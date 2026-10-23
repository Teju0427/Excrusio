<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records who did what, to which record, from where. Used for sign-ins,
 * approvals, and later every booking status change.
 */
class AuditLogger
{
    public function log(string $action, ?Model $subject = null, array $old = [], array $new = []): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
