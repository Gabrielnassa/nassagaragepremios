@extends('layouts.app')

@section('titulo', termo('meus'))

@section('conteudo')
<div class="pagina pagina--estreita">
    <header class="cabeca">
        <h1>{{ termo('meus') }}</h1>
        <p>Informe os dados usados na compra para ver seus pedidos.</p>
    </header>

    @include('partials.golpes')

    <form class="consulta" method="post" action="{{ route('consulta.buscar') }}" novalidate data-consulta>
        @csrf
        <div class="campo">
            <label for="cpf">CPF</label>
            <input id="cpf" name="cpf" type="text" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00"
                value="{{ old('cpf') }}" data-mascara="cpf" required
                @error('cpf') aria-invalid="true" aria-describedby="erro-cpf" @enderror>
            @error('cpf')<p class="dica dica--erro" id="erro-cpf">{{ $message }}</p>@enderror
        </div>
        <div class="campo">
            <label for="nascimento">Data de nascimento</label>
            <input id="nascimento" name="nascimento" type="text" inputmode="numeric" autocomplete="bday" placeholder="dd/mm/aaaa"
                value="{{ old('nascimento') }}" data-mascara="data" required
                @error('nascimento') aria-invalid="true" aria-describedby="erro-nascimento" @enderror>
            @error('nascimento')<p class="dica dica--erro" id="erro-nascimento">{{ $message }}</p>@enderror
        </div>
        <button class="cta cta--simples" type="submit">@include('partials.icon', ['name' => 'search', 'size' => 20]) Consultar</button>
    </form>

    @isset($orders)
        <section class="bloco" aria-labelledby="titulo-pedidos" id="pedidos">
            <h2 id="titulo-pedidos">Pedidos de {{ $customer->publicName() }}</h2>
            @forelse ($orders as $order)
                <article class="pedido-card">
                    <header>
                        <div>
                            <p class="rotulo">Pedido {{ $order->code }}</p>
                            <h3><a href="{{ route('campanha', $order->campaign) }}">{{ $order->campaign->title }}</a></h3>
                        </div>
                        <span class="situacao-pedido situacao-pedido--{{ $order->status }}">{{ ['pendente' => 'Aguardando pagamento', 'pago' => 'Pago', 'expirado' => 'Expirado', 'cancelado' => 'Cancelado', 'estornado' => 'Estornado'][$order->status] }}</span>
                    </header>
                    <p class="dica">{{ $order->quantity }} {{ $order->quantity === 1 ? termo('singular') : termo('plural') }} · {{ brl($order->amount_cents) }} · {{ hora_local($order->paid_at ?? $order->created_at, 'd/m/Y \à\s H\hi') }}</p>
                    @if ($order->tickets->isNotEmpty())
                        <ul class="placas">
                            @foreach ($order->tickets as $ticket)
                                <li>@include('partials.placa', ['numero' => $order->campaign->formatNumber($ticket->number)])</li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @empty
                <div class="vazio">
                    @include('partials.icon', ['name' => 'ticket', 'size' => 28])
                    <h3>Nenhum pedido ainda</h3>
                    <p>Quando você comprar, os pedidos aparecem aqui.</p>
                </div>
            @endforelse
        </section>
    @else
        <div class="vazio">
            @include('partials.icon', ['name' => 'ticket', 'size' => 28])
            <h2>Suas participações em um só lugar</h2>
            <p>Preencha o CPF e a data de nascimento para ver seus pedidos e o status de cada um.</p>
        </div>
    @endisset
</div>
@endsection
