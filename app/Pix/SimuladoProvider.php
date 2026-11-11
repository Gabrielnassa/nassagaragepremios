<?php

namespace App\Pix;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Provedor de teste. Não movimenta dinheiro e não pode ser usado em produção.
 */
class SimuladoProvider implements PixProvider
{
    public function __construct(private readonly string $secret)
    {
    }

    public function name(): string
    {
        return 'simulado';
    }

    public function createCharge(Order $order): Charge
    {
        $id = 'sim_'.Str::lower(Str::random(24));

        $payload = BrCode::build(
            '00000000-0000-4000-8000-000000000000',
            $order->amount_cents,
            $order->code,
            'PAGAMENTO DE TESTE',
            'SAO PAULO',
        );

        return new Charge($id, $payload, $order->expires_at, ['simulado' => true]);
    }

    public function getCharge(Payment $payment): ChargeStatus
    {
        $paid = ($payment->raw['pago_em'] ?? null) !== null;

        return new ChargeStatus($payment->provider_id, $paid, $paid ? Carbon::parse($payment->raw['pago_em']) : null);
    }

    public function refund(Payment $payment): bool
    {
        return true;
    }

    public function verifyWebhook(Request $request): ?ChargeStatus
    {
        $signature = (string) $request->header('X-Assinatura');
        $expected = $this->sign($request->getContent());

        if ($this->secret === '' || ! hash_equals($expected, $signature)) {
            return null;
        }

        $id = (string) $request->json('id');
        if ($id === '') {
            return null;
        }

        return new ChargeStatus($id, $request->json('status') === 'pago', now());
    }

    public function sign(string $body): string
    {
        return hash_hmac('sha256', $body, $this->secret);
    }
}
