<?php

namespace App\Console\Commands;

use App\Mail\EventReminderMail;
use App\Models\Attendee;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Queue 3-day and 24-hour reminder emails for upcoming events with attendees';

    public function handle(): int
    {
        $now = Carbon::now();

        // 3-day reminder: event is more than 24h but no more than 72h away.
        // The lower bound means an attendee who is already inside the 24h window
        // only ever receives the 24h reminder, never a misleading "3 days" one.
        $threeDay = $this->dispatchWindow(
            window: '3d',
            column: 'reminder_3d_sent_at',
            from: $now->copy()->addDay(),
            to: $now->copy()->addDays(3),
        );

        // 24-hour reminder: event is within the next 24h (and still upcoming).
        $oneDay = $this->dispatchWindow(
            window: '24h',
            column: 'reminder_24h_sent_at',
            from: $now,
            to: $now->copy()->addDay(),
        );

        $this->info("Queued {$threeDay} three-day and {$oneDay} 24-hour reminders.");

        return self::SUCCESS;
    }

    /**
     * Queue reminders for attendees of events whose start falls in [from, to]
     * and who have not yet been notified for this window.
     *
     * @param  '3d'|'24h'  $window
     */
    private function dispatchWindow(string $window, string $column, Carbon $from, Carbon $to): int
    {
        $count = 0;

        Attendee::query()
            ->whereNull($column)
            ->whereHas('event', fn ($q) => $q
                ->whereBetween('created_time', [$from->timestamp, $to->timestamp]))
            ->with('event')
            ->chunkById(500, function ($attendees) use ($window, $column, &$count) {
                foreach ($attendees as $attendee) {
                    Mail::to($attendee->email)->send(new EventReminderMail($attendee, $window));
                    $attendee->forceFill([$column => now()])->save();
                    $count++;
                }
            });

        return $count;
    }
}
