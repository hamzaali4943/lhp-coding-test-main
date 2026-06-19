<?php

namespace App\Models;

use App\Support\EventImages;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property int $user_id
 * @property string $type
 * @property string $status
 * @property int|null $created_time
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $city
 * @property string|null $country
 * @property string|null $timezone
 * @property array<string, mixed>|null $payload
 * @property-read string|null $address
 * @property-read list<string> $images
 * @property-read string|null $starts_at_iso
 * @property-read int|null $attendees_count
 * @property-read User|null $user
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Attendee, $this> */
    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    /** The event start time as an immutable Carbon instance (UTC). */
    public function startsAt(): ?CarbonImmutable
    {
        return $this->created_time !== null
            ? CarbonImmutable::createFromTimestampUTC($this->created_time)
            : null;
    }

    /** Human-readable location, e.g. "Berlin, Germany". */
    public function getAddressAttribute(): ?string
    {
        if (! $this->city) {
            return null;
        }

        return $this->country ? "{$this->city}, {$this->country}" : $this->city;
    }

    /**
     * Local placeholder image URLs (always at least two).
     *
     * @return list<string>
     */
    public function getImagesAttribute(): array
    {
        return EventImages::for($this->id, $this->type);
    }

    /** Event start as an ISO-8601 string in the venue's local timezone. */
    public function getStartsAtIsoAttribute(): ?string
    {
        $startsAt = $this->startsAt();

        if ($startsAt === null) {
            return null;
        }

        return $startsAt
            ->setTimezone($this->timezone ?: 'UTC')
            ->toIso8601String();
    }
}
