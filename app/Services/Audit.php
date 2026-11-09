<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class Audit
{
    public function record(string $action, ?object $subject = null, array $metadata = []): void
    {
        AuditLog::create(['user_id' => Auth::id(), 'action' => $action, 'subject_type' => $subject ? $subject::class : null, 'subject_id' => $subject?->getKey(), 'metadata' => $metadata, 'ip_address' => request()?->ip(), 'user_agent' => substr((string) request()?->userAgent(), 0, 1000), 'created_at' => now()]);
    }
}
