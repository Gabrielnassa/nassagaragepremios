<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Customer extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['cpf_encrypted', 'cpf_hash'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'location_consent' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'blocked_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public static function hashCpf(string $cpf): string
    {
        return hash_hmac('sha256', preg_replace('/\D/', '', $cpf), (string) config('app.key'));
    }

    public function setCpf(string $cpf): void
    {
        $digits = preg_replace('/\D/', '', $cpf);
        $this->cpf_encrypted = Crypt::encryptString($digits);
        $this->cpf_hash = static::hashCpf($digits);
    }

    public function cpf(): string
    {
        return Crypt::decryptString($this->cpf_encrypted);
    }

    public function publicName(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_convert_case($parts[0] ?? '', MB_CASE_TITLE);
        $last = count($parts) > 1 ? mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : '';

        return trim("$first $last");
    }

    public function publicLocation(): ?string
    {
        if (! $this->location_consent || ! $this->city || ! $this->state) {
            return null;
        }

        return "{$this->city}, {$this->state}";
    }
}
