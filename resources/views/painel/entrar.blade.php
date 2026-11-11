@extends('layouts.painel')

@section('titulo', 'Entrar')

@section('conteudo')
<form class="cartao cartao--acesso" method="post" action="{{ route('painel.entrar') }}">
    @csrf
    <h1>Painel Nassa Garage</h1>
    <label for="email">E-mail</label>
    <input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required autofocus>
    <label for="senha">Senha</label>
    <input id="senha" name="senha" type="password" autocomplete="current-password" required>
    <button class="botao" type="submit">Entrar</button>
</form>
@endsection
