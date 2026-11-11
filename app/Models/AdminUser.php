<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class AdminUser extends Authenticatable
{
    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'totp_secret'];

    public const PAPEIS = ['leitura' => 'Leitura', 'operador' => 'Operador', 'dono' => 'Dono'];

    private const NIVEIS = ['leitura' => 1, 'operador' => 2, 'dono' => 3];

    public function pode(string $papel): bool
    {
        return (self::NIVEIS[$this->role] ?? 0) >= self::NIVEIS[$papel];
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'totp_secret' => 'encrypted',
            'totp_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'active' => 'boolean',
        ];
    }
}
