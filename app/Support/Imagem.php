<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Imagem
{
    private const MAX_WIDTH = 1600;

    /**
     * Grava a foto com nome aleatório, reduzida e convertida para WebP quando o servidor permite.
     *
     * @return array{path: string, width: int, height: int}
     */
    public static function salvar(UploadedFile $file, string $dir): array
    {
        [$width, $height] = getimagesize($file->getRealPath());
        $name = bin2hex(random_bytes(16));

        $source = function_exists('imagewebp') ? @imagecreatefromstring(file_get_contents($file->getRealPath())) : false;

        if ($source === false) {
            $path = $file->storeAs($dir, $name.'.'.$file->extension(), 'public');

            return ['path' => $path, 'width' => $width, 'height' => $height];
        }

        if ($width > self::MAX_WIDTH) {
            $scaled = imagescale($source, self::MAX_WIDTH);
            imagedestroy($source);
            $source = $scaled;
            $height = imagesy($source);
            $width = self::MAX_WIDTH;
        }

        imagepalettetotruecolor($source);
        ob_start();
        imagewebp($source, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($source);

        $path = "$dir/$name.webp";
        Storage::disk('public')->put($path, $bytes);

        return ['path' => $path, 'width' => $width, 'height' => $height];
    }
}
