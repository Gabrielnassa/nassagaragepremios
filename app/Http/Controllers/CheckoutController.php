<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Models\Campaign;
use App\Models\Customer;
use App\Pix\PixProvider;
use App\Services\Checkout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CheckoutController extends Controller
{
    public function identificar(Request $request): JsonResponse
    {
        try {
            $phone = Checkout::phone($request->input('telefone'));
        } catch (CheckoutException $e) {
            return $this->erro($e);
        }

        $customer = Customer::where('phone', $phone)->first();

        return response()->json([
            'conhecido' => $customer !== null,
            'nome' => $customer?->publicName(),
        ]);
    }

    public function criar(Request $request, Campaign $campaign, Checkout $checkout): JsonResponse
    {
        try {
            $provider = app(PixProvider::class);
        } catch (RuntimeException) {
            return $this->erro(new CheckoutException('O pagamento está indisponível no momento. Tente de novo mais tarde.', 'geral', 503));
        }

        try {
            $order = $checkout->createOrder(
                $campaign,
                (int) $request->input('quantidade'),
                Checkout::phone($request->input('telefone')),
                $request->only(['nome', 'cpf', 'nascimento', 'email', 'aceite']),
                $provider,
            );
        } catch (CheckoutException $e) {
            return $this->erro($e);
        }

        return response()->json(['url' => route('pedido', $order->code)], 201);
    }

    private function erro(CheckoutException $e): JsonResponse
    {
        return response()->json(['campo' => $e->field, 'mensagem' => $e->getMessage()], $e->status);
    }
}
