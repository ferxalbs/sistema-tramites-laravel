<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();
        $nonce = Vite::cspNonce();
        $developmentOrigins = app()->environment('local') ? ' http://localhost:* http://127.0.0.1:*' : '';
        $developmentSockets = app()->environment('local') ? ' ws://localhost:* ws://127.0.0.1:*' : '';
        $response = $next($request);
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$developmentOrigins}",
            "style-src 'self' 'unsafe-inline'{$developmentOrigins}",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'{$developmentOrigins}{$developmentSockets}",
            "frame-src 'self' blob:",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]));
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
