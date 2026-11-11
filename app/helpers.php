<?php

use App\Models\Setting;
use Carbon\CarbonInterface;

if (! function_exists('brl')) {
    function brl(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}

if (! function_exists('termo')) {
    function termo(string $key, array $replace = []): string
    {
        $set = Setting::get('vocabulario', 'numeros') === 'titulos' ? 'titulos' : 'numeros';

        return __("termos.$set.$key", $replace);
    }
}

if (! function_exists('hora_local')) {
    function hora_local(?CarbonInterface $date, string $format = 'd/m/Y'): string
    {
        return $date ? $date->copy()->setTimezone('America/Sao_Paulo')->translatedFormat($format) : '';
    }
}

if (! function_exists('recurso')) {
    function recurso(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.substr(md5((string) filemtime($file)), 0, 8) : '');
    }
}
