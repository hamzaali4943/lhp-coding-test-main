@php
    $name = $event->payload['name'] ?? 'the event';
    $when = $event->starts_at_iso ?? null;
@endphp

<x-mail::message>
# You're on the list! 🎉

Hi {{ $attendee->name }},

Thanks for registering your interest in **{{ $name }}**. We've added you to the attendee list.

**When:** {{ $when ? \Illuminate\Support\Carbon::parse($when)->format('l, j F Y \a\t g:i A') : 'TBA' }}@if($event->timezone) ({{ $event->timezone }} local time)@endif
**Where:** {{ $event->address ?? 'Location to be announced' }}

We'll send you reminders 3 days and 24 hours before it starts.

<x-mail::button :url="url('/events/'.$event->id)">
View event
</x-mail::button>

See you there,<br>
{{ config('app.name') }}
</x-mail::message>
