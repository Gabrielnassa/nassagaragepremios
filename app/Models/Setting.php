<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function all_cached(): array
    {
        return Cache::rememberForever('settings', fn () => static::query()->pluck('value', 'key')->all());
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::all_cached()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }
}
