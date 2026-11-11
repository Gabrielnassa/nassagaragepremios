<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const PENDENTE = 'pendente';

    public const PAGO = 'pago';

    public const EXPIRADO = 'expirado';

    public const CANCELADO = 'cancelado';

    public const ESTORNADO = 'estornado';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->orderBy('number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
