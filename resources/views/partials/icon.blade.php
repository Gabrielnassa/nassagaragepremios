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
        'alert' => '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.5"/>',
        'flag' => '<path d="M5 21V4M5 4h14l-3 5 3 5H5"/>',
        'ticket' => '<path d="M3 8V6h18v2a2.5 2.5 0 000 5v2a2.5 2.5 0 000 0v3H3v-3a2.5 2.5 0 000-5z" transform="translate(0 -.5)"/><path d="M14 6v2M14 11v2M14 16v2"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l5 5"/>',
        'trophy' => '<path d="M8 4h8v5a4 4 0 01-8 0zM8 6H4v1a3 3 0 003 3M16 6h4v1a3 3 0 01-3 3M12 13v4M8 20h8M10 17h4"/>',
        'pix' => '<path d="M12 3l9 9-9 9-9-9z"/><path d="M8 8l4 4 4-4M8 16l4-4 4 4"/>',
        'shield' => '<path d="M12 3l8 3v6c0 4.5-3.2 7.8-8 9-4.8-1.200-8-4.500-8-9V6z"/>',
        'video' => '<rect x="3" y="6" width="12" height="12" rx="1"/><path d="M15 10l6-3v10l-6-3"/>',
        'screen' => '<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M8 20h8M12 16v4"/>',
        'coin' => '<circle cx="12" cy="12" r="9"/><path d="M14.500 9.500c-.5-1-1.500-1.500-2.500-1.500-1.500 0-2.500.8-2.500 2s1 1.700 2.500 2 2.500.8 2.500 2-1 2-2.500 2c-1.100 0-2.100-.5-2.600-1.500M12 6v2M12 16v2"/>',
        'grid' => '<rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><rect x="14" y="14" width="6" height="6"/>',
    ];
@endphp
<svg class="icone" viewBox="0 0 24 24" width="{{ $size ?? 20 }}" height="{{ $size ?? 20 }}" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] !!}</svg>
