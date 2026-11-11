@php
    $rotulos = [
        'em_breve' => 'Em breve',
        'ativa' => 'À venda',
        'esgotada' => 'Esgotada',
        'pausada' => 'Vendas pausadas',
        'aguardando' => 'Aguardando apuração',
        'concluida' => 'Concluída',
        'indisponivel' => 'Indisponível',
    ];
@endphp
<span class="estado estado--{{ $state }}"><span>{{ $rotulos[$state] }}</span></span>
