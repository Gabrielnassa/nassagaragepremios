<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_log';

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('O log de auditoria não pode ser alterado.'));
        static::deleting(fn () => throw new LogicException('O log de auditoria não pode ser apagado.'));
    }
}
