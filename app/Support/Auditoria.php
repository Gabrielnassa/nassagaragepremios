<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Auditoria
{
    public static function registrar(string $action, ?Model $subject = null, array $data = []): void
    {
        AuditLog::create([
            'admin_user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'data' => $data ?: null,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
