<?php

/*
 * Teste de disputa: vários pedidos simultâneos tentando comprar os últimos números de uma campanha.
 * Precisa de um banco já migrado (de preferência MySQL) e não roda em produção.
 *
 * Uso: php tests/carga/disputa.php [pedidos=200] [restantes=50]
 */

use App\Exceptions\CheckoutException;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Ticket;
use App\Pix\PixProvider;
use App\Services\Checkout;
use App\Services\Payments;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

putenv('PIX_PROVIDER=simulado');
$_ENV['PIX_PROVIDER'] = $_SERVER['PIX_PROVIDER'] = 'simulado';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ($app->isProduction()) {
    fwrite(STDERR, "Este teste não roda em produção.\n");
    exit(1);
}

$pedidos = (int) ($argv[1] ?? 200);
$restantes = (int) ($argv[2] ?? 50);
$total = 1000;
$marca = 'disputa-'.bin2hex(random_bytes(4));

$campanha = Campaign::create([
    'slug' => $marca, 'title' => 'Disputa', 'status' => Campaign::ATIVA, 'price_cents' => 100,
    'total_numbers' => $total, 'digits' => 3, 'max_per_order' => 10, 'is_demo' => true,
]);

$clientes = [];
for ($i = 0; $i <= $pedidos; $i++) {
    $cliente = new Customer(['phone' => '119'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT), 'name' => 'Cliente Disputa', 'birth_date' => '1990-01-01', 'is_demo' => true]);
    $cliente->cpf_encrypted = 'x';
    $cliente->cpf_hash = hash('sha256', $marca.$i);
    $cliente->save();
    $clientes[] = $cliente->id;
}

$semente = Order::create(['code' => strtoupper(substr($marca, -8)).'00', 'campaign_id' => $campanha->id, 'customer_id' => $clientes[0], 'quantity' => $total - $restantes, 'amount_cents' => 0, 'status' => Order::PAGO, 'paid_at' => now()]);
$numeros = range(0, $total - 1);
shuffle($numeros);
foreach (array_chunk(array_slice($numeros, 0, $total - $restantes), 500) as $bloco) {
    Ticket::insert(array_map(fn ($n) => ['campaign_id' => $campanha->id, 'number' => $n, 'order_id' => $semente->id, 'created_at' => now()], $bloco));
}
$campanha->update(['sold_count' => $total - $restantes]);

$saida = sys_get_temp_dir().'/'.$marca;
mkdir($saida);
$largada = microtime(true) + 2.0;
DB::disconnect();

$filhos = [];
for ($i = 1; $i <= $pedidos; $i++) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        DB::purge();
        usleep((int) max(0, ($largada - microtime(true)) * 1e6));
        $resultado = ['aceito' => 0, 'pago' => 0, 'liberado' => 0, 'recusado' => 0, 'tentativas' => 0, 'erro' => null];

        try {
            $cliente = Customer::findOrFail($clientes[$i]);
            $quantidade = random_int(1, 3);
            $provider = app(PixProvider::class);
            $order = app(Checkout::class)->createOrder(Campaign::findOrFail($campanha->id), $quantidade, $cliente->phone, [], $provider);
            $resultado['aceito'] = $quantidade;

            if ($i % 5 === 0) {
                app(Payments::class)->release($order);
                $resultado['liberado'] = $quantidade;
            } else {
                $payment = $order->payments()->firstOrFail();
                for ($t = 1; $t <= 8; $t++) {
                    $resultado['tentativas'] = $t;
                    try {
                        app(Payments::class)->confirm($payment, $provider);
                        app(Payments::class)->confirm($payment, $provider);
                        $resultado['pago'] = $quantidade;
                        break;
                    } catch (Throwable $e) {
                        $resultado['erro'] = $e->getMessage();
                        usleep(random_int(20000, 120000));
                    }
                }
            }
        } catch (CheckoutException) {
            $resultado['recusado'] = 1;
        } catch (Throwable $e) {
            $resultado['erro'] = $e->getMessage();
        }

        file_put_contents("$saida/$i.json", json_encode($resultado));
        exit(0);
    }
    $filhos[] = $pid;
}

foreach ($filhos as $pid) {
    pcntl_waitpid($pid, $status);
}

DB::purge();
$r = ['aceito' => 0, 'pago' => 0, 'liberado' => 0, 'recusado' => 0, 'repeticoes' => 0, 'erros' => []];
foreach (glob("$saida/*.json") as $arquivo) {
    $dados = json_decode(file_get_contents($arquivo), true);
    foreach (['aceito', 'pago', 'liberado', 'recusado'] as $chave) {
        $r[$chave] += $dados[$chave];
    }
    $r['repeticoes'] += max(0, $dados['tentativas'] - 1);
    if ($dados['erro'] && ! $dados['pago'] && ! $dados['recusado']) {
        $r['erros'][] = $dados['erro'];
    }
    unlink($arquivo);
}
rmdir($saida);

$campanha->refresh();
$emitidos = Ticket::where('campaign_id', $campanha->id)->count();
$distintos = Ticket::where('campaign_id', $campanha->id)->distinct()->count('number');
$incompletos = Order::where('campaign_id', $campanha->id)->where('status', Order::PAGO)
    ->whereRaw('quantity <> (select count(*) from tickets where tickets.order_id = orders.id)')->count();

$falhas = [];
if ($emitidos !== $distintos) { $falhas[] = 'há número repetido'; }
if ($emitidos !== $campanha->sold_count) { $falhas[] = "sold_count ({$campanha->sold_count}) difere dos números emitidos ($emitidos)"; }
if ($campanha->sold_count + $campanha->reserved_count > $total) { $falhas[] = 'estoque passou do total'; }
if ($campanha->reserved_count !== 0) { $falhas[] = "sobrou reserva ({$campanha->reserved_count})"; }
if ($incompletos) { $falhas[] = "$incompletos pedido(s) pago(s) com quantidade diferente dos números"; }
if ($campanha->sold_count !== $total - $restantes + $r['pago']) { $falhas[] = 'vendidos não batem com os pagamentos confirmados'; }
if ($r['erros']) { $falhas[] = count($r['erros']).' erro(s): '.$r['erros'][0]; }

printf("Banco: %s | pedidos: %d | números livres no início: %d\n", DB::connection()->getDriverName(), $pedidos, $restantes);
printf("Aceitos: %d números | pagos: %d | liberados: %d | pedidos recusados: %d | repetições após conflito: %d\n", $r['aceito'], $r['pago'], $r['liberado'], $r['recusado'], $r['repeticoes']);
printf("Emitidos: %d | distintos: %d | sold_count: %d | reserved_count: %d\n", $emitidos, $distintos, $campanha->sold_count, $campanha->reserved_count);

Order::where('campaign_id', $campanha->id)->delete();
$campanha->delete();
Customer::whereIn('id', $clientes)->delete();

echo $falhas ? "FALHOU: ".implode('; ', $falhas)."\n" : "OK: nenhum número repetido e nenhum estoque negativo.\n";
exit($falhas ? 1 : 0);
