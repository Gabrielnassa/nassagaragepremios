@extends('layouts.app')

@section('conteudo')
<div class="pagina">
    <section id="campanhas" aria-labelledby="titulo-campanhas">
        <header class="cabeca">
            <h1 id="titulo-campanhas">Campanhas</h1>
            <p>Escolha a quantidade, pague por Pix e acompanhe a apuração.</p>
        </header>

        @forelse ($ativas as $c)
            @php $estado = $c->publicState(); @endphp
            <a class="destaque" href="{{ route('campanha', $c) }}">
                <div class="destaque__foto">
                    @include('partials.foto', ['image' => $c->images->first(), 'eager' => $loop->first])
                </div>
                <div class="destaque__sobre">
                    @include('partials.estado', ['state' => $estado])
                    <h2>{{ $c->title }}</h2>
                    @if ($c->subtitle)<p>{{ $c->subtitle }}</p>@endif
                </div>
                <div class="destaque__corpo">
                    <div class="destaque__numeros">
                        <p class="preco"><small>Por apenas</small><strong class="num">{{ brl($c->price_cents) }}</strong><small>{{ termo('por_unidade') }}</small></p>
                        @if ($c->draw_at)
                            <p class="destaque__data"><small>Apuração</small><strong class="num">{{ hora_local($c->draw_at, 'd/m') }}</strong></p>
                        @endif
                    </div>
                    <div class="medidor">
                        <div class="medidor__texto"><b class="num">{{ number_format($c->percentSold(), 2, ',', '.') }}%</b> vendido</div>
                        @include('partials.luzes', ['percent' => $c->percentSold()])
                    </div>
                    <span class="cta">
                        <span class="cta__seta">@include('partials.icon', ['name' => 'arrow', 'size' => 20])</span>
                        <span class="cta__texto"><b>{{ $estado === 'ativa' ? termo('garantir') : 'Ver campanha' }}</b><small>Pagamento por Pix</small></span>
                    </span>
                </div>
            </a>
        @empty
            <div class="vazio">
                @include('partials.icon', ['name' => 'flag', 'size' => 28])
                <h2>Nenhuma campanha aberta agora</h2>
                <p>A próxima campanha aparece aqui assim que for publicada.</p>
            </div>
        @endforelse
    </section>

    <section class="bloco" id="como-funciona" aria-labelledby="titulo-como">
        <h2 id="titulo-como">Como funciona</h2>
        <ol class="passos">
            <li>
                <span class="passos__n num" aria-hidden="true">1</span>
                <h3>Escolha a quantidade</h3>
                <p>Você define quantos {{ termo('plural') }} quer. Não precisa criar conta com senha.</p>
            </li>
            <li>
                <span class="passos__n num" aria-hidden="true">2</span>
                <h3>Pague por Pix</h3>
                <p>O pedido fica reservado por alguns minutos enquanto você paga pelo QR code ou pelo copia e cola.</p>
            </li>
            <li>
                <span class="passos__n num" aria-hidden="true">3</span>
                <h3>Receba seus {{ termo('plural') }}</h3>
                <p>Com o pagamento confirmado, os {{ termo('plural') }} são atribuídos de forma aleatória e ficam em {{ termo('meus') }}.</p>
            </li>
        </ol>
    </section>

    @if ($ganhadores->isNotEmpty())
        <section class="bloco" aria-labelledby="titulo-ganhadores">
            <h2 id="titulo-ganhadores">Ganhadores</h2>
            <ul class="ganhadores">
                @foreach ($ganhadores as $g)
                    <li>
                        <a class="ganhador" href="{{ route('campanha', $g->campaign) }}">
                            <span class="ganhador__iniciais" aria-hidden="true">{{ mb_substr($g->display_name, 0, 1) }}{{ mb_substr(mb_strrchr($g->display_name, ' ') ?: '', 1, 1) }}</span>
                            <span class="ganhador__corpo">
                                <strong>{{ $g->display_name }}</strong>
                                <span>{{ $g->prize }}</span>
                                <span class="ganhador__linha">@include('partials.placa', ['numero' => $g->campaign->formatNumber($g->number), 'tipo' => 'ouro']) {{ hora_local($g->awarded_on->toImmutable()->setTime(12, 0), 'd/m/Y') }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($emBreve->isNotEmpty())
        <section class="bloco" aria-labelledby="titulo-breve">
            <h2 id="titulo-breve">Em breve</h2>
            <ul class="linhas">
                @foreach ($emBreve as $c)
                    @include('partials.linha-campanha', ['c' => $c])
                @endforeach
            </ul>
        </section>
    @endif

    @if ($encerradas->isNotEmpty())
        <section class="bloco" aria-labelledby="titulo-encerradas">
            <h2 id="titulo-encerradas">Encerradas</h2>
            <ul class="linhas">
                @foreach ($encerradas as $c)
                    @include('partials.linha-campanha', ['c' => $c])
                @endforeach
            </ul>
        </section>
    @endif

    <a class="atalho-consulta" href="{{ route('consulta') }}">
        <span class="atalho-consulta__icone">@include('partials.icon', ['name' => 'ticket', 'size' => 24])</span>
        <span><strong>Já comprou?</strong><span>Consulte seus {{ termo('plural') }} pelo CPF.</span></span>
        @include('partials.icon', ['name' => 'arrow', 'size' => 20])
    </a>
</div>
@endsection
