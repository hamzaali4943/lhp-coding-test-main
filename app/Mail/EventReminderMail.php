<?php

namespace App\Mail;

use App\Models\Attendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  '3d'|'24h'  $window  Which reminder this is.
     */
    public function __construct(public Attendee $attendee, public string $window) {}

    public function envelope(): Envelope
    {
        $name = $this->attendee->event->payload['name'] ?? 'your event';
        $lead = $this->window === '24h' ? 'Tomorrow' : 'In 3 days';

        return new Envelope(subject: "{$lead}: {$name}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.event-reminder', with: [
            'attendee' => $this->attendee,
            'event' => $this->attendee->event,
            'window' => $this->window,
            'lead' => $this->window === '24h' ? '24 hours' : '3 days',
        ]);
    }
}
