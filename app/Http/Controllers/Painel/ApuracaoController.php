<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Winner;
use App\Support\Auditoria;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ApuracaoController extends Controller
{
    public function show(Request $request, Campaign $campanha): View
    {
        $numero = $request->query('numero');
        $ticket = null;

        if ($numero !== null && ctype_digit((string) $numero)) {
            $ticket = Ticket::with('order.customer')->where('campaign_id', $campanha->id)->where('number', (int) $numero)->first();
            Auditoria::registrar('apuracao.conferencia', $campanha, ['numero' => (int) $numero]);
        }

        return view('painel.apuracao.show', [
            'campanha' => $campanha->load('prizes'),
            'numero' => $numero !== null && ctype_digit((string) $numero) ? (int) $numero : null,
            'ticket' => $ticket,
            'resultado' => (string) $request->query('resultado'),
            'ganhador' => Winner::where('campaign_id', $campanha->id)->where('type', 'principal')->first(),
        ]);
    }

    public function registrar(Request $request, Campaign $campanha): RedirectResponse
    {
        if ($campanha->status !== Campaign::ENCERRADA) {
            return back()->with('erro', 'Só é possível apurar campanhas com as vendas encerradas.');
        }

        $dados = $request->validate([
            'numero' => ['required', 'integer', 'min:0', 'max:'.($campanha->total_numbers - 1)],
            'resultado' => ['required', 'string', 'max:120'],
            'confirmo' => ['accepted'],
        ]);

        $ticket = Ticket::with('order.customer')->where('campaign_id', $campanha->id)->where('number', (int) $dados['numero'])->first();

        if (! $ticket || $ticket->order->status !== Order::PAGO) {
            return back()->with('erro', 'Nenhum pedido pago tem esse número. Confira no regulamento qual número vale nesse caso.');
        }

        DB::transaction(function () use ($campanha, $ticket, $dados) {
            $campanha->update([
                'status' => Campaign::APURADA,
                'winning_number' => $ticket->number,
                'official_result' => $dados['resultado'],
                'drawn_at' => now(),
            ]);

            Winner::create([
                'campaign_id' => $campanha->id,
                'order_id' => $ticket->order_id,
                'customer_id' => $ticket->order->customer_id,
                'type' => 'principal',
                'display_name' => $ticket->order->customer->publicName(),
                'prize' => $campanha->prizes()->orderBy('position')->value('title') ?? $campanha->title,
                'number' => $ticket->number,
                'awarded_on' => CarbonImmutable::now('America/Sao_Paulo')->toDateString(),
            ]);
        });

        Auditoria::registrar('apuracao.registrada', $campanha, ['numero' => $ticket->number, 'resultado' => $dados['resultado'], 'pedido' => $ticket->order_id]);

        return redirect()->route('painel.apuracao', $campanha)->with('ok', 'Apuração registrada. O ganhador só aparece no site depois da publicação.');
    }

    public function publicar(Request $request, Campaign $campanha): RedirectResponse
    {
        $request->validate(['consentimento' => ['accepted']]);

        $ganhador = Winner::where('campaign_id', $campanha->id)->where('type', 'principal')->firstOrFail();
        $ganhador->update(['consent_at' => $ganhador->consent_at ?? now(), 'published_at' => now()]);
        Auditoria::registrar('apuracao.publicada', $campanha, ['ganhador' => $ganhador->id]);

        return back()->with('ok', 'Ganhador publicado no site com o nome abreviado.');
    }
}
