import { reactive, ref, watch } from 'vue';
import type {
    CoachListingItem,
    PaginationMeta,
    SearchFilters,
} from '../types/public';

const PRICE_FLOOR = 0;
const PRICE_CEIL = 200;

const defaultFilters = (): SearchFilters => ({
    search: '',
    city: '',
    mode: [],
    price_min: null,
    price_max: null,
    specialization: [],
    gender: null,
    rating_min: null,
    available_days: [],
    time_slots: [],
    sort: 'relevance',
});

export function useCoachSearch() {
    const coaches = ref<CoachListingItem[]>([]);
    const loading = ref(false);
    const hasMore = ref(true);
    const meta = ref<PaginationMeta | null>(null);
    const initialized = ref(false);

    const filters = reactive<SearchFilters>(defaultFilters());

    let currentPage = 1;
    let abortController: AbortController | null = null;

    const buildQueryString = (page: number): string => {
        const params = new URLSearchParams();
        params.set('page', String(page));
        params.set('limit', '20');

        if (filters.search) params.set('search', filters.search);
        if (filters.city) params.set('city', filters.city);
        if (filters.mode.length) {
            filters.mode.forEach((m) => params.append('mode[]', m));
        }
        if (filters.price_min !== null && filters.price_min > PRICE_FLOOR)
            params.set('price_min', String(filters.price_min));
        if (filters.price_max !== null && filters.price_max < PRICE_CEIL)
            params.set('price_max', String(filters.price_max));
        if (filters.specialization.length) {
            filters.specialization.forEach((s) =>
                params.append('specialization[]', s),
            );
        }
        if (filters.gender) params.set('gender', filters.gender);
        if (filters.rating_min !== null)
            params.set('rating_min', String(filters.rating_min));
        if (filters.available_days.length) {
            filters.available_days.forEach((d) =>
                params.append('available_days[]', String(d)),
            );
        }
        if (filters.time_slots.length) {
            filters.time_slots.forEach((t) => params.append('time_slots[]', t));
        }
        if (filters.sort && filters.sort !== 'relevance')
            params.set('sort', filters.sort);

        return params.toString();
    };

    const consumeInitialParams = () => {
        const params = new URLSearchParams(window.location.search);
        if (params.has('q')) filters.search = params.get('q') ?? '';
        if (params.has('city')) filters.city = params.get('city') ?? '';
        if (params.toString()) {
            window.history.replaceState(
                window.history.state,
                '',
                window.location.pathname,
            );
        }
    };

    const fetchCoaches = async (append = false, force = false) => {
        if (loading.value && !force) return;

        abortController?.abort();
        const request = new AbortController();
        abortController = request;

        loading.value = true;
        try {
            const page = append ? currentPage + 1 : 1;
            const response = await fetch(
                `/api/v1/coaches?${buildQueryString(page)}`,
                {
                    signal: request.signal,
                },
            );

            if (!response.ok) return;

            const json = await response.json();
            const items: CoachListingItem[] = json.data ?? [];

            if (append) {
                coaches.value = [...coaches.value, ...items];
            } else {
                coaches.value = items;
            }

            currentPage = json.meta?.current_page ?? page;
            meta.value = json.meta ?? null;
            hasMore.value = !!json.links?.next;
        } catch (err: unknown) {
            if (err instanceof Error && err.name === 'AbortError') return;
        } finally {
            if (abortController === request) {
                loading.value = false;
            }
        }
    };

    const fetchNextPage = async () => {
        await fetchCoaches(true);
    };

    const resetFilters = () => {
        const next = defaultFilters();
        next.search = filters.search;
        next.city = filters.city;
        Object.assign(filters, next);
    };

    const resetAndFetch = async () => {
        currentPage = 1;
        hasMore.value = true;
        coaches.value = [];
        await fetchCoaches(false, true);
    };

    let filterDebounce: ReturnType<typeof setTimeout> | null = null;
    watch(
        filters,
        () => {
            if (!initialized.value) return;
            if (filterDebounce) clearTimeout(filterDebounce);
            filterDebounce = setTimeout(() => resetAndFetch(), 300);
        },
        { deep: true },
    );

    const init = () => {
        consumeInitialParams();
        initialized.value = true;
        fetchCoaches(false);
    };

    return {
        coaches,
        loading,
        hasMore,
        meta,
        filters,
        fetchCoaches,
        fetchNextPage,
        resetAndFetch,
        resetFilters,
        init,
    };
}
