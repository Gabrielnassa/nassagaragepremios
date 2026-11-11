<?php

namespace App\Pix;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PixProvider
{
    public function name(): string;

    public function createCharge(Order $order): Charge;

    public function getCharge(Payment $payment): ChargeStatus;

    public function refund(Payment $payment): bool;

    /**
     * Valida a assinatura da notificação e devolve o estado informado.
     * Devolve null quando a assinatura não confere.
     */
    public function verifyWebhook(Request $request): ?ChargeStatus;
}
