<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class AdminUser extends Authenticatable
{
    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'totp_secret'];

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
