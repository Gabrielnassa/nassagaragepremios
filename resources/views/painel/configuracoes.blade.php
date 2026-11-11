@extends('layouts.painel')

@section('titulo', 'Configurações')

@section('conteudo')
<h1>Configurações</h1>

<form method="post" action="{{ route('painel.configuracoes') }}">
    @csrf
    @method('PUT')

    <section class="cartao">
        <h2>Canais</h2>
        <div class="campos">
            <div class="campo">
                <label for="whatsapp_suporte">WhatsApp de suporte</label>
                <input id="whatsapp_suporte" name="whatsapp_suporte" type="tel" value="{{ old('whatsapp_suporte', $valores['whatsapp_suporte']) }}" placeholder="(11) 90000-0000">
                <p class="pequeno">Com DDD. Aparece no botão Suporte do site.</p>
            </div>
            <div class="campo">
                <label for="whatsapp_grupo">Link do grupo no WhatsApp</label>
                <input id="whatsapp_grupo" name="whatsapp_grupo" type="url" value="{{ old('whatsapp_grupo', $valores['whatsapp_grupo']) }}" placeholder="https://chat.whatsapp.com/...">
            </div>
            <div class="campo">
                <label for="instagram">Instagram</label>
                <input id="instagram" name="instagram" type="text" value="{{ old('instagram', $valores['instagram']) }}" placeholder="nssgrg">
            </div>
        </div>
    </section>

    <section class="cartao">
        <h2>Identificação no rodapé</h2>
        <div class="campos">
            <div class="campo">
                <label for="legal_company">Razão social</label>
                <input id="legal_company" name="legal_company" type="text" maxlength="160" value="{{ old('legal_company', $valores['legal_company']) }}">
            </div>
            <div class="campo">
                <label for="legal_cnpj">CNPJ</label>
                <input id="legal_cnpj" name="legal_cnpj" type="text" inputmode="numeric" value="{{ old('legal_cnpj', $valores['legal_cnpj']) }}">
            </div>
        </div>
    </section>

    <section class="cartao">
        <h2>Vocabulário do site</h2>
        <p class="pequeno">Use "títulos" se a modalidade for título de capitalização. Nos demais casos, "números".</p>
        <div class="linha-acoes">
            <label class="marcar"><input type="radio" name="vocabulario" value="numeros" @checked(old('vocabulario', $valores['vocabulario'] ?? 'numeros') === 'numeros')> Números</label>
            <label class="marcar"><input type="radio" name="vocabulario" value="titulos" @checked(old('vocabulario', $valores['vocabulario']) === 'titulos')> Títulos</label>
        </div>
    </section>

    <div class="rodape-form">
        <button class="botao" type="submit">Salvar configurações</button>
    </div>
</form>
@endsection
