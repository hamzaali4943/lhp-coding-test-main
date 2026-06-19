@php
    $name = $event->payload['name'] ?? 'your event';
    $when = $event->starts_at_iso ?? null;
@endphp

<x-mail::message>
# {{ $name }} starts in {{ $lead }}

Hi {{ $attendee->name }},

This is a friendly reminder that **{{ $name }}** is coming up in **{{ $lead }}**.

**When:** {{ $when ? \Illuminate\Support\Carbon::parse($when)->format('l, j F Y \a\t g:i A') : 'TBA' }}@if($event->timezone) ({{ $event->timezone }} local time)@endif
**Where:** {{ $event->address ?? 'Location to be announced' }}

<x-mail::button :url="url('/events/'.$event->id)">
View event details
</x-mail::button>

Looking forward to seeing you,<br>
{{ config('app.name') }}
</x-mail::message>
