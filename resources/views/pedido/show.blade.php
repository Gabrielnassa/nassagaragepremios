@extends('layouts.app')

@php
    use App\Models\Order;
    $rotuloQtd = $order->quantity.' '.($order->quantity === 1 ? termo('singular') : termo('plural'));
@endphp

@section('titulo', 'Pedido '.$order->code)
@section('sem_abas', '1')

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@push('fim')
    @if ($order->status === Order::PENDENTE)
        <script src="{{ recurso('assets/js/vendor/qrcode.js') }}" defer></script>
        <script src="{{ recurso('assets/js/pedido.js') }}" defer></script>
    @endif
@endpush

@section('conteudo')
<div class="pagina pagina--estreita pedido-pagina">
    <header class="pedido-topo">
        <p class="rotulo">Pedido {{ $order->code }}</p>
        <h1>
            @switch($order->status)
                @case(Order::PENDENTE) Pague com Pix @break
                @case(Order::PAGO) Pagamento confirmado @break
                @case(Order::ESTORNADO) Pagamento devolvido @break
                @default Pedido encerrado
            @endswitch
        </h1>
        <p>{{ $campaign->title }} · {{ $rotuloQtd }} · <strong class="num">{{ brl($order->amount_cents) }}</strong></p>
    </header>

    @if ($order->status === Order::PENDENTE && $payment)
        <section class="pix" data-pix
            data-status="{{ route('pedido.status', $order->code) }}"
            data-expira="{{ $order->expires_at->toIso8601String() }}"
            aria-label="Pagamento por Pix">
            <div class="pix__prazo">
                <span>@include('partials.icon', ['name' => 'clock', 'size' => 18]) Reserva válida por</span>
                <b class="num" data-prazo role="timer">{{ gmdate('i:s', max(0, $order->expires_at->getTimestamp() - time())) }}</b>
            </div>

            <div class="pix__qr">
                <canvas data-qr width="240" height="240" role="img" aria-label="QR code do Pix"></canvas>
            </div>

            <div class="campo">
                <label for="copia-cola">Pix copia e cola</label>
                <textarea id="copia-cola" rows="3" readonly data-codigo>{{ $payment->pix_payload }}</textarea>
            </div>
            <button class="cta cta--simples" type="button" data-copiar>@include('partials.icon', ['name' => 'file', 'size' => 20]) <span data-copiar-rotulo>Copiar código</span></button>

            <ol class="pix__passos">
                <li><span>Abra o app do seu banco e escolha pagar com Pix.</span></li>
                <li><span>Aponte a câmera para o QR code ou cole o código copiado.</span></li>
                <li><span>Confira o valor de <strong class="num">{{ brl($order->amount_cents) }}</strong> e confirme.</span></li>
            </ol>

            <p class="pix__aguardando" role="status">@include('partials.icon', ['name' => 'clock', 'size' => 16]) Aguardando o pagamento. Esta página atualiza sozinha quando ele for confirmado.</p>
        </section>

        @if ($simulado)
            <form class="simulacao" method="post" action="{{ route('pedido.simular', $order->code) }}">
                @csrf
                <p><strong>Ambiente de teste.</strong> Este Pix não é real e não deve ser pago. Use o botão para simular a confirmação do banco.</p>
                <button type="submit">Simular pagamento confirmado</button>
            </form>
        @endif
    @elseif ($order->status === Order::PAGO)
        <section class="confirmado">
            <p class="confirmado__selo">@include('partials.icon', ['name' => 'check', 'size' => 22]) Pago em {{ hora_local($order->paid_at, 'd/m/Y \à\s H\hi') }}</p>

            @if ($premios->isNotEmpty())
                <div class="premio-instantaneo">
                    <p class="rotulo rotulo--alerta">@include('partials.icon', ['name' => 'trophy', 'size' => 16]) Você tirou um {{ termo('singular') }} premiado</p>
                    @foreach ($premios as $premio)
                        <p>@include('partials.placa', ['numero' => $campaign->formatNumber($premio->number), 'tipo' => 'ouro']) <strong>{{ $premio->prize }}</strong></p>
                    @endforeach
                    <p class="dica">Nossa equipe entra em contato pelo telefone do cadastro. Não cobramos nada para liberar prêmio.</p>
                </div>
            @endif

            <h2>{{ $order->quantity === 1 ? 'Seu '.termo('singular') : 'Seus '.termo('plural') }}</h2>
            <ul class="placas">
                @foreach ($order->tickets as $ticket)
                    <li>@include('partials.placa', ['numero' => $campaign->formatNumber($ticket->number), 'tipo' => $premios->contains('number', $ticket->number) ? 'ouro' : ''])</li>
                @endforeach
            </ul>
            <p class="dica">Em nome de {{ $order->customer->publicName() }}. Guarde esta página ou consulte quando quiser em {{ termo('meus') }}.</p>
        </section>

        <div class="pedido-acoes">
            <a class="cta cta--simples" href="{{ route('consulta') }}">@include('partials.icon', ['name' => 'ticket', 'size' => 20]) {{ termo('meus') }}</a>
            <a class="botao-linha" href="{{ route('campanha', $campaign) }}">Voltar à campanha</a>
        </div>
    @elseif ($order->status === Order::ESTORNADO)
        <div class="situacao">
            <p><strong>O valor deste pedido foi devolvido.</strong></p>
            <p>{{ $order->close_reason ?? 'O pagamento não pôde ser aproveitado.' }} A devolução volta para a mesma conta do Pix.</p>
        </div>
        <div class="pedido-acoes">
            <a class="cta cta--simples" href="{{ route('campanha', $campaign) }}">Voltar à campanha @include('partials.icon', ['name' => 'arrow', 'size' => 20])</a>
        </div>
    @else
        <div class="situacao">
            <p><strong>{{ $order->status === Order::EXPIRADO ? 'O prazo para pagar este pedido acabou.' : 'Este pedido foi cancelado.' }}</strong></p>
            <p>Nada foi cobrado e a quantidade voltou para o estoque. Se você já pagou este Pix, o pagamento é conferido automaticamente; aguarde alguns minutos e recarregue a página.</p>
        </div>
        <div class="pedido-acoes">
            <a class="cta cta--simples" href="{{ route('campanha', $campaign) }}">Fazer novo pedido @include('partials.icon', ['name' => 'arrow', 'size' => 20])</a>
        </div>
    @endif

    @include('partials.golpes')
</div>
@endsection
