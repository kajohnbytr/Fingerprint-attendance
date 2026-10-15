<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Security\AuditLogger;
use App\Security\MfaChallenge;
use App\Security\PasswordPolicy;
use App\Security\SystemLockdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SessionController extends Controller
{
    public function __construct(private SystemLockdown $lockdown) {}

    public function login(Request $request, MfaChallenge $mfa, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'expectedRole' => ['nullable', 'string', 'max:30'],
        ]);

        $user = User::query()
            ->where('email', mb_strtolower(trim($data['email'])))
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $audit->record($user, 'API login rejected', 'Failed', 'Invalid credentials from '.$request->ip().'.');

            return response()->json([
                'error' => 'The supplied credentials are incorrect.',
            ], 422);
        }

        if (! $this->matchesExpectedRole($user->role, $data['expectedRole'] ?? null)) {
            $audit->record($user, 'API login rejected', 'Failed', 'Role mismatch from '.$request->ip().'.');

            return response()->json([
                'error' => 'This account does not have the selected role.',
            ], 403);
        }

        if ($this->lockdown->enabled() && $user->role !== 'super_admin') {
            $audit->record($user, 'API login rejected', 'Failed', 'System lockdown active.');

            return response()->json([
                'error' => 'The attendance system is currently in emergency lockdown.',
                'lockdown' => true,
            ], 503);
        }

        if (config('security.mfa_enabled')) {
            $challenge = $mfa->issue($user, $data['expectedRole'] ?? null);

            return response()->json([
                'mfaRequired' => true,
                'challengeToken' => $challenge['token'],
                'expiresIn' => $challenge['expires_in'],
                'message' => 'A verification code was sent to your email address.',
            ], 202);
        }

        return $this->authenticatedResponse($request, $user, $audit);
    }

    public function verifyMfa(Request $request, MfaChallenge $mfa, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'challengeToken' => ['required', 'string', 'size:64'],
            'code' => ['required', 'digits:6'],
        ]);
        $details = $mfa->details($data['challengeToken']);
        $user = $mfa->verify($data['challengeToken'], $data['code']);

        if (! $user || ! $this->matchesExpectedRole($user->role, $details['expected_role'] ?? null)) {
            $audit->record($user, 'API MFA rejected', 'Failed', 'Invalid or expired challenge from '.$request->ip().'.');

            return response()->json(['error' => 'The verification code is invalid or expired.'], 422);
        }

        return $this->authenticatedResponse($request, $user, $audit);
    }

    private function authenticatedResponse(Request $request, User $user, AuditLogger $audit): JsonResponse
    {
        if ($this->lockdown->enabled() && $user->role !== 'super_admin') {
            return response()->json([
                'error' => 'The attendance system is currently in emergency lockdown.  ',
                'lockdown' => true,
            ], 503);
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $audit->record($user, 'API login completed', details: 'Authenticated from '.$request->ip().'.');

        $token = $user->createToken('capstone-frontend');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->frontendRole($user->role),
                'mustChangePassword' => $user->must_change_password,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->frontendRole($user->role),
                'mustChangePassword' => $user->must_change_password,
            ],
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => PasswordPolicy::rules($request->user()),
            'confirmPassword' => ['required', 'same:newPassword'],
        ]);

        $user = $request->user();
        if (! Hash::check($data['currentPassword'], $user->password)) {
            return response()->json([
                'errors' => ['currentPassword' => ['The current password is incorrect.']],
            ], 422);
        }

        PasswordPolicy::change($user, $data['newPassword']);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    private function matchesExpectedRole(string $role, ?string $expectedRole): bool
    {
        if ($expectedRole === null) {
            return true;
        }

        return $role === $expectedRole || ($expectedRole === 'admin' && $role === 'faculty');
    }

    private function frontendRole(string $role): string
    {
        return $role === 'faculty' ? 'admin' : $role;
    }
}
