<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Winner extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'awarded_on' => 'date',
            'consent_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
