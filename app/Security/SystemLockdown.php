<?php

namespace App\Security;

use Illuminate\Support\Facades\DB;
use Throwable;

class SystemLockdown
{
    public function enabled(): bool
    {
        try {
            return (bool) DB::table('super_admin_settings')
                ->where('id', 1)
                ->value('system_lockdown');
        } catch (Throwable) {
            return false;
        }
    }
}
