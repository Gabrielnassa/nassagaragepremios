@extends('layouts.app')

@section('titulo', 'Página não encontrada')

@section('conteudo')
<div class="pagina pagina--erro">
    <p class="erro__codigo num">404</p>
    <h1>Esta página não existe</h1>
    <p>O endereço pode ter mudado ou a campanha pode ter saído do ar.</p>
    <a class="cta cta--simples" href="{{ route('home') }}">Ver campanhas @include('partials.icon', ['name' => 'arrow', 'size' => 20])</a>
</div>
@endsection
