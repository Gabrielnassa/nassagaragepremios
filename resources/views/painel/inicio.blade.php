@extends('layouts.painel')

@section('titulo', 'Visão geral')

@section('conteudo')
<h1>Visão geral</h1>

<div class="indicadores">
    @foreach ($vendas as $periodo => $v)
        <section class="cartao">
            <h2>{{ $periodo }}</h2>
            <p class="indicadores__valor">{{ brl($v['valor']) }}</p>
            <dl class="pares">
                <div><dt>Pedidos pagos</dt><dd>{{ number_format($v['pedidos'], 0, ',', '.') }}</dd></div>
                <div><dt>Números vendidos</dt><dd>{{ number_format($v['numeros'], 0, ',', '.') }}</dd></div>
                <div><dt>Ticket médio</dt><dd>{{ brl($v['ticket']) }}</dd></div>
            </dl>
        </section>
    @endforeach
</div>

<div class="duas">
    <section class="cartao">
        <h2>Campanhas em andamento</h2>
        @forelse ($campanhas as $c)
            <a class="andamento" href="{{ route('painel.campanhas.edit', $c) }}">
                <span><strong>{{ $c->title }}</strong> <span class="etiqueta etiqueta--{{ $c->status }}">{{ \App\Http\Controllers\Painel\CampanhaController::ROTULOS[$c->status] }}</span></span>
                <progress max="100" value="{{ $c->percentSold() }}">{{ $c->percentSold() }}%</progress>
                <span class="pequeno">{{ number_format($c->percentSold(), 2, ',', '.') }}% vendido · {{ number_format($c->sold_count, 0, ',', '.') }} de {{ number_format($c->total_numbers, 0, ',', '.') }} · {{ $c->reserved_count }} em reserva</span>
            </a>
        @empty
            <p class="vazio">Nenhuma campanha em andamento.</p>
        @endforelse
    </section>

    <section class="cartao">
        <h2>Pedidos por situação</h2>
        <dl class="pares">
            @foreach (\App\Http\Controllers\Painel\PedidoController::ROTULOS as $status => $rotulo)
                <div><dt><a href="{{ route('painel.pedidos.index', ['status' => $status]) }}">{{ $rotulo }}</a></dt><dd>{{ number_format($porStatus[$status] ?? 0, 0, ',', '.') }}</dd></div>
            @endforeach
        </dl>
    </section>
</div>

<section class="cartao">
    <h2>Últimos pagamentos</h2>
    @if ($ultimos->isEmpty())
        <p class="vazio">Nenhum pagamento confirmado ainda.</p>
    @else
        <div class="rolagem">
            <table>
                <thead><tr><th>Pedido</th><th>Campanha</th><th>Cliente</th><th class="num">Qtd.</th><th class="num">Valor</th><th>Pago em</th></tr></thead>
                <tbody>
                    @foreach ($ultimos as $p)
                        <tr>
                            <td><a href="{{ route('painel.pedidos.show', $p) }}"><code>{{ $p->code }}</code></a></td>
                            <td>{{ $p->campaign->title }}</td>
                            <td>{{ $p->customer->name }}</td>
                            <td class="num">{{ $p->quantity }}</td>
                            <td class="num">{{ brl($p->amount_cents) }}</td>
                            <td>{{ hora_local($p->paid_at, 'd/m H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
