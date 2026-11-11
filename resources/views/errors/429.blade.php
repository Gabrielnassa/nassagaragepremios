@extends('layouts.app')

@section('titulo', 'Muitas tentativas')

@section('conteudo')
<div class="pagina pagina--erro">
    <p class="erro__codigo num">429</p>
    <h1>Muitas tentativas seguidas</h1>
    <p>Espere um minuto e tente de novo. O limite existe para proteger os dados de quem comprou.</p>
    <a class="cta cta--simples" href="{{ route('home') }}">Voltar ao início @include('partials.icon', ['name' => 'arrow', 'size' => 20])</a>
</div>
@endsection
