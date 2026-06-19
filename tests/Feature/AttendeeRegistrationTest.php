<?php

use App\Mail\AttendeeConfirmationMail;
use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('registers an attendee and emails a confirmation', function () {
    Mail::fake();
    $event = Event::factory()->create();

    $this->post(route('events.attendees.store', $event), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ])->assertRedirect();

    $this->assertDatabaseHas('attendees', [
        'event_id' => $event->id,
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
    ]);

    Mail::assertQueued(AttendeeConfirmationMail::class, fn ($mail) => $mail->hasTo('ada@example.com'));
});

it('does not register the same email twice for one event', function () {
    Mail::fake();
    $event = Event::factory()->create();

    $payload = ['name' => 'Ada', 'email' => 'ada@example.com'];
    $this->post(route('events.attendees.store', $event), $payload);
    $this->post(route('events.attendees.store', $event), $payload);

    expect(Attendee::where('event_id', $event->id)->count())->toBe(1);
    Mail::assertQueued(AttendeeConfirmationMail::class, 1);
});

it('validates the registration input', function () {
    $event = Event::factory()->create();

    $this->post(route('events.attendees.store', $event), ['name' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['name', 'email']);
});
