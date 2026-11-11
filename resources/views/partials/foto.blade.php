@if ($image)
    <img src="{{ $image->url() }}" alt="{{ $image->alt }}" width="{{ $image->width ?? 1200 }}" height="{{ $image->height ?? 800 }}"
        @if (empty($eager)) loading="lazy" decoding="async" @else fetchpriority="high" @endif>
@else
    <span class="sem-foto">Foto em breve</span>
@endif
