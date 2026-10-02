<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsCurrent
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $user->refresh();
            $sessionVersion = $request->session()->get('account_session_version');

            if (! $user->activo || $user->estado_cuenta !== 'activo' || $user->rol === 'asistente'
                || ($sessionVersion !== null && (int) $sessionVersion !== $user->sesion_version)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                abort(Response::HTTP_FORBIDDEN);
            }

            if ($sessionVersion === null) {
                $request->session()->put('account_session_version', $user->sesion_version);
            }
        }

        return $next($request);
    }
}
