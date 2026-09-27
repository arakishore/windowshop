<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionalNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $notificationSubject,
        public readonly string $notificationBody,
        public readonly string $brandName,
        public readonly ?string $brandLogoUrl,
        public readonly string $marketplaceName,
        public readonly string $fromEmail,
        public readonly string $fromName,
        public readonly ?string $replyToEmail = null,
        public readonly array $ccRecipients = [],
        public readonly array $bccRecipients = [],
        public readonly array $emailPresentation = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
            replyTo: $this->replyToEmail ? [new Address($this->replyToEmail)] : [],
            cc: array_map(fn (string $email) => new Address($email), $this->ccRecipients),
            bcc: array_map(fn (string $email) => new Address($email), $this->bccRecipients),
            subject: $this->notificationSubject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.transactional-notification');
    }
}
