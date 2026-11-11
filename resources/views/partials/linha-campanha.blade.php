<li>
    <a class="linha" href="{{ route('campanha', $c) }}">
        <span class="linha__foto">@include('partials.foto', ['image' => $c->images->first()])</span>
        <span class="linha__corpo">
            @include('partials.estado', ['state' => $c->publicState()])
            <strong>{{ $c->title }}</strong>
            @if ($c->status === \App\Models\Campaign::EM_BREVE && $c->starts_at)
                <span>Abre em {{ hora_local($c->starts_at, 'd/m \à\s H\hi') }}</span>
            @elseif ($c->mainWinner)
                <span>{{ termo('contemplado') }} <b class="num">{{ $c->formatNumber($c->mainWinner->number) }}</b> · {{ $c->mainWinner->display_name }}</span>
            @elseif ($c->draw_at)
                <span>Apuração em {{ hora_local($c->draw_at) }}</span>
            @endif
        </span>
        @include('partials.icon', ['name' => 'right', 'size' => 18])
    </a>
</li>
