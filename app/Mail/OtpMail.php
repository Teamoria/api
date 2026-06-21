<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OtpMail extends Mailable
{
    use Queueable;

    public string $code;

    public string $type;

    public function __construct(string $code, string $type, string $subject = 'Verification Code')
    {
        $this->code = $code;
        $this->subject = $subject;
        $this->type = $type;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'code' => $this->code,
                'type' => $this->type,
            ],
        );
    }
}
