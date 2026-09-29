<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest;

class PasswordRecoveryController extends PasswordResetLinkController
{
    public function store(SendPasswordResetLinkRequest $request): Responsable
    {
        $email = mb_strtolower(trim((string) $request->input(Fortify::email(), '')));
        $emailKey = 'password-recovery:email:'.hash('sha256', $email);
        $ipKey = 'password-recovery:ip:'.hash('sha256', (string) $request->ip());

        if (! RateLimiter::tooManyAttempts($emailKey, 3) && ! RateLimiter::tooManyAttempts($ipKey, 10)) {
            RateLimiter::hit($emailKey, 1800);
            RateLimiter::hit($ipKey, 3600);

            if (str_ends_with($email, '@seoane.edu.pe')) {
                $user = User::query()->where('email', $email)->first();

                if ($user?->activo && $user->estado_cuenta === 'activo'
                    && ($user->rol !== 'estudiante' || preg_match('/\Aa\.[a-z0-9._-]+@seoane\.edu\.pe\z/D', $email) === 1)) {
                    Password::broker(config('fortify.passwords'))->sendResetLink(['email' => $email]);
                }
            }
        }

        return app(SuccessfulPasswordResetLinkRequestResponse::class, ['status' => Password::RESET_LINK_SENT]);
    }
}
