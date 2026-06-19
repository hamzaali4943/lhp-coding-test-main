<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarRange, Search, SlidersHorizontal } from '@lucide/vue';
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import DatePicker from '@/components/events/DatePicker.vue';
import EventCard from '@/components/events/EventCard.vue';
import { useEventFeed } from '@/composables/useEventFeed';
import type { EventFacets, EventFilters } from '@/types/events';

const props = defineProps<{ facets: EventFacets; filters: EventFilters }>();

const filters = reactive<EventFilters>({
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    city: props.filters.city ?? '',
    country: props.filters.country ?? '',
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    type: props.filters.type ?? '',
});

const { rows, total, loading, loaded, hasMore, loadMore, reset } = useEventFeed(
    '/events-visual-1/data',
);
const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;
let debounce: ReturnType<typeof setTimeout> | null = null;

function reload() {
    reset();
    loadMore(filters);
}

// Debounce filter changes so typing in the search box doesn't spam the server.
watch(
    filters,
    () => {
        if (debounce) {
            clearTimeout(debounce);
        }

        debounce = setTimeout(reload, 300);
    },
    { deep: true },
);

function clearFilters() {
    filters.from = '';
    filters.to = '';
    filters.city = '';
    filters.country = '';
    filters.q = '';
    filters.status = '';
    filters.type = '';
}

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting) {
                loadMore(filters);
            }
        },
        { rootMargin: '600px' },
    );

    if (sentinel.value) {
        observer.observe(sentinel.value);
    }

    loadMore(filters);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head title="Events Visual 1" />

    <div class="flex flex-col">
        <!-- Gallery header -->
        <header
            class="relative overflow-hidden border-b bg-linear-to-br from-violet-600 via-fuchsia-600 to-rose-500 px-6 py-10 text-white"
        >
            <div class="relative z-10">
                <p
                    class="text-sm font-medium tracking-wider text-white/80 uppercase"
                >
                    Browse events
                </p>
                <h1 class="mt-1 text-3xl font-bold">
                    Discover what's on, anywhere
                </h1>
                <p class="mt-2 max-w-xl text-white/85">
                    A visual gallery of upcoming events worldwide. Filter by
                    date and location to find your next night out.
                </p>
            </div>
            <div
                class="pointer-events-none absolute -top-16 -right-16 h-64 w-64 rounded-full bg-white/10"
            />
            <div
                class="pointer-events-none absolute right-32 -bottom-20 h-48 w-48 rounded-full bg-white/10"
            />
        </header>

        <!-- Filter bar -->
        <div
            class="sticky top-0 z-20 border-b bg-background/80 px-6 py-3 backdrop-blur"
        >
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex min-w-50 flex-1 flex-col gap-1">
                    <label
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                        ><Search class="size-3" /> Location</label
                    >
                    <input
                        v-model="filters.q"
                        type="text"
                        placeholder="Search city or country…"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                        ><CalendarRange class="size-3" /> From</label
                    >
                    <DatePicker v-model="filters.from" placeholder="Any date" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-muted-foreground">To</label>
                    <DatePicker v-model="filters.to" placeholder="Any date" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-muted-foreground">City</label>
                    <select
                        v-model="filters.city"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All cities</option>
                        <option
                            v-for="c in facets.cities"
                            :key="c.city"
                            :value="c.city"
                        >
                            {{ c.city }}
                        </option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                        ><SlidersHorizontal class="size-3" /> Type</label
                    >
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
                <button
                    type="button"
                    class="h-9 rounded-md px-3 text-sm text-muted-foreground hover:text-foreground"
                    @click="clearFilters"
                >
                    Clear
                </button>
            </div>
        </div>

        <!-- Results -->
        <div class="px-6 py-6">
            <p class="mb-4 text-sm text-muted-foreground">
                <span v-if="total !== null"
                    >{{ total.toLocaleString() }} events</span
                >
                <span v-else>Loading…</span>
            </p>

            <TransitionGroup
                tag="div"
                class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                enter-from-class="opacity-0 translate-y-3"
                enter-active-class="transition duration-300 ease-out"
            >
                <EventCard
                    v-for="event in rows"
                    :key="event.id"
                    :event="event"
                />
            </TransitionGroup>

            <div
                v-if="loaded && rows.length === 0 && !loading"
                class="py-20 text-center text-muted-foreground"
            >
                No events match your filters.
            </div>

            <div ref="sentinel" class="h-px" />

            <div class="py-6 text-center text-sm text-muted-foreground">
                <span v-if="loading">Loading more…</span>
                <span v-else-if="loaded && !hasMore()"
                    >You've reached the end.</span
                >
            </div>
        </div>
    </div>
</template>
