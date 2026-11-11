@extends('layouts.painel')

@php
    use App\Http\Controllers\Painel\PedidoController as P;
    use App\Models\Order;
    $cliente = $pedido->customer;
    $cpf = $cliente->cpf_encrypted !== 'x' ? preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $cliente->cpf()) : '—';
    $fone = preg_replace('/^(\d{2})(\d{4,5})(\d{4})$/', '($1) $2-$3', $cliente->phone);
    $eu = auth()->user();
@endphp

@section('titulo', 'Pedido '.$pedido->code)

@section('conteudo')
<div class="cabecalho">
    <div>
        <p class="pequeno"><a href="{{ route('painel.pedidos.index') }}">Pedidos</a></p>
        <h1>Pedido <code>{{ $pedido->code }}</code></h1>
    </div>
    <span class="etiqueta etiqueta--{{ $pedido->status }}">{{ P::ROTULOS[$pedido->status] }}</span>
</div>

<div class="duas">
    <section class="cartao">
        <h2>Compra</h2>
        <dl class="pares">
            <div><dt>Campanha</dt><dd><a href="{{ route('painel.campanhas.edit', $pedido->campaign) }}">{{ $pedido->campaign->title }}</a></dd></div>
            <div><dt>Quantidade</dt><dd>{{ $pedido->quantity }}</dd></div>
            <div><dt>Valor</dt><dd>{{ brl($pedido->amount_cents) }}</dd></div>
            <div><dt>Criado em</dt><dd>{{ hora_local($pedido->created_at, 'd/m/Y H:i') }}</dd></div>
            @if ($pedido->status === Order::PENDENTE)<div><dt>Expira em</dt><dd>{{ hora_local($pedido->expires_at, 'd/m/Y H:i') }}</dd></div>@endif
            @if ($pedido->paid_at)<div><dt>Pago em</dt><dd>{{ hora_local($pedido->paid_at, 'd/m/Y H:i') }}</dd></div>@endif
            @if ($pedido->close_reason)<div><dt>Motivo</dt><dd>{{ $pedido->close_reason }}</dd></div>@endif
        </dl>
    </section>

    <section class="cartao">
        <h2>Cliente</h2>
        <dl class="pares">
            <div><dt>Nome</dt><dd>{{ $cliente->name }}</dd></div>
            <div><dt>CPF</dt><dd>{{ $cpf }}</dd></div>
            <div><dt>Telefone</dt><dd>{{ $fone }}</dd></div>
            <div><dt>Nascimento</dt><dd>{{ $cliente->birth_date->format('d/m/Y') }}</dd></div>
            <div><dt>E-mail</dt><dd>{{ $cliente->email ?? '—' }}</dd></div>
        </dl>
        <p class="pequeno">A abertura desta página fica registrada no log de auditoria.</p>
    </section>
</div>

<section class="cartao">
    <h2>Pagamentos</h2>
    @forelse ($pedido->payments as $pg)
        <dl class="pares pares--linha">
            <div><dt>Provedor</dt><dd>{{ $pg->provider }}</dd></div>
            <div><dt>Identificador</dt><dd><code>{{ $pg->provider_id }}</code></dd></div>
            <div><dt>Situação</dt><dd>{{ $pg->status }}</dd></div>
            <div><dt>Valor</dt><dd>{{ brl($pg->amount_cents) }}</dd></div>
        </dl>
    @empty
        <p class="vazio">Sem cobrança registrada.</p>
    @endforelse
</section>

@if ($pedido->tickets->isNotEmpty())
    <section class="cartao">
        <h2>Números ({{ $pedido->tickets->count() }})</h2>
        <p class="numeros">@foreach ($pedido->tickets as $t)<code>{{ $pedido->campaign->formatNumber($t->number) }}</code> @endforeach</p>
    </section>
@endif

@if ($pedido->status === Order::PENDENTE && $eu->pode('operador'))
    <form class="cartao" method="post" action="{{ route('painel.pedidos.cancelar', $pedido) }}" data-confirma="Cancelar este pedido e devolver a quantidade ao estoque?">
        @csrf
        <h2>Cancelar pedido</h2>
        <div class="campo campo--largo">
            <label for="motivo">Motivo</label>
            <input id="motivo" name="motivo" type="text" minlength="5" maxlength="200" required>
        </div>
        <button class="botao botao--perigo" type="submit">Cancelar pedido</button>
    </form>
@elseif ($pedido->status === Order::PAGO && $eu->pode('dono'))
    <form class="cartao" method="post" action="{{ route('painel.pedidos.estornar', $pedido) }}" data-confirma="Estornar {{ brl($pedido->amount_cents) }} e apagar os números deste pedido? Não dá para desfazer.">
        @csrf
        <h2>Estornar pedido</h2>
        <p class="pequeno">O valor é devolvido pelo provedor de Pix, os números são apagados e voltam para o estoque.</p>
        <div class="campo campo--largo">
            <label for="motivo">Motivo</label>
            <input id="motivo" name="motivo" type="text" minlength="5" maxlength="200" required>
        </div>
        <button class="botao botao--perigo" type="submit">Estornar pedido</button>
    </form>
@endif
@endsection
