/**
 * Date/time helpers for events.
 *
 * Events are global, so we always render the wall-clock time at the venue's
 * location. We take the absolute unix start time and format it through Intl
 * with the event's IANA timezone, which is correct regardless of where the
 * viewer is.
 */

function toDate(unix: number | null): Date | null {
    return unix !== null ? new Date(unix * 1000) : null;
}

export function formatEventDate(
    unix: number | null,
    timezone: string | null,
    options: Intl.DateTimeFormatOptions = {},
): string {
    const date = toDate(unix);

    if (!date) {
        return 'Date to be announced';
    }

    return new Intl.DateTimeFormat(undefined, {
        timeZone: timezone || 'UTC',
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        ...options,
    }).format(date);
}

/** Short timezone label, e.g. "GMT+1", shown next to a local time. */
export function timezoneLabel(
    unix: number | null,
    timezone: string | null,
): string {
    const date = toDate(unix);

    if (!date || !timezone) {
        return '';
    }

    const parts = new Intl.DateTimeFormat(undefined, {
        timeZone: timezone,
        timeZoneName: 'short',
    }).formatToParts(date);

    return parts.find((p) => p.type === 'timeZoneName')?.value ?? '';
}

/** Friendly countdown relative to now, e.g. "In 3 days", "Today", "Ended". */
export function eventCountdown(unix: number | null): {
    label: string;
    soon: boolean;
    past: boolean;
} {
    const date = toDate(unix);

    if (!date) {
        return { label: '', soon: false, past: false };
    }

    const diffMs = date.getTime() - Date.now();
    const past = diffMs < 0;
    const days = Math.round(diffMs / 86_400_000);

    if (past) {
        return { label: 'Ended', soon: false, past: true };
    }

    if (days === 0) {
        return { label: 'Today', soon: true, past: false };
    }

    if (days === 1) {
        return { label: 'Tomorrow', soon: true, past: false };
    }

    if (days < 7) {
        return { label: `In ${days} days`, soon: true, past: false };
    }

    if (days < 30) {
        return {
            label: `In ${Math.round(days / 7)} weeks`,
            soon: false,
            past: false,
        };
    }

    return {
        label: `In ${Math.round(days / 30)} months`,
        soon: false,
        past: false,
    };
}

export function formatPrice(price: number | null, currency: string): string {
    if (price === null) {
        return '';
    }

    if (price === 0) {
        return 'Free';
    }

    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            maximumFractionDigits: 0,
        }).format(price);
    } catch {
        return `${currency} ${price.toFixed(0)}`;
    }
}
