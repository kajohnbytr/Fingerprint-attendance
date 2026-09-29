<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SessionController extends Controller
{
    public function login(Request $request): JsonResponse
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
            return response()->json([
                'error' => 'The supplied credentials are incorrect.',
            ], 422);
        }

        if (! $this->matchesExpectedRole($user->role, $data['expectedRole'] ?? null)) {
            return response()->json([
                'error' => 'This account does not have the selected role.',
            ], 403);
        }

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
            'newPassword' => ['required', 'string', Password::min(8), 'different:currentPassword'],
            'confirmPassword' => ['required', 'same:newPassword'],
        ]);

        $user = $request->user();
        if (! Hash::check($data['currentPassword'], $user->password)) {
            return response()->json([
                'errors' => ['currentPassword' => ['The current password is incorrect.']],
            ], 422);
        }

        $user->forceFill([
            'password' => $data['newPassword'],
            'must_change_password' => false,
        ])->save();

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
