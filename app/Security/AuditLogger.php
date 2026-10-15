<?php

namespace App\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public function record(?User $user, string $action, string $status = 'Success', ?string $details = null): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $user?->id,
            'action' => $action,
            'details' => $details ?? '',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
