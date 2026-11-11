<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Pix\PixProvider;
use App\Services\Payments;
use App\Support\Auditoria;
use App\Support\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PedidoController extends Controller
{
    public const ROTULOS = [
        Order::PENDENTE => 'Aguardando pagamento',
        Order::PAGO => 'Pago',
        Order::EXPIRADO => 'Expirado',
        Order::CANCELADO => 'Cancelado',
        Order::ESTORNADO => 'Estornado',
    ];

    public function index(Request $request): View
    {
        $busca = trim((string) $request->query('q'));
        $digitos = preg_replace('/\D/', '', $busca);
        $campanhaId = (int) $request->query('campanha');

        $query = Order::with(['campaign', 'customer'])->latest('id');

        if ($request->filled('status') && isset(self::ROTULOS[$request->query('status')])) {
            $query->where('status', $request->query('status'));
        }
        if ($campanhaId) {
            $query->where('campaign_id', $campanhaId);
        }

        if ($busca !== '') {
            $query->where(function ($q) use ($busca, $digitos, $campanhaId) {
                $q->where('code', strtoupper($busca));

                if (in_array(strlen($digitos), [10, 11], true)) {
                    $q->orWhereIn('customer_id', Customer::where('phone', $digitos)->select('id'));
                }
                if (Cpf::valid($digitos)) {
                    $q->orWhereIn('customer_id', Customer::where('cpf_hash', Customer::hashCpf($digitos))->select('id'));
                }
                if ($campanhaId && $digitos !== '' && $digitos === $busca && strlen($digitos) <= 8) {
                    $q->orWhereIn('id', fn ($t) => $t->select('order_id')->from('tickets')->where('campaign_id', $campanhaId)->where('number', (int) $digitos));
                }
            });
        }

        return view('painel.pedidos.index', [
            'pedidos' => $query->paginate(30)->withQueryString(),
            'campanhas' => Campaign::latest('id')->get(['id', 'title']),
            'busca' => $busca,
        ]);
    }

    public function show(Order $pedido): View
    {
        $pedido->load(['campaign', 'customer', 'tickets', 'payments']);
        Auditoria::registrar('pedido.visto', $pedido);

        return view('painel.pedidos.show', ['pedido' => $pedido]);
    }

    public function cancelar(Request $request, Order $pedido, Payments $payments): RedirectResponse
    {
        $motivo = $this->motivo($request);

        if (! $payments->release($pedido, Order::CANCELADO, $motivo)) {
            return back()->with('erro', 'Só é possível cancelar pedidos que aguardam pagamento.');
        }

        Auditoria::registrar('pedido.cancelado', $pedido, ['motivo' => $motivo]);

        return back()->with('ok', 'Pedido cancelado e quantidade devolvida ao estoque.');
    }

    public function estornar(Request $request, Order $pedido, Payments $payments): RedirectResponse
    {
        $motivo = $this->motivo($request);

        if ($pedido->campaign->status === Campaign::APURADA) {
            return back()->with('erro', 'A campanha já foi apurada. O estorno não pode mais ser feito por aqui.');
        }

        try {
            $provider = app(PixProvider::class);
        } catch (RuntimeException) {
            return back()->with('erro', 'Nenhum provedor de Pix configurado para fazer o estorno.');
        }

        if (! $payments->refundPaid($pedido, $provider, $motivo)) {
            return back()->with('erro', 'O estorno não foi concluído. Confira o pedido no provedor e tente de novo.');
        }

        Auditoria::registrar('pedido.estornado', $pedido, ['motivo' => $motivo, 'valor' => $pedido->amount_cents]);

        return back()->with('ok', 'Pedido estornado. Os números voltaram para o estoque.');
    }

    private function motivo(Request $request): string
    {
        return $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:200']])['motivo'];
    }
}
