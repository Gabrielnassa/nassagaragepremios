@php
    $acesas = (int) floor($percent / 5);
    if ($percent > 0 && $acesas === 0) {
        $acesas = 1;
    }
@endphp
<div class="luzes" data-luzes="{{ $acesas }}" role="img" aria-label="{{ number_format($percent, 2, ',', '.') }}% vendido">
    @for ($i = 0; $i < 20; $i++)<i @class(['on' => $i < $acesas])></i>@endfor
</div>
