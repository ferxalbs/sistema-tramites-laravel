<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TramiteDocumentoEntregado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $codigoExpediente,
        public string $numeroDocumento,
        private string $pdf,
        private string $nombreArchivo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Documento de su trámite '.$this->codigoExpediente);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tramite-documento');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [Attachment::fromData(fn (): string => $this->pdf, $this->nombreArchivo)
            ->withMime('application/pdf')];
    }
}
