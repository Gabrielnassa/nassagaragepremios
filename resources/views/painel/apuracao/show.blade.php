@extends('layouts.painel')

@php
    use App\Models\Campaign;
    use App\Models\Order;
    $encerrada = $campanha->status === Campaign::ENCERRADA;
    $dono = auth()->user()->pode('dono');
@endphp

@section('titulo', 'Apuração')

@section('conteudo')
<div class="cabecalho">
    <div>
        <p class="pequeno"><a href="{{ route('painel.campanhas.edit', $campanha) }}">{{ $campanha->title }}</a></p>
        <h1>Apuração</h1>
    </div>
</div>

@if ($campanha->status === Campaign::APURADA && $ganhador)
    <section class="cartao">
        <h2>Resultado registrado</h2>
        <dl class="pares">
            <div><dt>Número contemplado</dt><dd><strong>{{ $campanha->formatNumber($campanha->winning_number) }}</strong></dd></div>
            <div><dt>Resultado oficial</dt><dd>{{ $campanha->official_result }}</dd></div>
            <div><dt>Registrado em</dt><dd>{{ hora_local($campanha->drawn_at, 'd/m/Y H:i') }}</dd></div>
            <div><dt>Prêmio</dt><dd>{{ $ganhador->prize }}</dd></div>
            <div><dt>Nome no site</dt><dd>{{ $ganhador->display_name }}</dd></div>
            <div><dt>Pedido</dt><dd>@if ($ganhador->order_id)<a href="{{ route('painel.pedidos.show', $ganhador->order_id) }}">abrir dados completos do ganhador</a>@else — @endif</dd></div>
            <div><dt>Publicação</dt><dd>{{ $ganhador->published_at ? 'Publicado em '.hora_local($ganhador->published_at, 'd/m/Y H:i') : 'Ainda não publicado' }}</dd></div>
        </dl>

        @if (! $ganhador->published_at && $dono)
            <form class="grupo" method="post" action="{{ route('painel.apuracao.publicar', $campanha) }}">
                @csrf
                <label class="marcar"><input type="checkbox" name="consentimento" value="1" required> O ganhador foi contatado e autorizou a divulgação do nome abreviado.</label>
                <button class="botao" type="submit">Publicar ganhador no site</button>
            </form>
        @endif
    </section>
@elseif (! $encerrada)
    <p class="aviso aviso--alerta">A apuração só pode ser registrada depois de encerrar as vendas da campanha.</p>
@else
    <section class="cartao">
        <h2>1. Conferir o número</h2>
        <p class="pequeno">Critério do regulamento: {{ $campanha->draw_criterion ?? 'não preenchido' }}</p>
        <form class="filtros" method="get" action="{{ route('painel.apuracao', $campanha) }}">
            <div class="campo">
                <label for="resultado">Resultado oficial</label>
                <input id="resultado" name="resultado" type="text" maxlength="120" value="{{ $resultado }}" placeholder="Ex.: Loteria Federal, extração 0000, 1º prêmio 00000" required>
            </div>
            <div class="campo">
                <label for="numero">Número contemplado</label>
                <input id="numero" name="numero" type="number" min="0" max="{{ $campanha->total_numbers - 1 }}" value="{{ $numero }}" required>
            </div>
            <button class="botao botao--linha" type="submit">Conferir</button>
        </form>
    </section>

    @if ($numero !== null)
        <section class="cartao">
            <h2>2. Confirmar o ganhador</h2>
            @if ($ticket && $ticket->order->status === Order::PAGO)
                @php $c = $ticket->order->customer; @endphp
                <dl class="pares">
                    <div><dt>Número</dt><dd><strong>{{ $campanha->formatNumber($numero) }}</strong></dd></div>
                    <div><dt>Nome completo</dt><dd>{{ $c->name }}</dd></div>
                    <div><dt>Telefone</dt><dd>{{ preg_replace('/^(\d{2})(\d{4,5})(\d{4})$/', '($1) $2-$3', $c->phone) }}</dd></div>
                    <div><dt>Pedido</dt><dd><a href="{{ route('painel.pedidos.show', $ticket->order) }}"><code>{{ $ticket->order->code }}</code></a>, pago em {{ hora_local($ticket->order->paid_at, 'd/m/Y H:i') }}</dd></div>
                    <div><dt>Nome que vai para o site</dt><dd>{{ $c->publicName() }}</dd></div>
                </dl>
                @if ($dono)
                    <form class="grupo" method="post" action="{{ route('painel.apuracao.registrar', $campanha) }}" data-confirma="Registrar o número {{ $campanha->formatNumber($numero) }} como contemplado? A campanha passa para Apurada e isso não pode ser desfeito pelo painel.">
                        @csrf
                        <input type="hidden" name="numero" value="{{ $numero }}">
                        <input type="hidden" name="resultado" value="{{ $resultado }}">
                        <label class="marcar"><input type="checkbox" name="confirmo" value="1" required> Conferi o resultado oficial e o critério do regulamento.</label>
                        <button class="botao" type="submit">Registrar apuração</button>
                    </form>
                @else
                    <p class="pequeno">Só o perfil Dono registra a apuração.</p>
                @endif
            @else
                <p class="aviso aviso--alerta">Nenhum pedido pago tem o número {{ $campanha->formatNumber($numero) }}. Confira no regulamento qual número vale nesse caso e faça a conferência de novo.</p>
            @endif
        </section>
    @endif
@endif
@endsection
