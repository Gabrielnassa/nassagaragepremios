<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Support\Cpf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ConsultaController extends Controller
{
    public function form(): View
    {
        return view('consulta');
    }

    public function buscar(Request $request): View
    {
        $request->flash();

        if (! Cpf::valid($request->input('cpf'))) {
            throw ValidationException::withMessages(['cpf' => 'Confira o CPF: ele precisa ter 11 dígitos válidos.']);
        }

        $nascimento = $this->data((string) $request->input('nascimento'));
        if (! $nascimento) {
            throw ValidationException::withMessages(['nascimento' => 'Digite a data no formato dia/mês/ano, por exemplo 05/03/1990.']);
        }

        $customer = Customer::where('cpf_hash', Customer::hashCpf($request->input('cpf')))->first();

        if (! $customer || ! $customer->birth_date->isSameDay($nascimento)) {
            throw ValidationException::withMessages(['cpf' => 'Não encontramos compras com esse CPF e essa data de nascimento. Confira os dados e tente de novo.']);
        }

        $orders = Order::with(['campaign', 'tickets'])
            ->where('customer_id', $customer->id)
            ->whereIn('status', [Order::PENDENTE, Order::PAGO, Order::ESTORNADO])
            ->latest('id')
            ->limit(50)
            ->get();

        return view('consulta', ['customer' => $customer, 'orders' => $orders, 'demo' => $customer->is_demo]);
    }

    private function data(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', trim($value), $m) || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!d/m/Y', $value);
        } catch (Throwable) {
            return null;
        }
    }
}
