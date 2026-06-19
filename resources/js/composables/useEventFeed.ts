import { ref } from 'vue';
import type { EventCard, EventFilters } from '@/types/events';

interface FeedResponse {
    data: EventCard[];
    current_page: number;
    last_page: number;
    total: number;
    stats: { ms: number };
}

/** Drop empty filter values so they don't appear in the query string. */
function toParams(filters: Partial<EventFilters>): Record<string, string> {
    const params: Record<string, string> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value !== null && value !== undefined && value !== '') {
            params[key] = String(value);
        }
    }

    return params;
}

/**
 * Paginated, filterable event feed with infinite-scroll semantics. Shared by
 * the card grid (Visual 1) and the map's city panel (Visual 2).
 */
export function useEventFeed(endpoint: string) {
    const rows = ref<EventCard[]>([]);
    const page = ref(0);
    const lastPage = ref<number | null>(null);
    const total = ref<number | null>(null);
    const loading = ref(false);
    const ms = ref(0);
    const loaded = ref(false);

    const hasMore = () =>
        lastPage.value === null || page.value < lastPage.value;

    async function loadMore(filters: Partial<EventFilters> = {}) {
        if (loading.value || !hasMore()) {
            return;
        }

        loading.value = true;

        const params = new URLSearchParams({
            page: String(page.value + 1),
            ...toParams(filters),
        });

        try {
            const response = await fetch(`${endpoint}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            const payload: FeedResponse = await response.json();

            rows.value.push(...payload.data);
            page.value = payload.current_page;
            lastPage.value = payload.last_page;
            total.value = payload.total;
            ms.value = payload.stats.ms;
            loaded.value = true;
        } finally {
            loading.value = false;
        }
    }

    function reset() {
        rows.value = [];
        page.value = 0;
        lastPage.value = null;
        total.value = null;
        loaded.value = false;
    }

    return {
        rows,
        page,
        lastPage,
        total,
        loading,
        ms,
        loaded,
        hasMore,
        loadMore,
        reset,
    };
}
