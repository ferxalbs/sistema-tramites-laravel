<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\MailReadiness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse;
use Symfony\Component\HttpFoundation\Response;

class PendingRegistrationResponse implements RegisterResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $user = $request->user();
        $submitted = $user instanceof User && $user->verificationMailWasSubmitted();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = ! $submitted
            ? 'Solicitud registrada y pendiente de aprobación. No se pudo enviar la verificación de correo; contacte con la administración o reintente su envío.'
            : (app(MailReadiness::class)->isTestInbox()
                ? 'Solicitud registrada. La verificación está en la bandeja local de pruebas; la aprobación administrativa también es necesaria.'
                : 'Solicitud registrada. Revise su correo institucional; la aprobación administrativa también es necesaria.');

        return redirect()->route('login')->with('status', $message);
    }
}
