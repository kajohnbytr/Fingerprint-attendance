<?php

namespace App\Http\Middleware;

use App\Security\IdleTimeout;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RequireRecentApiActivity
{
    public function __construct(private IdleTimeout $idleTimeout) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        $idleMinutes = $this->idleTimeout->minutes();

        if ($token instanceof PersonalAccessToken && $token->last_used_at?->lt(now()->subMinutes($idleMinutes))) {
            $token->delete();

            return response()->json(['message' => 'Your session expired due to inactivity.'], 401);
        }

        return $next($request);
    }
}
