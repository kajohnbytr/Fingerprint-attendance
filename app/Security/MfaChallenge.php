<?php

namespace App\Security;

use App\Models\User;
use App\Notifications\LoginVerificationCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MfaChallenge
{
    /** @return array{token: string, expires_in: int} */
    public function issue(User $user, ?string $expectedRole = null, bool $remember = false): array
    {
        $token = Str::random(64);
        $code = (string) random_int(100000, 999999);
        $ttl = config('security.mfa_ttl_minutes');

        Cache::put($this->key($token), [
            'user_id' => $user->id,
            'code_hash' => hash_hmac('sha256', $code, config('app.key')),
            'attempts' => 0,
            'expected_role' => $expectedRole,
            'remember' => $remember,
        ], now()->addMinutes($ttl));

        $user->notify(new LoginVerificationCode($code, $ttl));

        return ['token' => $token, 'expires_in' => $ttl * 60];
    }

    public function verify(string $token, string $code): ?User
    {
        $key = $this->key($token);
        $challenge = Cache::get($key);

        if (! is_array($challenge) || ($challenge['attempts'] ?? 0) >= 5) {
            Cache::forget($key);

            return null;
        }

        $challenge['attempts']++;
        Cache::put($key, $challenge, now()->addMinutes(config('security.mfa_ttl_minutes')));
        $expectedHash = hash_hmac('sha256', $code, config('app.key'));

        if (! hash_equals($challenge['code_hash'], $expectedHash)) {
            return null;
        }

        Cache::forget($key);

        return User::query()->whereKey($challenge['user_id'])->where('is_active', true)->first();
    }

    /** @return array<string, mixed>|null */
    public function details(string $token): ?array
    {
        $challenge = Cache::get($this->key($token));

        return is_array($challenge) ? $challenge : null;
    }

    private function key(string $token): string
    {
        return 'mfa:'.hash('sha256', $token);
    }
}
