@extends('layouts.painel')

@section('titulo', 'Verificação em duas etapas')

@if ($configurar)
    @push('scripts')
        <script src="{{ recurso('assets/js/vendor/qrcode.js') }}" defer></script>
    @endpush
@endif

@section('conteudo')
<form class="cartao cartao--acesso" method="post" action="{{ route('painel.verificacao') }}">
    @csrf
    <h1>Verificação em duas etapas</h1>
    @if ($configurar)
        <p>Abra um aplicativo autenticador (Google Authenticator, Authy ou 1Password), leia o QR code e digite o código de 6 dígitos que ele mostrar.</p>
        <div class="qr"><canvas data-qr="{{ $uri }}" width="200" height="200" role="img" aria-label="QR code para o aplicativo autenticador"></canvas></div>
        <p class="pequeno">Se não conseguir ler, digite esta chave no aplicativo: <code>{{ trim(chunk_split($segredo, 4, ' ')) }}</code></p>
    @else
        <p>Digite o código de 6 dígitos do seu aplicativo autenticador.</p>
    @endif
    <label for="codigo">Código</label>
    <input id="codigo" name="codigo" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus>
    <button class="botao" type="submit">Confirmar</button>
</form>
<form class="fora__sair" method="post" action="{{ route('painel.sair') }}">
    @csrf
    <button type="submit">Sair e entrar com outra conta</button>
</form>
@endsection
