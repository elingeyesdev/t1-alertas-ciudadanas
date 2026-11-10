<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AuthorityCredentialsMail extends Mailable
{
    public function __construct(public string $phone, public string $password) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Credenciales de acceso');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.credentials');
    }
}
