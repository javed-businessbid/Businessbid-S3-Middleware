<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class HierysTransactionalMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{label:string,value:string}>  $detailRows
     */
    public function __construct(
        public string $emailSubject,
        public string $headline,
        public string $intro = '',
        public array $detailRows = [],
        public ?string $credentialPassword = null,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        $preheader = $this->intro !== ''
            ? Str::limit(trim(str_replace(["\r\n", "\r", "\n"], ' ', strip_tags($this->intro))), 140)
            : Str::limit($this->headline, 140);

        return new Content(
            view: 'mail.hierys-transactional',
            with: [
                'headline' => $this->headline,
                'intro' => $this->intro,
                'detailRows' => $this->detailRows,
                'credentialPassword' => $this->credentialPassword,
                'actionUrl' => $this->actionUrl,
                'actionLabel' => $this->actionLabel,
                'preheader' => $preheader,
            ],
        );
    }
}
