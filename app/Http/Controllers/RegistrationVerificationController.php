<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RegistrationVerificationController extends Controller
{
    public function __invoke(int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
        abort_if($user->hasVerifiedEmail(), 410);

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->route('login')->with('status', 'Correo verificado. Su cuenta sigue pendiente de aprobación administrativa.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $input = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $email = mb_strtolower(trim($input['email']));
        $key = 'verification-resend:'.hash('sha256', $email.'|'.$request->ip());

        RateLimiter::attempt($key, 1, function () use ($email): void {
            $user = User::query()->where('email', $email)->where('estado_cuenta', 'pendiente')->first();

            if ($user instanceof User && ! $user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();
            }
        }, 300);

        return back()->with('status', 'Si la cuenta está pendiente, se enviará un enlace de verificación al correo indicado.');
    }
}
