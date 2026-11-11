<?php

namespace App\Services;

use App\Exceptions\CheckoutException;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SelfExclusion;
use App\Pix\PixProvider;
use App\Support\Cpf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class Checkout
{
    public function __construct(private readonly Payments $payments)
    {
    }

    public static function phone(?string $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if (! in_array(strlen($digits), [10, 11], true) || (int) substr($digits, 0, 2) < 11) {
            throw new CheckoutException('Digite o DDD e o número, por exemplo (11) 90000-0000.', 'telefone');
        }

        return $digits;
    }

    /**
     * @param  array{nome?: ?string, cpf?: ?string, nascimento?: ?string, email?: ?string, aceite?: mixed}  $data
     */
    public function createOrder(Campaign $campaign, int $quantity, string $phone, array $data, PixProvider $provider): Order
    {
        if (! $campaign->isOnSale()) {
            throw new CheckoutException('Esta campanha não está à venda agora.', 'geral', 409);
        }

        $max = $campaign->maxPurchasable();
        if ($quantity < min($campaign->min_per_order, $max) || $quantity > $max) {
            throw new CheckoutException("Escolha de {$campaign->min_per_order} a {$max} por pedido.", 'quantidade');
        }

        $customer = Customer::where('phone', $phone)->first() ?? $this->register($phone, $data);

        if ($customer->blocked_at) {
            throw new CheckoutException('Este cadastro está bloqueado para compras. Fale com o suporte.', 'geral', 403);
        }

        if (SelfExclusion::active()->where('cpf_hash', $customer->cpf_hash)->exists()) {
            throw new CheckoutException('Há um pedido de autoexclusão ativo para este CPF. A compra não pode ser feita.', 'geral', 403);
        }

        $order = DB::transaction(function () use ($campaign, $customer, $quantity) {
            $this->payments->lockCampaign($campaign->id);

            Order::where('campaign_id', $campaign->id)->where('customer_id', $customer->id)
                ->where('status', Order::PENDENTE)->get()
                ->each(fn (Order $old) => $this->payments->release($old, Order::CANCELADO, 'Substituído por um novo pedido'));

            $reserved = Campaign::whereKey($campaign->id)
                ->where('status', Campaign::ATIVA)
                ->whereRaw('sold_count + reserved_count + ? <= total_numbers', [$quantity])
                ->update(['reserved_count' => DB::raw('reserved_count + '.$quantity)]);

            if (! $reserved) {
                throw new CheckoutException('Não há mais essa quantidade disponível. Atualize a página e tente com menos.', 'quantidade', 409);
            }

            return Order::create([
                'code' => $this->code(),
                'campaign_id' => $campaign->id,
                'customer_id' => $customer->id,
                'quantity' => $quantity,
                'amount_cents' => $quantity * $campaign->price_cents,
                'status' => Order::PENDENTE,
                'expires_at' => now()->addMinutes($campaign->reservation_minutes),
            ]);
        }, 3);

        try {
            $charge = $provider->createCharge($order);

            Payment::create([
                'order_id' => $order->id,
                'provider' => $provider->name(),
                'provider_id' => $charge->providerId,
                'status' => 'pendente',
                'amount_cents' => $order->amount_cents,
                'pix_payload' => $charge->payload,
                'raw' => $charge->raw,
            ]);
        } catch (Throwable $e) {
            $this->payments->release($order, Order::CANCELADO, 'Falha ao gerar a cobrança');
            Log::error('Falha ao gerar cobrança Pix', ['order_id' => $order->id, 'erro' => $e->getMessage()]);

            throw new CheckoutException('Não foi possível gerar o Pix agora. Tente de novo em instantes.', 'geral', 503);
        }

        return $order;
    }

    private function register(string $phone, array $data): Customer
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) ($data['nome'] ?? '')));
        if (mb_strlen($name) < 5 || mb_strlen($name) > 120 || ! str_contains($name, ' ') || preg_match('/[^\p{L}\s\'.-]/u', $name)) {
            throw new CheckoutException('Digite seu nome completo, como está no documento.', 'nome');
        }

        if (! Cpf::valid($data['cpf'] ?? null)) {
            throw new CheckoutException('Confira o CPF: ele precisa ter 11 dígitos válidos.', 'cpf');
        }

        $birth = $this->birthDate((string) ($data['nascimento'] ?? ''));
        if (! $birth) {
            throw new CheckoutException('Digite a data no formato dia/mês/ano, por exemplo 05/03/1990.', 'nascimento');
        }
        if ($birth->gt(CarbonImmutable::now('America/Sao_Paulo')->subYears(18)->endOfDay())) {
            throw new CheckoutException('A compra é permitida apenas para maiores de 18 anos.', 'nascimento');
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160)) {
            throw new CheckoutException('Confira o e-mail ou deixe o campo vazio.', 'email');
        }

        if (! filter_var($data['aceite'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new CheckoutException('Marque a confirmação para continuar.', 'aceite');
        }

        if (Customer::where('cpf_hash', Customer::hashCpf($data['cpf']))->exists()) {
            throw new CheckoutException('Este CPF já está cadastrado com outro telefone. Use o telefone do cadastro ou fale com o suporte.', 'cpf');
        }

        $customer = new Customer([
            'phone' => $phone,
            'name' => $name,
            'birth_date' => $birth->toDateString(),
            'email' => $email ?: null,
            'terms_accepted_at' => now(),
        ]);
        $customer->setCpf($data['cpf']);
        $customer->save();

        return $customer;
    }

    private function birthDate(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', trim($value), $m) || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3]) || (int) $m[3] < 1900) {
            return null;
        }

        return CarbonImmutable::create((int) $m[3], (int) $m[2], (int) $m[1], 0, 0, 0, 'America/Sao_Paulo');
    }

    private function code(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)];
            }
        } while (Order::where('code', $code)->exists());

        return $code;
    }
}
