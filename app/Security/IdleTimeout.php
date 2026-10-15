<?php

namespace App\Security;

use Illuminate\Support\Facades\DB;
use Throwable;

class IdleTimeout
{
    public function minutes(): int
    {
        try {
            $configuredMinutes = DB::table('super_admin_settings')
                ->where('id', 1)
                ->value('session_timeout_minutes');
        } catch (Throwable) {
            $configuredMinutes = null;
        }

        $fallbackMinutes = max(
            (int) config('security.session_idle_minutes', 30),
            (int) config('security.api_idle_minutes', 30),
        );

        return min(1440, max(5, (int) ($configuredMinutes ?? $fallbackMinutes)));
    }

    /** @return array{timeoutMinutes: int, warningDelaySeconds: int, countdownSeconds: int} */
    public function browserSettings(): array
    {
        $timeoutMinutes = $this->minutes();
        $totalSeconds = $timeoutMinutes * 60;
        $countdownSeconds = min(120, $totalSeconds - 60);

        return [
            'timeoutMinutes' => $timeoutMinutes,
            'warningDelaySeconds' => $totalSeconds - $countdownSeconds,
            'countdownSeconds' => $countdownSeconds,
        ];
    }
}
