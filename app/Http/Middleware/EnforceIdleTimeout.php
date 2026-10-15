<?php

namespace App\Http\Middleware;

use App\Security\IdleTimeout;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdleTimeout
{
    public function __construct(private IdleTimeout $idleTimeout) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $lastActivity = (int) $request->session()->get('security.last_activity', time());
            $idleSeconds = $this->idleTimeout->minutes() * 60;

            if (time() - $lastActivity > $idleSeconds) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Your session expired due to inactivity. Please sign in again.',
                ]);
            }

            $request->session()->put('security.last_activity', time());
        }

        return $next($request);
    }
}
