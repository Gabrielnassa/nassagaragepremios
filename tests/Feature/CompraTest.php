<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\InstantPrize;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SelfExclusion;
use App\Models\Ticket;
use App\Pix\BrCode;
use App\Pix\PixProvider;
use App\Services\Payments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CompraTest extends TestCase
{
    use RefreshDatabase;

    private const NOVO = ['nome' => 'Fulano de Tal', 'cpf' => '529.982.247-25', 'nascimento' => '05/03/1990', 'aceite' => true];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function campanha(array $dados = []): Campaign
    {
        return Campaign::create($dados + [
            'slug' => 'teste', 'title' => 'Campanha de teste', 'status' => Campaign::ATIVA,
            'price_cents' => 4990, 'total_numbers' => 100, 'digits' => 2, 'max_per_order' => 50,
        ]);
    }

    private function comprar(int $quantidade = 3, array $dados = self::NOVO, string $telefone = '11987654321'): TestResponse
    {
        return $this->postJson('/campanha/teste/pedido', ['telefone' => $telefone, 'quantidade' => $quantidade] + $dados);
    }

    private function pagar(Order $order): string
    {
        return app(Payments::class)->confirm($order->payments()->firstOrFail(), app(PixProvider::class));
    }

    private function webhook(string $providerId, ?string $assinatura = null): TestResponse
    {
        $corpo = json_encode(['id' => $providerId, 'status' => 'pago']);
        $assinatura ??= hash_hmac('sha256', $corpo, 'segredo-de-teste');

        return $this->call('POST', '/webhooks/pix', [], [], [], ['HTTP_X_ASSINATURA' => $assinatura, 'CONTENT_TYPE' => 'application/json'], $corpo);
    }

    public function test_telefone_novo_pede_cadastro_e_conhecido_mostra_nome_abreviado(): void
    {
        $this->campanha();

        $this->postJson('/checkout/identificar', ['telefone' => '(11) 98765-4321'])->assertOk()->assertJson(['conhecido' => false]);
        $this->postJson('/checkout/identificar', ['telefone' => '123'])->assertStatus(422)->assertJson(['campo' => 'telefone']);

        $this->comprar()->assertCreated();

        $this->postJson('/checkout/identificar', ['telefone' => '11987654321'])
            ->assertOk()->assertExactJson(['conhecido' => true, 'nome' => 'Fulano T.']);
    }

    public function test_pedido_reserva_a_quantidade_e_calcula_o_valor_no_servidor(): void
    {
        $campanha = $this->campanha();

        $resposta = $this->comprar(3, self::NOVO + ['valor' => 1, 'amount_cents' => 1]);
        $resposta->assertCreated();

        $pedido = Order::firstOrFail();
        $this->assertSame(Order::PENDENTE, $pedido->status);
        $this->assertSame(3 * 4990, $pedido->amount_cents);
        $this->assertSame(0, Ticket::count());
        $this->assertSame(3, $campanha->fresh()->reserved_count);
        $this->assertSame(0, $campanha->fresh()->sold_count);
        $this->assertStringEndsWith('/pedido/'.$pedido->code, $resposta->json('url'));

        $this->get('/pedido/'.$pedido->code)->assertOk()->assertSee('Pague com Pix')->assertSee($pedido->payments()->first()->pix_payload);
    }

    public function test_cadastro_valida_cpf_idade_e_aceite(): void
    {
        $this->campanha();

        $this->comprar(1, ['cpf' => '111.111.111-11'] + self::NOVO)->assertStatus(422)->assertJson(['campo' => 'cpf']);
        $this->comprar(1, ['nascimento' => now()->subYears(17)->format('d/m/Y')] + self::NOVO)->assertStatus(422)->assertJson(['campo' => 'nascimento']);
        $this->comprar(1, ['aceite' => false] + self::NOVO)->assertStatus(422)->assertJson(['campo' => 'aceite']);
        $this->comprar(1, ['nome' => 'Fulano'] + self::NOVO)->assertStatus(422)->assertJson(['campo' => 'nome']);

        $this->assertSame(0, Customer::count());
        $this->assertSame(0, Order::count());
    }

    public function test_quantidade_respeita_limite_e_estoque(): void
    {
        $campanha = $this->campanha(['sold_count' => 96]);

        $this->comprar(5)->assertStatus(422)->assertJson(['campo' => 'quantidade']);
        $this->comprar(0)->assertStatus(422);
        $this->comprar(4)->assertCreated();
        $this->comprar(1, ['cpf' => '111.444.777-35'] + self::NOVO, '11911112222')->assertStatus(409);

        $this->assertSame(4, $campanha->fresh()->reserved_count);
    }

    public function test_pagamento_atribui_numeros_unicos_e_atualiza_contadores(): void
    {
        $campanha = $this->campanha();
        $this->comprar(30)->assertCreated();
        $pedido = Order::firstOrFail();

        $this->assertSame('pago', $this->pagar($pedido));

        $numeros = Ticket::where('order_id', $pedido->id)->pluck('number');
        $this->assertCount(30, $numeros);
        $this->assertCount(30, $numeros->unique());
        $this->assertTrue($numeros->every(fn ($n) => $n >= 0 && $n < 100));
        $this->assertSame([30, 0], [$campanha->fresh()->sold_count, $campanha->fresh()->reserved_count]);
        $this->assertSame(Order::PAGO, $pedido->fresh()->status);

        $this->get('/pedido/'.$pedido->code)->assertOk()->assertSee('Pagamento confirmado');
    }

    public function test_campanha_pode_ser_vendida_ate_o_ultimo_numero(): void
    {
        $campanha = $this->campanha(['max_per_order' => 100]);
        $cpfs = ['529.982.247-25', '111.444.777-35', '390.533.447-05', '168.995.350-09'];

        foreach ([40, 40, 15, 5] as $i => $quantidade) {
            $this->comprar($quantidade, ['cpf' => $cpfs[$i]] + self::NOVO, '1190000000'.$i)->assertCreated();
            $this->pagar(Order::latest('id')->firstOrFail());
        }

        $this->assertSame(100, Ticket::count());
        $this->assertSame(100, Ticket::distinct()->count('number'));
        $this->assertSame([100, 0], [$campanha->fresh()->sold_count, $campanha->fresh()->reserved_count]);
        $this->assertSame('esgotada', $campanha->fresh()->publicState());
    }

    public function test_webhook_exige_assinatura_e_repeticao_nao_duplica(): void
    {
        $campanha = $this->campanha();
        $this->comprar(5)->assertCreated();
        $pagamento = Payment::firstOrFail();

        $this->webhook($pagamento->provider_id, 'assinatura-errada')->assertStatus(401);
        $this->assertSame(0, Ticket::count());

        $this->webhook($pagamento->provider_id)->assertOk()->assertJson(['resultado' => 'pago']);
        $this->webhook($pagamento->provider_id)->assertOk()->assertJson(['resultado' => 'repetido']);
        $this->webhook('sim_inexistente')->assertOk()->assertJson(['resultado' => 'ignorado']);

        $this->assertSame(5, Ticket::count());
        $this->assertSame([5, 0], [$campanha->fresh()->sold_count, $campanha->fresh()->reserved_count]);
    }

    public function test_pedido_expirado_devolve_o_estoque(): void
    {
        $campanha = $this->campanha();
        $this->comprar(10)->assertCreated();
        $pedido = Order::firstOrFail();

        $this->travel(14)->minutes();
        $this->artisan('pedidos:expirar');
        $this->assertSame(Order::PENDENTE, $pedido->fresh()->status);

        $this->travel(3)->minutes();
        $this->artisan('pedidos:expirar');
        $this->artisan('pedidos:expirar');

        $this->assertSame(Order::EXPIRADO, $pedido->fresh()->status);
        $this->assertSame([0, 0], [$campanha->fresh()->sold_count, $campanha->fresh()->reserved_count]);
        $this->get('/pedido/'.$pedido->code)->assertOk()->assertSee('O prazo para pagar este pedido acabou');
    }

    public function test_pagamento_atrasado_e_honrado_se_ha_estoque_e_estornado_se_nao_ha(): void
    {
        $campanha = $this->campanha(['total_numbers' => 10, 'digits' => 1, 'max_per_order' => 10]);
        $this->comprar(6)->assertCreated();
        $primeiro = Order::firstOrFail();
        app(Payments::class)->release($primeiro);

        $this->assertSame('pago', $this->pagar($primeiro));
        $this->assertSame(6, Ticket::where('order_id', $primeiro->id)->count());

        $this->comprar(4, ['cpf' => '111.444.777-35'] + self::NOVO, '11911112222')->assertCreated();
        $segundo = Order::latest('id')->firstOrFail();
        app(Payments::class)->release($segundo);
        $campanha->update(['sold_count' => 10]);

        $this->assertSame('estornar', $this->pagar($segundo));
        $this->assertSame(Order::ESTORNADO, $segundo->fresh()->status);
        $this->assertSame('estornado', $segundo->payments()->first()->status);
        $this->assertSame(0, Ticket::where('order_id', $segundo->id)->count());
        $this->assertSame(10, $campanha->fresh()->sold_count);
    }

    public function test_novo_pedido_substitui_o_pendente_do_mesmo_cliente(): void
    {
        $campanha = $this->campanha();
        $this->comprar(10)->assertCreated();
        $this->comprar(4, [])->assertCreated();

        $this->assertSame([Order::CANCELADO, Order::PENDENTE], Order::orderBy('id')->pluck('status')->all());
        $this->assertSame(4, $campanha->fresh()->reserved_count);
    }

    public function test_numero_premiado_e_registrado_na_confirmacao(): void
    {
        $campanha = $this->campanha(['total_numbers' => 5, 'digits' => 1, 'max_per_order' => 5]);
        InstantPrize::create(['campaign_id' => $campanha->id, 'number' => 3, 'prize' => 'R$ 500 no Pix']);

        $this->comprar(5)->assertCreated();
        $pedido = Order::firstOrFail();
        $this->pagar($pedido);

        $premio = InstantPrize::firstOrFail();
        $this->assertSame(Ticket::where('number', 3)->value('id'), $premio->ticket_id);
        $this->assertNotNull($premio->won_at);
        $this->get('/pedido/'.$pedido->code)->assertSee('R$ 500 no Pix');
    }

    public function test_autoexclusao_e_bloqueio_impedem_a_compra(): void
    {
        $this->campanha();
        $this->comprar(1)->assertCreated();
        $cliente = Customer::firstOrFail();

        SelfExclusion::create(['cpf_hash' => $cliente->cpf_hash, 'period' => '3_meses', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(3)]);
        $this->comprar(1, [])->assertStatus(403);

        SelfExclusion::query()->update(['revoked_at' => now()]);
        $cliente->update(['blocked_at' => now()]);
        $this->comprar(1, [])->assertStatus(403);
    }

    public function test_sem_provedor_configurado_o_checkout_fica_indisponivel(): void
    {
        $this->campanha();
        config(['pix.provider' => null]);
        $this->app->forgetInstance(PixProvider::class);

        $this->comprar(1)->assertStatus(503);
        $this->assertSame(0, Order::count());
    }

    public function test_provedor_simulado_nao_existe_em_producao(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->app->forgetInstance(PixProvider::class);

        $this->expectException(\RuntimeException::class);
        app(PixProvider::class);
    }

    public function test_codigo_pix_tem_crc_valido(): void
    {
        $codigo = BrCode::build('00000000-0000-4000-8000-000000000000', 4990, 'ABC123', 'PAGAMENTO DE TESTE', 'SAO PAULO');

        $this->assertStringContainsString('540549.90', $codigo);
        $this->assertSame(substr($codigo, -4), BrCode::crc16(substr($codigo, 0, -4)));
        $this->assertSame('29B1', BrCode::crc16('123456789'));
    }
}
