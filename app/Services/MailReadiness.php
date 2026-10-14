<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class MailReadiness
{
    public function isTestInbox(): bool
    {
        return (bool) config('mail.test_inbox') && app()->environment('local', 'testing');
    }

    public function canSend(): bool
    {
        $mailer = config('mail.default');
        if (! is_string($mailer) || in_array($mailer, ['array', 'log'], true)) {
            return false;
        }
        if (config('mail.test_inbox') && ! $this->isTestInbox()) {
            return false;
        }
        $settings = config('mail.mailers.'.$mailer);
        if (! is_array($settings)) {
            return false;
        }
        if (in_array($settings['transport'] ?? '', ['failover', 'roundrobin'], true)) {
            // A log fallback would acknowledge messages that never left the server.
            return false;
        }

        return true;
    }

    public function requireDelivery(string $field): void
    {
        if (! $this->canSend()) {
            throw ValidationException::withMessages([$field => 'El correo todavía no está configurado para enviar mensajes. Configure un proveedor autorizado o la bandeja local de pruebas.']);
        }
    }

    public function sentMessage(): string
    {
        return $this->isTestInbox()
            ? 'Mensaje entregado a la bandeja local de pruebas; no se envió a un correo externo.'
            : 'El servidor de correo aceptó el mensaje para su envío.';
    }
}
