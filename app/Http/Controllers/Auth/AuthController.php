<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Security\AuditLogger;
use App\Security\MfaChallenge;
use App\Security\SystemLockdown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private SystemLockdown $lockdown) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, MfaChallenge $mfa, AuditLogger $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => [
                'required',
                'email:rfc',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $user = User::query()->where('email', mb_strtolower(trim($credentials['email'])))->where('is_active', true)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $audit->record($user, 'Web login rejected', 'Failed', 'Invalid credentials from '.$request->ip().'.');

            return back()
                ->withErrors([
                    'email' => 'The supplied credentials are incorrect.',
                ])
                ->onlyInput('email');
        }

        if ($this->lockdown->enabled() && $user->role !== 'super_admin') {
            $audit->record($user, 'Web login rejected', 'Failed', 'System lockdown active.');

            return back()
                ->withErrors(['email' => 'The attendance system is currently in emergency lockdown.  '])
                ->onlyInput('email');
        }

        if (config('security.mfa_enabled')) {
            $challenge = $mfa->issue($user, remember: $request->boolean('remember'));
            $request->session()->put('security.mfa_token', $challenge['token']);

            return redirect()->route('mfa.challenge');
        }

        $this->completeLogin($request, $user, $request->boolean('remember'), $audit);

        return redirect()->intended(route('dashboard'));
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('security.mfa_token')) {
            return redirect()->route('login');
        }

        return view('auth.mfa');
    }

    public function verify(Request $request, MfaChallenge $mfa, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $token = (string) $request->session()->get('security.mfa_token');
        $details = $mfa->details($token);
        $user = $mfa->verify($token, $data['code']);

        if (! $user) {
            $audit->record(null, 'Web MFA rejected', 'Failed', 'Invalid or expired challenge from '.$request->ip().'.');

            return back()->withErrors(['code' => 'The verification code is invalid or expired.']);
        }

        if ($this->lockdown->enabled() && $user->role !== 'super_admin') {
            $audit->record($user, 'Web MFA rejected', 'Failed', 'System lockdown active.');

            return redirect()->route('login')->withErrors([
                'email' => 'The attendance system is currently in emergency lockdown.  ',
            ]);
        }

        $request->session()->forget('security.mfa_token');
        $this->completeLogin($request, $user, (bool) ($details['remember'] ?? false), $audit);

        return redirect()->intended(route('dashboard'));
    }

    private function completeLogin(Request $request, User $user, bool $remember, AuditLogger $audit): void
    {
        Auth::login($user, $remember);

        $request->session()->regenerate();
        $request->session()->put('security.last_activity', time());
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $audit->record($user, 'Web login completed', details: 'Authenticated from '.$request->ip().'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
