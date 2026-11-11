@php $eu = auth()->user(); @endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Painel') · Painel {{ config('app.name') }}</title>
    <link rel="icon" href="{{ recurso('assets/img/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ recurso('assets/css/painel.css') }}">
    <script src="{{ recurso('assets/js/painel.js') }}" defer></script>
    @stack('scripts')
</head>
<body @class(['fora' => empty($eu) || ! session('painel.2fa')])>
@if ($eu && session('painel.2fa'))
    <header class="barra">
        <a class="barra__marca" href="{{ route('painel.inicio') }}">Nassa Garage <span>Painel</span></a>
        <nav class="barra__nav" aria-label="Painel">
            <a href="{{ route('painel.inicio') }}" @if (request()->routeIs('painel.inicio')) aria-current="page" @endif>Visão geral</a>
            <a href="{{ route('painel.campanhas.index') }}" @if (request()->routeIs('painel.campanhas.*', 'painel.apuracao*')) aria-current="page" @endif>Campanhas</a>
            <a href="{{ route('painel.pedidos.index') }}" @if (request()->routeIs('painel.pedidos.*')) aria-current="page" @endif>Pedidos</a>
            @if ($eu->pode('dono'))
                <a href="{{ route('painel.configuracoes') }}" @if (request()->routeIs('painel.configuracoes')) aria-current="page" @endif>Configurações</a>
            @endif
        </nav>
        <form class="barra__sair" method="post" action="{{ route('painel.sair') }}">
            @csrf
            <span>{{ $eu->name }} · {{ \App\Models\AdminUser::PAPEIS[$eu->role] ?? $eu->role }}</span>
            <button type="submit">Sair</button>
        </form>
    </header>
@endif

<main class="conteudo">
    @if (session('ok'))<p class="aviso aviso--ok" role="status">{{ session('ok') }}</p>@endif
    @if (session('erro'))<p class="aviso aviso--erro" role="alert">{{ session('erro') }}</p>@endif
    @if ($errors->any())
        <div class="aviso aviso--erro" role="alert">
            <strong>Confira os campos:</strong>
            <ul>@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('conteudo')
</main>
</body>
</html>
