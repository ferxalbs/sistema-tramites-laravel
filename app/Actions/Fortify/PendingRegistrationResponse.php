<?php

namespace App\Actions\Fortify;

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
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Solicitud registrada. Revise su correo institucional; la aprobación administrativa también es necesaria.');
    }
}
