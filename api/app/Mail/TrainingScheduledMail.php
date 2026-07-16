<?php

namespace App\Mail;

use App\Models\TrainingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// "You're on the roster" — one recipient, their local time. Built from the session so
// the email carries real detail (team, when, what's being drilled), not just a ping.
class TrainingScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TrainingSession $session,
        public string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Practice scheduled: {$this->session->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.training-scheduled',
            with: [
                'name' => $this->recipientName,
                'team' => $this->session->team->name,
                'title' => $this->session->title,
                'when' => $this->session->scheduled_at,
                'duration' => $this->session->duration_minutes,
                'tactics' => $this->session->tactics->pluck('name')->all(),
            ],
        );
    }
}
