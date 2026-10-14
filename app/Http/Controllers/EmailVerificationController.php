<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MailReadiness;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;

class EmailVerificationController extends EmailVerificationNotificationController
{
    public function store(Request $request): mixed
    {
        $user = $request->user();
        if (! $user instanceof User || $user->hasVerifiedEmail()) {
            return parent::store($request);
        }
        app(MailReadiness::class)->requireDelivery('email');
        $user->sendEmailVerificationNotification();
        if (! $user->verificationMailWasSubmitted()) {
            throw ValidationException::withMessages(['email' => 'No se pudo enviar la verificación. Reintente o contacte con la administración.']);
        }

        return app(EmailVerificationNotificationSentResponse::class);
    }
}
