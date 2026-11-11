@extends('layouts.painel')

@php use App\Http\Controllers\Painel\PedidoController as P; @endphp

@section('titulo', 'Pedidos')

@section('conteudo')
<h1>Pedidos</h1>

<form class="cartao filtros" method="get" action="{{ route('painel.pedidos.index') }}">
    <div class="campo">
        <label for="q">Buscar</label>
        <input id="q" name="q" type="search" value="{{ $busca }}" placeholder="Código, telefone, CPF ou número">
    </div>
    <div class="campo">
        <label for="campanha">Campanha</label>
        <select id="campanha" name="campanha">
            <option value="">Todas</option>
            @foreach ($campanhas as $c)
                <option value="{{ $c->id }}" @selected((int) request('campanha') === $c->id)>{{ $c->title }}</option>
            @endforeach
        </select>
    </div>
    <div class="campo">
        <label for="status">Situação</label>
        <select id="status" name="status">
            <option value="">Todas</option>
            @foreach (P::ROTULOS as $valor => $rotulo)
                <option value="{{ $valor }}" @selected(request('status') === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </div>
    <button class="botao" type="submit">Filtrar</button>
    <p class="pequeno filtros__dica">Para buscar por número do sorteio, escolha a campanha e digite só o número.</p>
</form>

<section class="cartao">
    @if ($pedidos->isEmpty())
        <p class="vazio">Nenhum pedido encontrado com esses filtros.</p>
    @else
        <div class="rolagem">
            <table>
                <thead><tr><th>Pedido</th><th>Campanha</th><th>Cliente</th><th class="num">Qtd.</th><th class="num">Valor</th><th>Situação</th><th>Criado em</th></tr></thead>
                <tbody>
                    @foreach ($pedidos as $p)
                        <tr>
                            <td><a href="{{ route('painel.pedidos.show', $p) }}"><code>{{ $p->code }}</code></a></td>
                            <td>{{ $p->campaign->title }}</td>
                            <td>{{ $p->customer->name }}</td>
                            <td class="num">{{ $p->quantity }}</td>
                            <td class="num">{{ brl($p->amount_cents) }}</td>
                            <td><span class="etiqueta etiqueta--{{ $p->status }}">{{ P::ROTULOS[$p->status] }}</span></td>
                            <td>{{ hora_local($p->created_at, 'd/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <nav class="paginas" aria-label="Páginas">
            <span class="pequeno">{{ number_format($pedidos->total(), 0, ',', '.') }} pedido(s) · página {{ $pedidos->currentPage() }} de {{ $pedidos->lastPage() }}</span>
            <span>
                @if ($pedidos->previousPageUrl())<a class="botao botao--linha" href="{{ $pedidos->previousPageUrl() }}">Anterior</a>@endif
                @if ($pedidos->nextPageUrl())<a class="botao botao--linha" href="{{ $pedidos->nextPageUrl() }}">Próxima</a>@endif
            </span>
        </nav>
    @endif
</section>
@endsection
