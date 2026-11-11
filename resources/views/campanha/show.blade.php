@extends('layouts.app')

@php
    use App\Models\Campaign;
    $aVenda = $campaign->isOnSale();
    $percentual = $campaign->percentSold();
    $minimo = min($campaign->min_per_order, max(1, $campaign->maxPurchasable()));
    $instantaneos = $campaign->instantPrizes;
    $sairam = $instantaneos->whereNotNull('ticket_id');
    $faltando = $campaign->legalMissing();
    $resto = $promo ? max(0, $promo->ends_at->getTimestamp() - time()) : 0;
    $fotos = $campaign->images;
@endphp

@section('titulo', $campaign->title)
@section('descricao', $campaign->subtitle ?? $campaign->title)
@section('sem_abas', '1')

@push('meta')
    @if ($fotos->first())
        <meta property="og:image" content="{{ $fotos->first()->url() }}">
    @endif
@endpush

@section('conteudo')
<article class="campanha" data-campanha
    data-preco="{{ $campaign->price_cents }}"
    data-min="{{ $minimo }}"
    data-max="{{ $aVenda ? $campaign->maxPurchasable() : 0 }}"
    data-ao-vivo="{{ route('campanha.ao-vivo', $campaign) }}">

    <section class="galeria" aria-label="Fotos do prêmio" data-galeria>
        <div class="galeria__palco">
            <div class="galeria__trilho" data-trilho tabindex="0">
                @forelse ($fotos as $image)
                    <figure class="galeria__slide">@include('partials.foto', ['image' => $image, 'eager' => $loop->first])</figure>
                @empty
                    <figure class="galeria__slide"><span class="sem-foto">Foto em breve</span></figure>
                @endforelse
            </div>
            @if ($fotos->count() > 1)
                <button class="galeria__seta galeria__seta--ant" type="button" data-anterior aria-label="Foto anterior">@include('partials.icon', ['name' => 'left'])</button>
                <button class="galeria__seta galeria__seta--prox" type="button" data-proxima aria-label="Próxima foto">@include('partials.icon', ['name' => 'right'])</button>
                <p class="galeria__conta num" aria-live="polite"><span data-atual>1</span> / {{ $fotos->count() }}</p>
            @endif
        </div>
        @if ($fotos->count() > 1)
            <div class="miniaturas" role="group" aria-label="Escolher foto">
                @foreach ($fotos as $image)
                    <button type="button" data-ir="{{ $loop->index }}" aria-label="Foto {{ $loop->iteration }}" @if ($loop->first) aria-current="true" @endif>
                        <img src="{{ $image->url() }}" alt="" width="120" height="80" loading="lazy" decoding="async">
                    </button>
                @endforeach
            </div>
        @endif
    </section>

    <section class="compra" aria-labelledby="titulo-campanha">
        <header class="compra__cabeca">
            @include('partials.estado', ['state' => $state])
            <h1 id="titulo-campanha">{{ $campaign->title }}</h1>
            @if ($campaign->subtitle)<p>{{ $campaign->subtitle }}</p>@endif
        </header>

        <div class="compra__cartao">
            @if ($state !== 'em_breve')
                <div class="medidor">
                    <div class="medidor__texto">
                        <span><b class="num" data-percentual>{{ number_format($percentual, 2, ',', '.') }}%</b> vendido</span>
                        @if ($state === 'ativa')
                            <span data-restantes>{{ termo('restantes', ['n' => number_format($campaign->remaining(), 0, ',', '.')]) }}</span>
                        @endif
                    </div>
                    @include('partials.luzes', ['percent' => $percentual])
                </div>
            @endif

            @if ($state === 'ativa')
                <p class="preco"><small>Por apenas</small><strong class="num">{{ brl($campaign->price_cents) }}</strong><small>{{ termo('por_unidade') }}</small></p>

                <form class="pedido" data-pedido novalidate>
                    <label class="rotulo" for="quantidade">Quantidade</label>
                    <div class="passo">
                        <button type="button" data-menos aria-label="Diminuir quantidade">@include('partials.icon', ['name' => 'minus'])</button>
                        <input class="num" id="quantidade" name="quantidade" type="text" inputmode="numeric" autocomplete="off"
                            value="{{ $minimo }}" aria-describedby="limites">
                        <button type="button" data-mais aria-label="Aumentar quantidade">@include('partials.icon', ['name' => 'plus'])</button>
                    </div>
                    <div class="atalhos" role="group" aria-label="Somar à quantidade">
                        @foreach ($campaign->shortcuts() as $atalho)
                            <button type="button" @class(['atalho', 'atalho--destaque' => $atalho['featured']]) data-soma="{{ $atalho['qty'] }}">
                                @if ($atalho['featured'])<em>Recomendado</em>@endif
                                <b class="num">+{{ str_pad((string) $atalho['qty'], 2, '0', STR_PAD_LEFT) }}</b>
                                <small>Somar</small>
                            </button>
                        @endforeach
                    </div>
                    <p class="dica" id="limites" data-limites>De {{ $minimo }} a {{ $campaign->maxPurchasable() }} por pedido. Os {{ termo('plural') }} são atribuídos de forma aleatória depois do pagamento.</p>

                    <button class="cta" type="submit" data-cta>
                        <span class="cta__seta">@include('partials.icon', ['name' => 'arrow', 'size' => 20])</span>
                        <span class="cta__texto"><b>{{ termo('garantir') }}</b><small>Pagamento por Pix</small></span>
                        <span class="cta__total"><small data-resumo>{{ $minimo }} × {{ brl($campaign->price_cents) }}</small><b class="num" data-total>{{ brl($minimo * $campaign->price_cents) }}</b></span>
                    </button>
                </form>
            @elseif ($state === 'em_breve')
                <p class="preco"><small>Por apenas</small><strong class="num">{{ brl($campaign->price_cents) }}</strong><small>{{ termo('por_unidade') }}</small></p>
                <div class="situacao">
                    @if ($campaign->starts_at)
                        <p><strong>As vendas abrem em {{ hora_local($campaign->starts_at, 'd/m/Y \à\s H\hi') }}.</strong></p>
                        <p>Volte nesse horário para garantir os seus.</p>
                    @else
                        <p><strong>A data de abertura das vendas será publicada aqui.</strong></p>
                    @endif
                </div>
            @elseif ($state === 'esgotada')
                <div class="situacao">
                    <p><strong>Todos os {{ termo('plural') }} foram vendidos.</strong></p>
                    @if ($campaign->draw_at)<p>A apuração está marcada para {{ hora_local($campaign->draw_at, 'd/m/Y \à\s H\hi') }}.</p>@endif
                </div>
            @elseif ($state === 'pausada')
                <div class="situacao">
                    <p><strong>As vendas desta campanha estão pausadas.</strong></p>
                    <p>Quem já comprou continua participando normalmente.</p>
                </div>
            @elseif ($state === 'aguardando')
                <div class="situacao">
                    <p><strong>Vendas encerradas.</strong></p>
                    @if ($campaign->draw_at)
                        <p>Apuração em {{ hora_local($campaign->draw_at, 'd/m/Y \à\s H\hi') }}. O resultado é publicado nesta página.</p>
                    @else
                        <p>O resultado é publicado nesta página assim que sair a apuração.</p>
                    @endif
                </div>
            @elseif ($state === 'concluida')
                <div class="resultado">
                    <p class="rotulo">@include('partials.icon', ['name' => 'trophy', 'size' => 16]) {{ termo('contemplado') }}</p>
                    @include('partials.placa', ['numero' => $campaign->winning_number !== null ? $campaign->formatNumber($campaign->winning_number) : '—', 'tipo' => 'ouro grande'])
                    @if ($campaign->mainWinner)
                        <p class="resultado__nome">{{ $campaign->mainWinner->display_name }}</p>
                        <p class="dica">{{ $campaign->mainWinner->prize }} · apurado em {{ hora_local($campaign->drawn_at ?? $campaign->draw_at) }}</p>
                    @endif
                    @if ($campaign->official_result)<p class="dica">Resultado oficial: {{ $campaign->official_result }}</p>@endif
                </div>
            @endif

            <a class="compra__consulta" href="{{ route('consulta') }}">@include('partials.icon', ['name' => 'ticket', 'size' => 18]) {{ termo('meus') }} @include('partials.icon', ['name' => 'right', 'size' => 16])</a>
        </div>
    </section>

    <div class="detalhes">
        @if ($promo)
            <section class="promo" aria-labelledby="titulo-promo">
                <div class="promo__faixa" aria-hidden="true"></div>
                <div class="promo__corpo">
                    <p class="rotulo rotulo--alerta">Promoção com prazo</p>
                    <h2 id="titulo-promo">{{ $promo->title }}</h2>
                    <p class="promo__premio">{{ $promo->prize }}</p>
                    @if ($promo->rules)<p class="promo__regras">{{ $promo->rules }}</p>@endif

                    <div class="contador" data-contador="{{ $promo->ends_at->toIso8601String() }}" role="timer" aria-label="Tempo até o fim da promoção">
                        <span><b class="num" data-dias>{{ str_pad((string) intdiv($resto, 86400), 2, '0', STR_PAD_LEFT) }}</b><small>dias</small></span>
                        <span><b class="num" data-horas>{{ gmdate('H', $resto % 86400) }}</b><small>horas</small></span>
                        <span><b class="num" data-minutos>{{ gmdate('i', $resto) }}</b><small>min</small></span>
                        <span><b class="num" data-segundos>{{ gmdate('s', $resto) }}</b><small>seg</small></span>
                    </div>

                    <div class="lider" data-lider @if (! $leader) hidden @endif>
                        <p class="rotulo">{{ $promo->type === 'maior_numero' ? termo('maior').' até agora' : 'Maior comprador até agora' }}</p>
                        <span class="placa placa--ouro placa--grande num" data-lider-valor>{{ $leader['value'] ?? '' }}</span>
                        <p class="lider__quem"><strong data-lider-nome>{{ $leader['name'] ?? '' }}</strong>
                            <span data-lider-detalhe>{{ collect([$leader['location'] ?? null, isset($leader) ? $leader['at']->diffForHumans() : null])->filter()->implode(' · ') }}</span>
                        </p>
                    </div>
                    <p class="lider-vazio" data-sem-lider @if ($leader) hidden @endif>Ainda não há compra paga neste período.</p>
                    <p class="dica">Atualiza a cada pagamento confirmado.</p>
                </div>
            </section>
        @endif

        @if ($campaign->prizes->isNotEmpty())
            <section class="secao" aria-labelledby="titulo-premios">
                <h2 id="titulo-premios">Prêmios</h2>
                <ol class="premios">
                    @foreach ($campaign->prizes as $prize)
                        <li>
                            <span class="premios__pos num">{{ $prize->position }}º</span>
                            <div><strong>{{ $prize->title }}</strong>@if ($prize->description)<p>{{ $prize->description }}</p>@endif</div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if ($campaign->description)
            <section class="secao" aria-labelledby="titulo-sobre">
                <h2 id="titulo-sobre">Sobre o prêmio</h2>
                <div class="texto">
                    @foreach (preg_split('/\R{2,}/', trim($campaign->description)) as $paragrafo)
                        <p>{!! nl2br(e($paragrafo)) !!}</p>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($instantaneos->isNotEmpty())
            <section class="secao" aria-labelledby="titulo-instantaneos" data-filtro>
                <div class="secao__topo">
                    <h2 id="titulo-instantaneos">{{ termo('premiados') }}</h2>
                    <p class="dica">{{ $instantaneos->count() }} prêmios · {{ $sairam->count() }} já saíram</p>
                </div>
                <div class="filtro" role="group" aria-label="Filtrar prêmios">
                    <button type="button" data-ver="todos" aria-pressed="true">Todos</button>
                    <button type="button" data-ver="livre" aria-pressed="false">Disponíveis <span class="num">{{ $instantaneos->count() - $sairam->count() }}</span></button>
                    <button type="button" data-ver="saiu" aria-pressed="false">Já saíram <span class="num">{{ $sairam->count() }}</span></button>
                </div>
                <ul class="instantaneos">
                    @foreach ($instantaneos->sortByDesc('won_at') as $item)
                        @php $saiu = $item->ticket_id !== null; @endphp
                        <li data-tipo="{{ $saiu ? 'saiu' : 'livre' }}" @class(['saiu' => $saiu])>
                            @include('partials.placa', [
                                'numero' => $saiu || $campaign->instant_revealed_at ? $campaign->formatNumber($item->number) : str_repeat('•', $campaign->digits),
                                'tipo' => $saiu ? 'ouro' : 'oculta',
                            ])
                            <strong>{{ $item->prize }}</strong>
                            <span>{{ $saiu ? ($item->ticket?->order?->customer?->publicName() ?? 'Contemplado') : 'Disponível' }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($campaign->instant_hash)
                    <details class="prova">
                        <summary>Como conferir que a lista não mudou</summary>
                        <p>A lista de {{ termo('plural') }} premiados foi fechada antes da abertura das vendas. O código abaixo é o SHA-256 dessa lista; no encerramento, a lista completa e o sal são publicados aqui para qualquer pessoa conferir.</p>
                        <code>{{ $campaign->instant_hash }}</code>
                    </details>
                @endif
            </section>
        @endif

        @if ($campaign->draw_criterion || $campaign->draw_at || $campaign->delivery_deadline)
            <section class="secao secao--apuracao" aria-labelledby="titulo-apuracao">
                <h2 id="titulo-apuracao">Apuração</h2>
                <dl class="ficha">
                    @if ($campaign->draw_at)<div><dt>Data</dt><dd>{{ hora_local($campaign->draw_at, 'd/m/Y \à\s H\hi') }}</dd></div>@endif
                    @if ($campaign->draw_criterion)<div><dt>Critério</dt><dd>{{ $campaign->draw_criterion }}</dd></div>@endif
                    @if ($campaign->delivery_deadline)<div><dt>Entrega do prêmio</dt><dd>{{ $campaign->delivery_deadline }}</dd></div>@endif
                </dl>
            </section>
        @endif

        <section class="secao" aria-labelledby="titulo-legal">
            <h2 id="titulo-legal">Responsável e regulamento</h2>
            @if ($faltando)
                <div class="alerta">
                    @include('partials.icon', ['name' => 'alert'])
                    <div>
                        <strong>Dados legais ainda não preenchidos.</strong>
                        <p>Fora do ambiente de demonstração, a campanha só pode ser ativada com estes campos preenchidos no painel:</p>
                        <ul>
                            @foreach ($faltando as $campo)
                                <li>{{ $campo }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @else
                <dl class="ficha">
                    <div><dt>Responsável</dt><dd>{{ $campaign->legal_company }} · CNPJ {{ $campaign->formattedCnpj() }}</dd></div>
                    <div><dt>Modalidade</dt><dd>{{ Campaign::MODALIDADES[$campaign->legal_modality] ?? $campaign->legal_modality }}</dd></div>
                    <div><dt>Processo</dt><dd>{{ $campaign->legal_process }}</dd></div>
                    @if ($campaign->legal_capitalization_company)<div><dt>Sociedade de capitalização</dt><dd>{{ $campaign->legal_capitalization_company }}</dd></div>@endif
                    @if ($campaign->legal_beneficiary)<div><dt>Entidade beneficiada</dt><dd>{{ $campaign->legal_beneficiary }}</dd></div>@endif
                </dl>
                <a class="link-arquivo" href="{{ Storage::disk('public')->url($campaign->legal_regulation_path) }}">@include('partials.icon', ['name' => 'file', 'size' => 18]) Regulamento completo (PDF)</a>
            @endif
        </section>
    </div>
</article>

@if ($state === 'ativa')
    <div class="barra-fixa" data-barra-fixa hidden>
        <div>
            <small data-resumo>{{ $minimo }} × {{ brl($campaign->price_cents) }}</small>
            <b class="num" data-total>{{ brl($minimo * $campaign->price_cents) }}</b>
        </div>
        <button class="cta cta--simples" type="button" data-cta-fixo>{{ termo('garantir') }} @include('partials.icon', ['name' => 'arrow', 'size' => 20])</button>
    </div>

    <dialog class="checkout" id="checkout" aria-labelledby="titulo-checkout">
        <div class="checkout__topo">
            <h2 id="titulo-checkout">Seu pedido</h2>
            <button class="botao-icone" type="button" data-fecha aria-label="Fechar">@include('partials.icon', ['name' => 'close'])</button>
        </div>
        <div class="checkout__resumo">
            <div><p class="rotulo">Campanha</p><p>{{ $campaign->title }}</p></div>
            <div class="checkout__valor"><p class="rotulo" data-resumo-qtd></p><p class="num" data-total></p></div>
        </div>
        <form class="checkout__passo" data-checkout novalidate>
            <div class="campo">
                <label for="telefone">Seu telefone com DDD</label>
                <input id="telefone" name="telefone" type="tel" inputmode="numeric" autocomplete="tel-national" placeholder="(11) 90000-0000" aria-describedby="telefone-ajuda" required>
                <p class="dica" id="telefone-ajuda" data-telefone-ajuda>Usamos o telefone para localizar seu cadastro e avisar sobre o pedido.</p>
            </div>
            <button class="cta cta--simples" type="submit">Continuar @include('partials.icon', ['name' => 'arrow', 'size' => 20])</button>
            <p class="checkout__aviso" data-checkout-aviso role="status" hidden>O pagamento ainda não está ligado nesta versão de demonstração. Esta etapa entra na próxima entrega.</p>
        </form>
        <p class="confianca">@include('partials.icon', ['name' => 'lock', 'size' => 14]) Você paga por Pix e recebe os {{ termo('plural') }} na confirmação.</p>
    </dialog>
@endif
@endsection
