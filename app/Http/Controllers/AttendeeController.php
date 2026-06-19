<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendeeRequest;
use App\Mail\AttendeeConfirmationMail;
use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class AttendeeController extends Controller
{
    /**
     * Register interest/attendance for an event and email a confirmation.
     */
    public function store(StoreAttendeeRequest $request, Event $event): RedirectResponse
    {
        $attendee = Attendee::firstOrNew([
            'event_id' => $event->id,
            'email' => $request->string('email')->lower()->value(),
        ]);

        if ($attendee->exists) {
            return back()->with('toast', [
                'type' => 'info',
                'message' => "You're already on the list for this event.",
            ]);
        }

        $attendee->fill([
            'name' => $request->string('name'),
            'status' => $request->input('status', 'interested'),
        ])->save();

        $attendee->setRelation('event', $event);
        Mail::to($attendee->email)->send(new AttendeeConfirmationMail($attendee));

        return back()->with('toast', [
            'type' => 'success',
            'message' => "You're on the list — check your email for confirmation.",
        ]);
    }
}
