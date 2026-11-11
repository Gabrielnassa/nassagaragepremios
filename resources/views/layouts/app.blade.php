@php
    use App\Models\Setting;
    $marca = config('app.name');
    $whatsapp = Setting::get('whatsapp_suporte');
    $instagram = Setting::get('instagram', 'nssgrg');
    $responsavel = Setting::get('legal_company');
    $cnpj = Setting::get('legal_cnpj');
    $naCampanha = request()->routeIs('home', 'campanha');
    $naConsulta = request()->routeIs('consulta', 'consulta.buscar');
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('titulo')@yield('titulo') · {{ $marca }}@else{{ $marca }}@endif</title>
    <meta name="description" content="@yield('descricao', 'Campanhas da Nassa Garage: escolha a quantidade, pague por Pix e acompanhe a apuração.')">
    <meta name="theme-color" content="#050607">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta property="og:site_name" content="{{ $marca }}">
    <meta property="og:title" content="@yield('titulo', $marca)">
    <meta property="og:type" content="website">
    @stack('meta')
    @unless (app()->isProduction())
        <meta name="robots" content="noindex, nofollow">
    @endunless
    <link rel="icon" href="{{ recurso('assets/img/favicon.svg') }}" type="image/svg+xml">
    <link rel="preload" href="{{ asset('assets/fonts/saira-condensed-800.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('assets/fonts/saira-400.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ recurso('assets/css/site.css') }}">
    <script src="{{ recurso('assets/js/site.js') }}" defer></script>
</head>
<body>
    <a class="pular" href="#conteudo">Pular para o conteúdo</a>

    @if (! empty($demo))
        <p class="aviso-demo">Ambiente de demonstração. Os dados desta página são fictícios.</p>
    @endif

    <header class="topo">
        <div class="topo__dentro">
            <button class="topo__menu" type="button" data-abre="menu" aria-haspopup="dialog" aria-label="Abrir menu">
                @include('partials.icon', ['name' => 'menu', 'size' => 24])
            </button>
            <a class="marca" href="{{ route('home') }}" aria-label="{{ $marca }}, página inicial">@include('partials.marca')</a>
            <nav class="topo__nav" aria-label="Principal">
                <a href="{{ route('home') }}" @if ($naCampanha) aria-current="page" @endif>Campanhas</a>
                <a href="{{ route('consulta') }}" @if ($naConsulta) aria-current="page" @endif>{{ termo('meus') }}</a>
                <a href="{{ route('home') }}#como-funciona">Como funciona</a>
            </nav>
            @if ($whatsapp)
                <a class="topo__suporte" href="https://wa.me/55{{ $whatsapp }}" rel="noopener">
                    @include('partials.icon', ['name' => 'chat', 'size' => 20])
                    <span>Suporte</span>
                </a>
            @else
                <a class="topo__suporte" href="{{ route('consulta') }}">
                    @include('partials.icon', ['name' => 'ticket', 'size' => 20])
                    <span>Consultar</span>
                </a>
            @endif
        </div>
        @hasSection('sem_abas')
        @else
            <nav class="abas" aria-label="Seções">
                <a class="aba" href="{{ route('home') }}" @if ($naCampanha) aria-current="page" @endif>
                    @include('partials.icon', ['name' => 'flag', 'size' => 18]) Campanhas
                </a>
                <a class="aba" href="{{ route('consulta') }}" @if ($naConsulta) aria-current="page" @endif>
                    @include('partials.icon', ['name' => 'ticket', 'size' => 18]) {{ termo('meus') }}
                </a>
            </nav>
        @endif
    </header>

    <dialog class="menu" id="menu" aria-label="Menu">
        <div class="menu__topo">
            <span class="marca">@include('partials.marca')</span>
            <button class="botao-icone" type="button" data-fecha aria-label="Fechar menu">
                @include('partials.icon', ['name' => 'close'])
            </button>
        </div>
        <nav class="menu__lista" aria-label="Menu">
            <p class="rotulo">Principal</p>
            <a href="{{ route('home') }}">@include('partials.icon', ['name' => 'grid', 'size' => 18]) Início</a>
            <a href="{{ route('home') }}#campanhas">@include('partials.icon', ['name' => 'flag', 'size' => 18]) Campanhas</a>
            <a href="{{ route('consulta') }}">@include('partials.icon', ['name' => 'ticket', 'size' => 18]) {{ termo('meus') }}</a>
            <a href="{{ route('home') }}#como-funciona">@include('partials.icon', ['name' => 'check', 'size' => 18]) Como funciona</a>
            @if ($whatsapp || $instagram)
                <p class="rotulo">Canais oficiais</p>
                @if ($whatsapp)
                    <a href="https://wa.me/55{{ $whatsapp }}" rel="noopener">@include('partials.icon', ['name' => 'chat', 'size' => 18]) Suporte no WhatsApp</a>
                @endif
                @if ($instagram)
                    <a href="https://instagram.com/{{ $instagram }}" rel="noopener">@include('partials.icon', ['name' => 'arrow', 'size' => 18]) Instagram &#64;{{ $instagram }}</a>
                @endif
            @endif
        </nav>
    </dialog>

    <main id="conteudo" class="painel">
        @yield('conteudo')
    </main>

    <footer class="rodape">
        <div class="rodape__dentro">
            <div class="rodape__marca">
                <span class="marca">@include('partials.marca')</span>
                @if ($responsavel)
                    <p>{{ $responsavel }}@if ($cnpj) · CNPJ {{ $cnpj }}@endif</p>
                @endif
            </div>
            <nav class="rodape__links" aria-label="Rodapé">
                <a href="{{ route('home') }}#campanhas">Campanhas</a>
                <a href="{{ route('consulta') }}">{{ termo('meus') }}</a>
                <a href="{{ route('home') }}#como-funciona">Como funciona</a>
                @if ($instagram)<a href="https://instagram.com/{{ $instagram }}" rel="noopener">Instagram</a>@endif
            </nav>
            <p class="rodape__idade"><span class="selo-idade">18+</span> Participação permitida apenas para maiores de 18 anos.</p>
            <div class="rodape__fim">
                <p>© {{ date('Y') }} {{ $marca }}</p>
                <p>Desenvolvido por <strong>NassaTech</strong></p>
            </div>
        </div>
    </footer>

    @stack('fim')
</body>
</html>
