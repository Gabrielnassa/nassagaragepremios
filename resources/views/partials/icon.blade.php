@php
    $paths = [
        'menu' => '<path d="M3 6h18M3 12h12M3 18h18"/>',
        'close' => '<path d="M5 5l14 14M19 5L5 19"/>',
        'left' => '<path d="M15 5l-7 7 7 7"/>',
        'right' => '<path d="M9 5l7 7-7 7"/>',
        'arrow' => '<path d="M4 12h16M13 5l7 7-7 7"/>',
        'minus' => '<path d="M5 12h14"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="1"/><path d="M8 11V8a4 4 0 018 0v3"/>',
        'chat' => '<path d="M4 5h16v11H9l-5 4z"/>',
        'file' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 13h6M9 17h6"/>',
        'check' => '<path d="M4 12l5 5L20 6"/>',
        'calendar' => '<rect x="4" y="5" width="16" height="15" rx="1"/><path d="M4 10h16M8 3v4M16 3v4"/>',
        'alert' => '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.5"/>',
    ];
@endphp
<svg class="icone" viewBox="0 0 24 24" width="{{ $size ?? 20 }}" height="{{ $size ?? 20 }}" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="square" aria-hidden="true">{!! $paths[$name] !!}</svg>
