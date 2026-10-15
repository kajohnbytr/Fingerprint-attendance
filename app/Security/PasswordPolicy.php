<?php

namespace App\Security;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    public static function baseRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            Password::min(12)->mixedCase()->letters()->numbers()->symbols(),
        ];
    }

    public static function rules(User $user, bool $required = true): array
    {
        return [
            ...self::baseRules($required),
            function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                if (! is_string($value)) {
                    return;
                }

                $passwords = collect([$user->password])
                    ->merge($user->passwordHistories()->latest()->limit(config('security.password_history'))->pluck('password'));

                if ($passwords->contains(fn (string $hash): bool => Hash::check($value, $hash))) {
                    $fail('The new password must not match any of your recent passwords.');
                }
            },
        ];
    }

    public static function change(User $user, string $newPassword): void
    {
        DB::transaction(function () use ($user, $newPassword): void {
            $user->passwordHistories()->create(['password' => $user->password]);
            $user->forceFill([
                'password' => $newPassword,
                'must_change_password' => false,
            ])->save();

            $idsToKeep = $user->passwordHistories()
                ->latest()
                ->limit(config('security.password_history'))
                ->pluck('id');
            $user->passwordHistories()->whereNotIn('id', $idsToKeep)->delete();
        });
    }

    public static function validateAndChange(User $user, string $newPassword): void
    {
        validator(
            ['newPassword' => $newPassword],
            ['newPassword' => self::rules($user)]
        )->validate();

        self::change($user, $newPassword);
    }
}
