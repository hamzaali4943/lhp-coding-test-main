<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Clock,
    MapPin,
    Tag,
    Ticket,
    Users,
} from '@lucide/vue';
import type * as Leaflet from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import RegisterInterest from '@/components/events/RegisterInterest.vue';
import { Badge } from '@/components/ui/badge';
import {
    eventCountdown,
    formatEventDate,
    formatPrice,
    timezoneLabel,
} from '@/composables/useEventTime';
import type { EventCard } from '@/types/events';

interface EventDetail {
    id: string;
    type: string;
    status: string;
    payload: Record<string, any>;
    user: { id: number; name: string } | null;
    attendees_count: number;
}

const props = defineProps<{ event: EventDetail; card: EventCard }>();

const active = ref(0);
const countdown = eventCountdown(props.card.starts_at_unix);
const mapEl = ref<HTMLElement | null>(null);
let map: Leaflet.Map | null = null;

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

onMounted(async () => {
    if (
        !mapEl.value ||
        props.card.latitude === null ||
        props.card.longitude === null
    ) {
        return;
    }

    const L = await import('leaflet');

    map = L.map(mapEl.value, {
        zoomControl: false,
        dragging: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
    }).setView([props.card.latitude, props.card.longitude], 11);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);

    L.circleMarker([props.card.latitude, props.card.longitude], {
        radius: 10,
        color: '#e11d48',
        fillColor: '#e11d48',
        fillOpacity: 0.6,
        weight: 2,
    }).addTo(map);
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <Head :title="card.name" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6">
        <Link
            href="/events-visual-1"
            class="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Back to events
        </Link>

        <!-- Gallery -->
        <div class="flex flex-col gap-3">
            <div
                class="relative aspect-21/9 overflow-hidden rounded-2xl bg-muted"
            >
                <img
                    :src="card.images[active]"
                    :alt="card.name"
                    class="h-full w-full object-cover"
                />
                <div class="absolute top-4 left-4 flex gap-2">
                    <Badge
                        :variant="statusVariant(event.status)"
                        class="capitalize"
                        >{{ event.status.replace('_', ' ') }}</Badge
                    >
                    <Badge
                        v-if="countdown.label"
                        class="bg-black/60 text-white"
                        >{{ countdown.label }}</Badge
                    >
                </div>
            </div>
            <div v-if="card.images.length > 1" class="flex gap-3">
                <button
                    v-for="(img, i) in card.images"
                    :key="img"
                    type="button"
                    class="relative h-16 w-24 overflow-hidden rounded-lg ring-2 transition"
                    :class="
                        i === active
                            ? 'ring-primary'
                            : 'opacity-70 ring-transparent hover:opacity-100'
                    "
                    @click="active = i"
                >
                    <img
                        :src="img"
                        :alt="`${card.name} ${i + 1}`"
                        class="h-full w-full object-cover"
                    />
                </button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Main details -->
            <div class="flex flex-col gap-5 lg:col-span-2">
                <div class="flex flex-col gap-2">
                    <Badge variant="outline" class="w-fit capitalize">{{
                        event.type
                    }}</Badge>
                    <h1 class="text-3xl leading-tight font-bold">
                        {{ card.name }}
                    </h1>
                    <p
                        v-if="event.payload.organizer?.name"
                        class="text-sm text-muted-foreground"
                    >
                        Organised by {{ event.payload.organizer.name }}
                    </p>
                </div>

                <p
                    v-if="card.description"
                    class="leading-relaxed text-muted-foreground"
                >
                    {{ card.description }}
                </p>

                <div class="overflow-hidden rounded-xl border">
                    <div ref="mapEl" class="h-56 w-full" />
                </div>
            </div>

            <!-- Sidebar: facts + register -->
            <aside class="flex flex-col gap-4 rounded-xl border bg-card p-5">
                <div class="flex items-start gap-3">
                    <CalendarDays class="mt-0.5 size-5 text-muted-foreground" />
                    <div>
                        <p class="font-medium">
                            {{
                                formatEventDate(
                                    card.starts_at_unix,
                                    card.timezone,
                                )
                            }}
                        </p>
                        <p
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Clock class="size-3" /> Local time
                            {{
                                timezoneLabel(
                                    card.starts_at_unix,
                                    card.timezone,
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <MapPin class="mt-0.5 size-5 text-muted-foreground" />
                    <div>
                        <p class="font-medium">
                            {{ card.address ?? 'Location to be announced' }}
                        </p>
                        <p
                            v-if="card.venue"
                            class="text-xs text-muted-foreground"
                        >
                            {{ card.venue }}
                        </p>
                    </div>
                </div>

                <div v-if="card.price !== null" class="flex items-center gap-3">
                    <Ticket class="size-5 text-muted-foreground" />
                    <p class="font-medium">
                        {{ formatPrice(card.price, card.currency) }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Users class="size-5 text-muted-foreground" />
                    <p class="font-medium">
                        {{ event.attendees_count }} attending
                    </p>
                </div>

                <div
                    v-if="event.payload.tags?.length"
                    class="flex flex-wrap items-center gap-1.5"
                >
                    <Tag class="size-4 text-muted-foreground" />
                    <Badge
                        v-for="tag in event.payload.tags"
                        :key="tag"
                        variant="secondary"
                        class="capitalize"
                        >{{ tag }}</Badge
                    >
                </div>

                <RegisterInterest
                    :event-id="event.id"
                    :event-name="card.name"
                    size="lg"
                    block
                />
            </aside>
        </div>
    </div>
</template>
