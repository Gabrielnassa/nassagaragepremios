<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Pix\PixProvider;
use App\Pix\SimuladoProvider;
use App\Services\Payments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class PedidoController extends Controller
{
    public function show(string $code, Payments $payments): View
    {
        $order = $this->order($code, $payments);
        $order->load(['campaign', 'customer', 'tickets']);
        $payment = $order->payments()->latest('id')->first();

        $premios = $order->status === Order::PAGO
            ? $order->campaign->instantPrizes()->whereIn('ticket_id', $order->tickets->pluck('id'))->get()
            : collect();

        return view('pedido.show', [
            'order' => $order,
            'campaign' => $order->campaign,
            'payment' => $payment,
            'premios' => $premios,
            'simulado' => $payment?->provider === 'simulado' && $this->simulado() !== null,
            'demo' => $order->campaign->is_demo,
        ]);
    }

    public function status(string $code, Payments $payments): JsonResponse
    {
        return response()->json(['status' => $this->order($code, $payments)->status])
            ->header('Cache-Control', 'no-store');
    }

    public function simular(string $code, Payments $payments): RedirectResponse
    {
        $provider = $this->simulado();
        abort_if($provider === null, 404);

        $order = Order::where('code', $code)->firstOrFail();
        $payment = $order->payments()->where('provider', 'simulado')->latest('id')->firstOrFail();

        $payment->update(['raw' => ['simulado' => true, 'pago_em' => now()->toIso8601String()]]);
        $payments->confirm($payment, $provider);

        return redirect()->route('pedido', $order->code);
    }

    private function order(string $code, Payments $payments): Order
    {
        $order = Order::where('code', strtoupper($code))->firstOrFail();

        if ($payments->isOverdue($order)) {
            $payments->release($order, Order::EXPIRADO, 'Prazo de pagamento encerrado');
            $order->refresh();
        }

        return $order;
    }

    private function simulado(): ?SimuladoProvider
    {
        try {
            $provider = app(PixProvider::class);
        } catch (RuntimeException) {
            return null;
        }

        return $provider instanceof SimuladoProvider ? $provider : null;
    }
}
