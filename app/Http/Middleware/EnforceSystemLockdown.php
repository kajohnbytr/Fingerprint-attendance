<?php

namespace App\Http\Middleware;

use App\Security\SystemLockdown;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSystemLockdown
{
    public function __construct(private SystemLockdown $lockdown) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->lockdown->enabled() || $request->user()?->role === 'super_admin') {
            return $next($request);
        }

        if (! $request->user() && $request->routeIs('login', 'login.store', 'mfa.*')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'The attendance system is currently in emergency lockdown. Only the Super Administrator can access the system.',
                'lockdown' => true,
            ], 503);
        }

        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->view('errors.lockdown', status: 503);
    }
}
