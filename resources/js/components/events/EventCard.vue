<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Calendar, MapPin, Users } from '@lucide/vue';
import { ref } from 'vue';
import RegisterInterest from '@/components/events/RegisterInterest.vue';
import { Badge } from '@/components/ui/badge';
import {
    eventCountdown,
    formatEventDate,
    formatPrice,
    timezoneLabel,
} from '@/composables/useEventTime';
import type { EventCard } from '@/types/events';

const props = defineProps<{ event: EventCard }>();

const active = ref(0);
const countdown = eventCountdown(props.event.starts_at_unix);

const statusVariant = (status: string) => {
    switch (status) {
        case 'published':
            return 'default';
        case 'cancelled':
            return 'destructive';
        case 'sold_out':
            return 'secondary';
        default:
            return 'outline';
    }
};
</script>

<template>
    <Link
        :href="`/events/${event.id}`"
        class="group flex flex-col overflow-hidden rounded-xl border bg-card text-card-foreground shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
    >
        <!-- Image with hover-zoom + thumbnail dots for the 2+ images -->
        <div class="relative aspect-16/10 overflow-hidden bg-muted">
            <img
                :src="event.images[active]"
                :alt="event.name"
                loading="lazy"
                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            />
            <div class="absolute top-3 left-3 flex gap-2">
                <Badge
                    :variant="statusVariant(event.status)"
                    class="capitalize backdrop-blur"
                >
                    {{ event.status.replace('_', ' ') }}
                </Badge>
            </div>
            <div
                v-if="countdown.label"
                class="absolute top-3 right-3 rounded-full px-2.5 py-1 text-xs font-medium text-white backdrop-blur"
                :class="
                    countdown.past
                        ? 'bg-black/40'
                        : countdown.soon
                          ? 'bg-rose-500/90'
                          : 'bg-black/50'
                "
            >
                {{ countdown.label }}
            </div>

            <div
                v-if="event.images.length > 1"
                class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5"
            >
                <button
                    v-for="(img, i) in event.images"
                    :key="img"
                    type="button"
                    class="h-1.5 rounded-full transition-all"
                    :class="
                        i === active
                            ? 'w-5 bg-white'
                            : 'w-1.5 bg-white/60 hover:bg-white/80'
                    "
                    :aria-label="`Image ${i + 1}`"
                    @click.prevent.stop="active = i"
                />
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <div class="flex items-center justify-between gap-2">
                <Badge variant="outline" class="capitalize">{{
                    event.type
                }}</Badge>
                <span v-if="event.price !== null" class="text-sm font-semibold">
                    {{ formatPrice(event.price, event.currency) }}
                </span>
            </div>

            <h3 class="line-clamp-2 text-base leading-snug font-semibold">
                {{ event.name }}
            </h3>

            <p
                v-if="event.description"
                class="line-clamp-2 text-sm text-muted-foreground"
            >
                {{ event.description }}
            </p>

            <div
                class="mt-auto flex flex-col gap-1.5 text-sm text-muted-foreground"
            >
                <span class="flex items-center gap-1.5">
                    <MapPin class="size-4 shrink-0" />
                    {{ event.address ?? 'Location TBA' }}
                    <span v-if="event.venue" class="truncate"
                        >· {{ event.venue }}</span
                    >
                </span>
                <span class="flex items-center gap-1.5">
                    <Calendar class="size-4 shrink-0" />
                    {{
                        formatEventDate(event.starts_at_unix, event.timezone, {
                            year: undefined,
                        })
                    }}
                    <span class="text-xs">{{
                        timezoneLabel(event.starts_at_unix, event.timezone)
                    }}</span>
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 pt-1">
                <span
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <Users class="size-3.5" />
                    {{ event.attendees_count }} going
                </span>
                <RegisterInterest
                    :event-id="event.id"
                    :event-name="event.name"
                />
            </div>
        </div>
    </Link>
</template>
