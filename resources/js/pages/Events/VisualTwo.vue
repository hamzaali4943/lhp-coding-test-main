<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MapPin, X } from '@lucide/vue';
import type * as Leaflet from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import DatePicker from '@/components/events/DatePicker.vue';
import EventCard from '@/components/events/EventCard.vue';
import { useEventFeed } from '@/composables/useEventFeed';
import type { EventFacets, EventFilters, MapPoint } from '@/types/events';

const props = defineProps<{ facets: EventFacets; filters: EventFilters }>();

// Only date + category filters drive the map; location is the map itself.
const filters = reactive({
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    type: props.filters.type ?? '',
    status: props.filters.status ?? '',
});

const mapEl = ref<HTMLElement | null>(null);
const points = ref<MapPoint[]>([]);
const total = ref<number | null>(null);
const selectedCity = ref<MapPoint | null>(null);

// Leaflet is loaded client-side only (it touches `window` at import time, which
// breaks Inertia SSR), so we hold the module in a closure after onMounted.
let L: typeof Leaflet | null = null;
let map: Leaflet.Map | null = null;
let markerLayer: Leaflet.LayerGroup | null = null;
let debounce: ReturnType<typeof setTimeout> | null = null;

// City event list (side panel) reuses the shared paginated feed.
const cityFeed = useEventFeed('/events-visual-2/data');

function activeFilters(): Partial<EventFilters> {
    return { ...filters };
}

async function loadMarkers() {
    const params = new URLSearchParams();

    for (const [k, v] of Object.entries(filters)) {
        if (v) {
            params.set(k, String(v));
        }
    }

    const res = await fetch(`/events-visual-2/map?${params.toString()}`, {
        headers: { Accept: 'application/json' },
    });
    const payload = await res.json();
    points.value = payload.data;
    total.value = payload.total;
    drawMarkers();
}

function radiusFor(count: number, max: number): number {
    const scale = max > 0 ? Math.sqrt(count / max) : 0;

    return 8 + scale * 24;
}

function colorFor(count: number, max: number): string {
    const ratio = max > 0 ? count / max : 0;

    if (ratio > 0.66) {
        return '#e11d48';
    } // rose

    if (ratio > 0.33) {
        return '#f59e0b';
    } // amber

    return '#6366f1'; // indigo
}

function drawMarkers() {
    if (!map || !L) {
        return;
    }

    markerLayer = markerLayer ?? L.layerGroup().addTo(map);
    markerLayer.clearLayers();

    const max = points.value.reduce((m, p) => Math.max(m, p.count), 0);

    for (const point of points.value) {
        const color = colorFor(point.count, max);
        const marker = L.circleMarker([point.lat, point.lng], {
            radius: radiusFor(point.count, max),
            color,
            fillColor: color,
            fillOpacity: 0.55,
            weight: 2,
        });
        marker.bindTooltip(
            `${point.city} · ${point.count.toLocaleString()} events`,
            { direction: 'top' },
        );
        marker.on('click', () => selectCity(point));
        marker.addTo(markerLayer);
    }
}

function selectCity(point: MapPoint) {
    selectedCity.value = point;
    cityFeed.reset();
    cityFeed.loadMore({ ...activeFilters(), city: point.city });
    map?.panTo([point.lat, point.lng]);
}

function closePanel() {
    selectedCity.value = null;
}

function loadMoreCity() {
    if (selectedCity.value) {
        cityFeed.loadMore({
            ...activeFilters(),
            city: selectedCity.value.city,
        });
    }
}

watch(filters, () => {
    if (debounce) {
        clearTimeout(debounce);
    }

    debounce = setTimeout(() => {
        loadMarkers();

        if (selectedCity.value) {
            selectCity(selectedCity.value);
        }
    }, 300);
});

onMounted(async () => {
    if (!mapEl.value) {
        return;
    }

    L = await import('leaflet');

    map = L.map(mapEl.value, { worldCopyJump: true, minZoom: 2 }).setView(
        [25, 5],
        2,
    );
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);

    loadMarkers();
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <Head title="Events Visual 2" />

    <div class="relative flex h-[calc(100svh-1rem)] flex-col">
        <!-- Filter bar -->
        <div
            class="z-20 flex flex-wrap items-end gap-3 border-b bg-background px-6 py-3"
        >
            <div>
                <h1 class="text-lg font-semibold">Events map</h1>
                <p class="text-xs text-muted-foreground">
                    <span v-if="total !== null"
                        >{{ total.toLocaleString() }} events across
                        {{ points.length }} cities</span
                    >
                    <span v-else>Loading map…</span>
                </p>
            </div>
            <div class="ml-auto flex flex-wrap items-end gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-muted-foreground">From</label>
                    <DatePicker v-model="filters.from" placeholder="Any date" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-muted-foreground">To</label>
                    <DatePicker v-model="filters.to" placeholder="Any date" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-muted-foreground">Type</label>
                    <select
                        v-model="filters.type"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All types</option>
                        <option
                            v-for="t in facets.types"
                            :key="t"
                            :value="t"
                            class="capitalize"
                        >
                            {{ t }}
                        </option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-muted-foreground">Status</label>
                    <select
                        v-model="filters.status"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Any status</option>
                        <option
                            v-for="s in facets.statuses"
                            :key="s"
                            :value="s"
                            class="capitalize"
                        >
                            {{ s.replace('_', ' ') }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Map + side panel -->
        <div class="relative flex-1 overflow-hidden">
            <div ref="mapEl" class="absolute inset-0 z-0" />

            <Transition
                enter-from-class="translate-x-full"
                enter-active-class="transition-transform duration-300 ease-out"
                leave-to-class="translate-x-full"
                leave-active-class="transition-transform duration-200 ease-in"
            >
                <aside
                    v-if="selectedCity"
                    class="absolute top-0 right-0 z-10 flex h-full w-full flex-col border-l bg-background shadow-xl sm:w-100"
                >
                    <div
                        class="flex items-start justify-between gap-2 border-b p-4"
                    >
                        <div>
                            <h2
                                class="flex items-center gap-1.5 text-lg font-semibold"
                            >
                                <MapPin class="size-4" />
                                {{ selectedCity.city }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{ selectedCity.country }} ·
                                {{ selectedCity.count.toLocaleString() }} events
                            </p>
                        </div>
                        <button
                            class="rounded-md p-1 text-muted-foreground hover:bg-muted"
                            @click="closePanel"
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    <!-- Block flow (not flex-col) so cards keep their natural
                         height and the panel scrolls, rather than flex-shrinking
                         them into slivers. -->
                    <div class="flex-1 space-y-4 overflow-y-auto p-4">
                        <EventCard
                            v-for="event in cityFeed.rows.value"
                            :key="event.id"
                            :event="event"
                        />

                        <div
                            v-if="
                                cityFeed.loaded.value &&
                                cityFeed.rows.value.length === 0 &&
                                !cityFeed.loading.value
                            "
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No events here for these filters.
                        </div>

                        <button
                            v-if="
                                cityFeed.hasMore() &&
                                cityFeed.rows.value.length > 0
                            "
                            type="button"
                            class="rounded-md border py-2 text-sm hover:bg-muted disabled:opacity-50"
                            :disabled="cityFeed.loading.value"
                            @click="loadMoreCity"
                        >
                            {{
                                cityFeed.loading.value
                                    ? 'Loading…'
                                    : 'Load more'
                            }}
                        </button>
                    </div>
                </aside>
            </Transition>

            <div
                v-if="!selectedCity"
                class="pointer-events-none absolute bottom-4 left-1/2 z-10 -translate-x-1/2 rounded-full bg-background/90 px-4 py-2 text-sm text-muted-foreground shadow-md backdrop-blur"
            >
                Click a city to browse its events
            </div>
        </div>
    </div>
</template>
