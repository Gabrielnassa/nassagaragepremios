@extends('layouts.painel')

@section('titulo', 'Campanhas')

@section('conteudo')
<div class="cabecalho">
    <h1>Campanhas</h1>
    @if (auth()->user()->pode('operador'))
        <a class="botao" href="{{ route('painel.campanhas.create') }}">Nova campanha</a>
    @endif
</div>

<section class="cartao">
    @if ($campanhas->isEmpty())
        <p class="vazio">Nenhuma campanha cadastrada. Crie a primeira em "Nova campanha".</p>
    @else
        <div class="rolagem">
            <table>
                <thead><tr><th>Campanha</th><th>Status</th><th class="num">Preço</th><th class="num">Vendido</th><th>Apuração</th><th></th></tr></thead>
                <tbody>
                    @foreach ($campanhas as $c)
                        <tr>
                            <td><strong>{{ $c->title }}</strong>@if ($c->is_demo) <span class="etiqueta">demonstração</span>@endif<br><span class="pequeno">/campanha/{{ $c->slug }}</span></td>
                            <td><span class="etiqueta etiqueta--{{ $c->status }}">{{ \App\Http\Controllers\Painel\CampanhaController::ROTULOS[$c->status] }}</span></td>
                            <td class="num">{{ brl($c->price_cents) }}</td>
                            <td class="num">{{ number_format($c->percentSold(), 2, ',', '.') }}%<br><span class="pequeno">{{ number_format($c->sold_count, 0, ',', '.') }} / {{ number_format($c->total_numbers, 0, ',', '.') }}</span></td>
                            <td>{{ $c->draw_at ? hora_local($c->draw_at, 'd/m/Y H:i') : '—' }}</td>
                            <td class="acoes">
                                @if (auth()->user()->pode('operador'))<a href="{{ route('painel.campanhas.edit', $c) }}">Editar</a>@endif
                                <a href="{{ route('painel.pedidos.index', ['campanha' => $c->id]) }}">Pedidos</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
