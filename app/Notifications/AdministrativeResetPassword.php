<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AdministrativeResetPassword extends ResetPassword
{
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Restablecimiento administrativo de contraseña')
            ->line('Un administrador generó este enlace seguro para restablecer su contraseña.')
            ->action('Restablecer contraseña', $this->resetUrl($notifiable))
            ->line('El enlace vence en 30 minutos y solo puede usarse una vez.');
    }
}
