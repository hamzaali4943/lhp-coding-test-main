<?php

use App\Mail\EventReminderMail;
use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('queues 3-day and 24-hour reminders in their windows and stamps them', function () {
    Mail::fake();

    $in2Days = Event::factory()->create(['created_time' => now()->addDays(2)->timestamp]);
    $threeDayAttendee = Attendee::factory()->for($in2Days)->create();

    $in12Hours = Event::factory()->create(['created_time' => now()->addHours(12)->timestamp]);
    $oneDayAttendee = Attendee::factory()->for($in12Hours)->create();

    // Far-future event should not trigger anything yet.
    $farOff = Event::factory()->create(['created_time' => now()->addDays(20)->timestamp]);
    Attendee::factory()->for($farOff)->create();

    $this->artisan('events:send-reminders')->assertSuccessful();

    Mail::assertQueued(EventReminderMail::class, fn ($m) => $m->window === '3d' && $m->attendee->is($threeDayAttendee));
    Mail::assertQueued(EventReminderMail::class, fn ($m) => $m->window === '24h' && $m->attendee->is($oneDayAttendee));
    Mail::assertQueued(EventReminderMail::class, 2);

    expect($threeDayAttendee->fresh()->reminder_3d_sent_at)->not->toBeNull();
    expect($oneDayAttendee->fresh()->reminder_24h_sent_at)->not->toBeNull();
});

it('does not send duplicate reminders on a second run', function () {
    Mail::fake();

    $event = Event::factory()->create(['created_time' => now()->addHours(12)->timestamp]);
    Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders');
    $this->artisan('events:send-reminders');

    Mail::assertQueued(EventReminderMail::class, 1);
});
