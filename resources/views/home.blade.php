@extends('layouts.app')

@section('conteudo')
<div class="pagina" id="campanhas">
    <header class="cabeca">
        <p class="rotulo">Nassa Garage</p>
        <h1>Campanhas</h1>
    </header>

    @forelse ($ativas as $c)
        <a class="destaque" href="{{ route('campanha', $c) }}">
            <div class="destaque__foto">
                @include('partials.foto', ['image' => $c->images->first(), 'eager' => $loop->first])
                @include('partials.estado', ['state' => $c->publicState()])
            </div>
            <div class="destaque__corpo">
                <h2>{{ $c->title }}</h2>
                @if ($c->subtitle)<p>{{ $c->subtitle }}</p>@endif
                <dl class="destaque__dados">
                    <div><dt>Preço</dt><dd>{{ brl($c->price_cents) }}</dd></div>
                    <div><dt>Vendido</dt><dd>{{ number_format($c->percentSold(), 2, ',', '.') }}%</dd></div>
                    @if ($c->draw_at)<div><dt>Apuração</dt><dd>{{ hora_local($c->draw_at, 'd/m') }}</dd></div>@endif
                </dl>
                <span class="destaque__ir">Ver campanha @include('partials.icon', ['name' => 'arrow', 'size' => 18])</span>
            </div>
        </a>
    @empty
        <div class="vazio">
            <h2>Nenhuma campanha aberta agora</h2>
            <p>A próxima campanha aparece aqui assim que for publicada.</p>
        </div>
    @endforelse

    @if ($emBreve->isNotEmpty())
        <section class="bloco">
            <h2>Em breve</h2>
            <ul class="linhas">
                @foreach ($emBreve as $c)
                    @include('partials.linha-campanha', ['c' => $c])
                @endforeach
            </ul>
        </section>
    @endif

    @if ($encerradas->isNotEmpty())
        <section class="bloco">
            <h2>Encerradas</h2>
            <ul class="linhas">
                @foreach ($encerradas as $c)
                    @include('partials.linha-campanha', ['c' => $c])
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection
