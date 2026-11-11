<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CampaignImage extends Model
{
    protected $guarded = ['id'];

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
