@extends('layouts.painel')

@php
    use App\Http\Controllers\Painel\CampanhaController as C;
    use App\Models\Campaign;
    $nova = ! $campanha->exists;
    $travada = ! $nova && ! in_array($campanha->status, [Campaign::RASCUNHO, Campaign::EM_BREVE], true);
    $pacotes = $campanha->packages ?: [];
    $dataLocal = fn ($d) => $d ? $d->copy()->setTimezone('America/Sao_Paulo')->format('Y-m-d\TH:i') : '';
    $faltando = $nova ? [] : $campanha->legalMissing();
    $dono = auth()->user()->pode('dono');
@endphp

@section('titulo', $nova ? 'Nova campanha' : $campanha->title)

@section('conteudo')
<div class="cabecalho">
    <div>
        <p class="pequeno"><a href="{{ route('painel.campanhas.index') }}">Campanhas</a></p>
        <h1>{{ $nova ? 'Nova campanha' : $campanha->title }}</h1>
    </div>
    @unless ($nova)
        <div class="cabecalho__acoes">
            <span class="etiqueta etiqueta--{{ $campanha->status }}">{{ C::ROTULOS[$campanha->status] }}</span>
            @if ($campanha->status !== Campaign::RASCUNHO)
                <a class="botao botao--linha" href="{{ route('campanha', $campanha) }}">Ver no site</a>
            @endif
        </div>
    @endunless
</div>

@unless ($nova)
    <section class="cartao">
        <h2>Status</h2>
        <dl class="pares pares--linha">
            <div><dt>Vendidos</dt><dd>{{ number_format($campanha->sold_count, 0, ',', '.') }} de {{ number_format($campanha->total_numbers, 0, ',', '.') }} ({{ number_format($campanha->percentSold(), 2, ',', '.') }}%)</dd></div>
            <div><dt>Em reserva</dt><dd>{{ number_format($campanha->reserved_count, 0, ',', '.') }}</dd></div>
            @if ($campanha->winning_number !== null)<div><dt>Número contemplado</dt><dd>{{ $campanha->formatNumber($campanha->winning_number) }}</dd></div>@endif
        </dl>

        @if ($faltando && in_array(Campaign::ATIVA, C::TRANSICOES[$campanha->status], true))
            <p class="aviso aviso--alerta">Para ativar, ainda falta preencher: {{ implode(', ', $faltando) }}.</p>
        @endif

        @if ($dono)
            <div class="linha-acoes">
                @foreach (C::TRANSICOES[$campanha->status] as $destino)
                    <form method="post" action="{{ route('painel.campanhas.status', $campanha) }}"
                        @if ($destino === Campaign::ENCERRADA) data-confirma="Encerrar as vendas desta campanha? Os pedidos que aguardam pagamento serão cancelados e a venda não pode ser reaberta." @endif>
                        @csrf
                        <input type="hidden" name="status" value="{{ $destino }}">
                        <button class="botao {{ $destino === Campaign::ATIVA ? '' : 'botao--linha' }}" type="submit">
                            {{ ['em_breve' => 'Anunciar como "Em breve"', 'ativa' => 'Ativar vendas', 'pausada' => 'Pausar vendas', 'encerrada' => 'Encerrar vendas', 'rascunho' => 'Voltar para rascunho'][$destino] }}
                        </button>
                    </form>
                @endforeach
                @if (in_array($campanha->status, [Campaign::ENCERRADA, Campaign::APURADA], true))
                    <a class="botao" href="{{ route('painel.apuracao', $campanha) }}">{{ $campanha->status === Campaign::ENCERRADA ? 'Registrar apuração' : 'Ver apuração' }}</a>
                @endif
            </div>
        @else
            <p class="pequeno">Só o perfil Dono muda o status da campanha.</p>
        @endif
    </section>
@endunless

<form method="post" enctype="multipart/form-data" action="{{ $nova ? route('painel.campanhas.store') : route('painel.campanhas.update', $campanha) }}">
    @csrf
    @unless ($nova) @method('PUT') @endunless

    <section class="cartao">
        <h2>Apresentação</h2>
        <div class="campos">
            <div class="campo campo--largo">
                <label for="title">Título</label>
                <input id="title" name="title" type="text" maxlength="120" value="{{ old('title', $campanha->title) }}" required>
            </div>
            <div class="campo campo--largo">
                <label for="slug">Endereço da página</label>
                <input id="slug" name="slug" type="text" maxlength="120" value="{{ old('slug', $campanha->slug) }}" placeholder="gerado a partir do título">
                <p class="pequeno">Só letras minúsculas, números e hífen. Aparece em /campanha/endereco.</p>
            </div>
            <div class="campo campo--largo">
                <label for="subtitle">Subtítulo</label>
                <input id="subtitle" name="subtitle" type="text" maxlength="200" value="{{ old('subtitle', $campanha->subtitle) }}">
            </div>
            <div class="campo campo--largo">
                <label for="description">Sobre o prêmio</label>
                <textarea id="description" name="description" rows="6">{{ old('description', $campanha->description) }}</textarea>
                <p class="pequeno">Deixe uma linha em branco entre os parágrafos.</p>
            </div>
        </div>
    </section>

    <section class="cartao">
        <h2>Venda</h2>
        @if ($travada)
            <p class="aviso aviso--alerta">Preço e total de números não mudam depois que a campanha é ativada.</p>
        @endif
        <div class="campos">
            <div class="campo">
                <label for="price">Preço por número (R$)</label>
                <input id="price" name="price" type="text" inputmode="decimal" value="{{ old('price', $campanha->price_cents ? number_format($campanha->price_cents / 100, 2, ',', '') : '') }}" @disabled($travada) required>
            </div>
            <div class="campo">
                <label for="total_numbers">Total de números</label>
                <input id="total_numbers" name="total_numbers" type="number" min="10" value="{{ old('total_numbers', $campanha->total_numbers) }}" @disabled($travada) required>
                <p class="pequeno">Com 10.000, os números vão de 0000 a 9999.</p>
            </div>
            <div class="campo">
                <label for="min_per_order">Mínimo por pedido</label>
                <input id="min_per_order" name="min_per_order" type="number" min="1" value="{{ old('min_per_order', $campanha->min_per_order) }}" required>
            </div>
            <div class="campo">
                <label for="max_per_order">Máximo por pedido</label>
                <input id="max_per_order" name="max_per_order" type="number" min="1" value="{{ old('max_per_order', $campanha->max_per_order) }}" required>
            </div>
            <div class="campo">
                <label for="reservation_minutes">Prazo para pagar (minutos)</label>
                <input id="reservation_minutes" name="reservation_minutes" type="number" min="5" max="120" value="{{ old('reservation_minutes', $campanha->reservation_minutes) }}" required>
            </div>
            <div class="campo">
                <label for="starts_at">Abertura das vendas</label>
                <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $dataLocal($campanha->starts_at)) }}">
                <p class="pequeno">Informativo, aparece em "Em breve". Horário de Brasília.</p>
            </div>
        </div>

        <fieldset class="grupo">
            <legend>Atalhos de quantidade</legend>
            <p class="pequeno">Até seis botões que somam à quantidade. Marque um como recomendado. Em branco, o site usa +1, +2, +3, +5, +10 e +20.</p>
            <div class="atalhos-form">
                @for ($i = 0; $i < 6; $i++)
                    <div>
                        <label for="pacote-{{ $i }}">Atalho {{ $i + 1 }}</label>
                        <input id="pacote-{{ $i }}" name="packages[{{ $i }}]" type="number" min="1" value="{{ old("packages.$i", $pacotes[$i]['qty'] ?? '') }}">
                        <label class="marcar"><input type="radio" name="featured" value="{{ $i }}" @checked((string) old('featured', collect($pacotes)->search(fn ($p) => ! empty($p['featured']))) === (string) $i)> Recomendado</label>
                    </div>
                @endfor
            </div>
        </fieldset>
    </section>

    <section class="cartao">
        <h2>Fotos</h2>
        <p class="pequeno">Use fotos reais do prêmio, todas na mesma proporção (3:2 fica melhor). JPG, PNG ou WebP de até 8 MB; o sistema reduz e converte.</p>
        @if (! $nova && $campanha->images->isNotEmpty())
            <ul class="fotos">
                @foreach ($campanha->images as $image)
                    <li>
                        <img src="{{ $image->url() }}" alt="" width="180" height="120" loading="lazy">
                        <label for="pos-{{ $image->id }}">Ordem</label>
                        <input id="pos-{{ $image->id }}" name="photo_position[{{ $image->id }}]" type="number" min="0" value="{{ $image->position }}">
                        <label for="alt-{{ $image->id }}">Descrição da foto</label>
                        <input id="alt-{{ $image->id }}" name="photo_alt[{{ $image->id }}]" type="text" maxlength="160" value="{{ $image->alt }}">
                        <label class="marcar"><input type="checkbox" name="photo_remove[{{ $image->id }}]" value="1"> Remover</label>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="campo campo--largo">
            <label for="photos">Adicionar fotos</label>
            <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
        </div>
    </section>

    <section class="cartao">
        <h2>Prêmios</h2>
        <p class="pequeno">Na ordem: 1º prêmio, 2º prêmio e assim por diante. Linhas em branco são ignoradas.</p>
        @php $premios = $nova ? collect() : $campanha->prizes; @endphp
        @for ($i = 0; $i < max(3, $premios->count() + 1); $i++)
            <div class="campos campos--premio">
                <div class="campo">
                    <label for="premio-{{ $i }}">{{ $i + 1 }}º prêmio</label>
                    <input id="premio-{{ $i }}" name="prize_title[{{ $i }}]" type="text" maxlength="160" value="{{ old("prize_title.$i", $premios[$i]->title ?? '') }}">
                </div>
                <div class="campo">
                    <label for="premio-desc-{{ $i }}">Detalhe</label>
                    <input id="premio-desc-{{ $i }}" name="prize_description[{{ $i }}]" type="text" maxlength="300" value="{{ old("prize_description.$i", $premios[$i]->description ?? '') }}">
                </div>
            </div>
        @endfor
    </section>

    <section class="cartao">
        <h2>Apuração</h2>
        <div class="campos">
            <div class="campo">
                <label for="draw_at">Data e hora da apuração</label>
                <input id="draw_at" name="draw_at" type="datetime-local" value="{{ old('draw_at', $dataLocal($campanha->draw_at)) }}">
            </div>
            <div class="campo">
                <label for="delivery_deadline">Prazo de entrega do prêmio</label>
                <input id="delivery_deadline" name="delivery_deadline" type="text" maxlength="200" value="{{ old('delivery_deadline', $campanha->delivery_deadline) }}">
            </div>
            <div class="campo campo--largo">
                <label for="draw_criterion">Critério de apuração</label>
                <textarea id="draw_criterion" name="draw_criterion" rows="3">{{ old('draw_criterion', $campanha->draw_criterion) }}</textarea>
                <p class="pequeno">Copie do regulamento aprovado. O sistema não sorteia o prêmio principal: você registra aqui o resultado oficial.</p>
            </div>
        </div>
    </section>

    <section class="cartao">
        <h2>Responsável e regulamento</h2>
        <p class="pequeno">Dados reais da autorização. Sem eles a campanha não pode ser ativada.</p>
        <div class="campos">
            <div class="campo">
                <label for="legal_company">Razão social do responsável</label>
                <input id="legal_company" name="legal_company" type="text" maxlength="160" value="{{ old('legal_company', $campanha->legal_company) }}">
            </div>
            <div class="campo">
                <label for="legal_cnpj">CNPJ do responsável</label>
                <input id="legal_cnpj" name="legal_cnpj" type="text" inputmode="numeric" maxlength="18" value="{{ old('legal_cnpj', $campanha->formattedCnpj()) }}">
            </div>
            <div class="campo">
                <label for="legal_modality">Modalidade</label>
                <select id="legal_modality" name="legal_modality">
                    <option value="">Escolha</option>
                    @foreach (Campaign::MODALIDADES as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('legal_modality', $campanha->legal_modality) === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="campo">
                <label for="legal_process">Número do processo ou certificado</label>
                <input id="legal_process" name="legal_process" type="text" maxlength="80" value="{{ old('legal_process', $campanha->legal_process) }}">
            </div>
            <div class="campo">
                <label for="legal_capitalization_company">Sociedade de capitalização</label>
                <input id="legal_capitalization_company" name="legal_capitalization_company" type="text" maxlength="160" value="{{ old('legal_capitalization_company', $campanha->legal_capitalization_company) }}">
                <p class="pequeno">Obrigatório na modalidade de capitalização.</p>
            </div>
            <div class="campo">
                <label for="legal_beneficiary">Entidade beneficiada</label>
                <input id="legal_beneficiary" name="legal_beneficiary" type="text" maxlength="160" value="{{ old('legal_beneficiary', $campanha->legal_beneficiary) }}">
                <p class="pequeno">Obrigatório em capitalização e sorteio filantrópico.</p>
            </div>
            <div class="campo campo--largo">
                <label for="regulation">Regulamento em PDF</label>
                <input id="regulation" name="regulation" type="file" accept="application/pdf">
                @if ($campanha->legal_regulation_path)
                    <p class="pequeno">Arquivo atual: <a href="{{ Storage::disk('public')->url($campanha->legal_regulation_path) }}">abrir regulamento</a>. Envie outro para substituir.</p>
                @endif
            </div>
        </div>
    </section>

    <div class="rodape-form">
        <button class="botao" type="submit">{{ $nova ? 'Criar campanha' : 'Salvar alterações' }}</button>
        <a class="botao botao--linha" href="{{ route('painel.campanhas.index') }}">Cancelar</a>
    </div>
</form>
@endsection
