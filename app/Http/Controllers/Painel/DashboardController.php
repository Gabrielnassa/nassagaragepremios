<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $now = CarbonImmutable::now('America/Sao_Paulo');
        $periods = ['Hoje' => $now->startOfDay(), '7 dias' => $now->subDays(6)->startOfDay(), '30 dias' => $now->subDays(29)->startOfDay()];

        $sales = [];
        foreach ($periods as $label => $since) {
            $row = Order::where('status', Order::PAGO)->where('paid_at', '>=', $since->utc())
                ->selectRaw('COUNT(*) as pedidos, COALESCE(SUM(amount_cents), 0) as valor, COALESCE(SUM(quantity), 0) as numeros')
                ->first();

            $sales[$label] = [
                'pedidos' => (int) $row->pedidos,
                'valor' => (int) $row->valor,
                'numeros' => (int) $row->numeros,
                'ticket' => $row->pedidos ? intdiv((int) $row->valor, (int) $row->pedidos) : 0,
            ];
        }

        return view('painel.inicio', [
            'vendas' => $sales,
            'porStatus' => Order::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'campanhas' => Campaign::whereIn('status', [Campaign::ATIVA, Campaign::PAUSADA, Campaign::ENCERRADA, Campaign::EM_BREVE])->latest('id')->get(),
            'ultimos' => Order::with(['campaign', 'customer'])->where('status', Order::PAGO)->latest('paid_at')->limit(8)->get(),
        ]);
    }
}
