<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChangeIsComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->debe_cambiar_password
            && ! $request->routeIs('password.change-required', 'user-password.update', 'logout')) {
            return to_route('password.change-required', [], 303);
        }

        return $next($request);
    }
}
