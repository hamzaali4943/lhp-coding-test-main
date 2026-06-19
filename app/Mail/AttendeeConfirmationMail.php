<?php

namespace App\Mail;

use App\Models\Attendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendeeConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Attendee $attendee) {}

    public function envelope(): Envelope
    {
        $name = $this->attendee->event->payload['name'] ?? 'the event';

        return new Envelope(subject: "You're on the list for {$name}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.attendee-confirmation', with: [
            'attendee' => $this->attendee,
            'event' => $this->attendee->event,
        ]);
    }
}
