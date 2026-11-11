<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Pix\PixProvider;
use App\Services\Payments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PixWebhookController extends Controller
{
    public function __invoke(Request $request, Payments $payments): JsonResponse
    {
        try {
            $provider = app(PixProvider::class);
        } catch (RuntimeException) {
            return response()->json(['erro' => 'provedor indisponível'], 503);
        }

        $event = $provider->verifyWebhook($request);
        if ($event === null) {
            return response()->json(['erro' => 'assinatura inválida'], 401);
        }

        $payment = Payment::where('provider', $provider->name())->where('provider_id', $event->providerId)->first();
        if ($payment === null || ! $event->paid) {
            return response()->json(['resultado' => 'ignorado']);
        }

        return response()->json(['resultado' => $payments->confirm($payment, $provider, $event->paidAt)]);
    }
}
