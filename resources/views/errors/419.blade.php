@extends('layouts.app')

@section('titulo', 'Página expirada')

@section('conteudo')
<div class="pagina pagina--erro">
    <p class="erro__codigo num">419</p>
    <h1>Esta página ficou aberta por muito tempo</h1>
    <p>Por segurança, o formulário expirou. Volte e envie de novo.</p>
    <a class="cta cta--simples" href="{{ url()->previous(route('home')) }}">Voltar @include('partials.icon', ['name' => 'arrow', 'size' => 20])</a>
</div>
@endsection
