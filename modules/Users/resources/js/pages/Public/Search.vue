<script setup lang="ts">
import SiteLayout from '@/layouts/SiteLayout.vue';
import { Map as MapIcon, SearchX, SlidersHorizontal, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import CoachCard from '../../components/public/CoachCard.vue';
import CoachCardSkeleton from '../../components/public/CoachCardSkeleton.vue';
import FilterDrawer from '../../components/public/FilterDrawer.vue';
import FilterSidebar from '../../components/public/FilterSidebar.vue';
import MapView from '../../components/public/MapView.vue';
import SearchHero from '../../components/public/SearchHero.vue';
import { useCoachSearch } from '../../composables/useCoachSearch';
import { useInfiniteScroll } from '../../composables/useInfiniteScroll';

const {
    coaches,
    loading,
    hasMore,
    meta,
    filters,
    fetchNextPage,
    resetFilters,
    init,
} = useCoachSearch();
const { sentinelRef } = useInfiniteScroll(fetchNextPage, hasMore);

const filterDrawerOpen = ref(false);
const showMap = ref(false);

const sortOptions = [
    { label: 'Rilevanza', value: 'relevance' },
    { label: 'Valutazione', value: 'rating_desc' },
    { label: 'Prezzo ↑', value: 'price_asc' },
    { label: 'Prezzo ↓', value: 'price_desc' },
];

const totalCount = computed(() => meta.value?.total ?? 0);

const modeLabels: Record<string, string> = {
    in_person: 'In sede',
    online: 'Online',
    hybrid: 'Ibrido',
};
const genderLabels: Record<string, string> = {
    male: 'Uomo',
    female: 'Donna',
};
const slotLabels: Record<string, string> = {
    mattina: 'Mattina',
    pomeriggio: 'Pomeriggio',
    sera: 'Sera',
};
const dayLabels: Record<number, string> = {
    1: 'Lunedì',
    2: 'Martedì',
    3: 'Mercoledì',
    4: 'Giovedì',
    5: 'Venerdì',
    6: 'Sabato',
    0: 'Domenica',
};

const activeFilterCount = computed(() => {
    let count = 0;
    if (filters.mode.length) count++;
    if (filters.price_min !== null || filters.price_max !== null) count++;
    if (filters.specialization.length) count++;
    if (filters.gender) count++;
    if (filters.rating_min) count++;
    if (filters.available_days.length) count++;
    if (filters.time_slots.length) count++;
    return count;
});

interface ActiveChip {
    id: string;
    label: string;
    remove: () => void;
}

const availableSpecs = ref<{ id: number; name: string; slug: string }[]>([]);
const specName = (slug: string) =>
    availableSpecs.value.find((s) => s.slug === slug)?.name ?? slug;

const removeChipValue = (
    key: 'mode' | 'specialization' | 'available_days' | 'time_slots',
    value: string | number,
) => {
    const current = [...filters[key]] as (string | number)[];
    const idx = current.indexOf(value);
    if (idx >= 0) current.splice(idx, 1);
    Object.assign(filters, { [key]: current });
};

const activeChips = computed<ActiveChip[]>(() => {
    const chips: ActiveChip[] = [];

    filters.mode.forEach((m) =>
        chips.push({
            id: `mode-${m}`,
            label: modeLabels[m] ?? m,
            remove: () => removeChipValue('mode', m),
        }),
    );

    const priceMin = filters.price_min ?? 0;
    const priceMax = filters.price_max ?? 200;
    if (priceMin > 0 || priceMax < 200) {
        const label =
            priceMin > 0 && priceMax < 200
                ? `€${priceMin}–€${priceMax}`
                : priceMin > 0
                  ? `da €${priceMin}`
                  : `fino a €${priceMax}`;
        chips.push({
            id: 'price',
            label,
            remove: () =>
                Object.assign(filters, {
                    price_min: null,
                    price_max: null,
                }),
        });
    }

    filters.specialization.forEach((slug) =>
        chips.push({
            id: `spec-${slug}`,
            label: specName(slug),
            remove: () => removeChipValue('specialization', slug),
        }),
    );

    if (filters.gender)
        chips.push({
            id: 'gender',
            label: genderLabels[filters.gender] ?? filters.gender,
            remove: () => Object.assign(filters, { gender: null }),
        });

    if (filters.rating_min)
        chips.push({
            id: 'rating',
            label: `${filters.rating_min}+ ★`,
            remove: () => Object.assign(filters, { rating_min: null }),
        });

    filters.available_days.forEach((d) =>
        chips.push({
            id: `day-${d}`,
            label: dayLabels[d] ?? String(d),
            remove: () => removeChipValue('available_days', d),
        }),
    );

    filters.time_slots.forEach((t) =>
        chips.push({
            id: `slot-${t}`,
            label: slotLabels[t] ?? t,
            remove: () => removeChipValue('time_slots', t),
        }),
    );

    return chips;
});

const handleSearch = ({ search, city }: { search: string; city: string }) => {
    filters.search = search;
    filters.city = city;
};

onMounted(() => {
    init();
});

onMounted(async () => {
    try {
        const res = await fetch('/api/v1/specializations');
        if (res.ok) {
            availableSpecs.value = await res.json();
        }
    } catch {
        /* noop */
    }
});
</script>

<template>
    <SiteLayout
        title="Cerca Coach - MioCoach"
        description="Trova il personal trainer ideale per le tue esigenze. Filtra per specializzazione, città, prezzo e disponibilità."
    >
        <!-- Floating Search Bar -->
        <div class="pt-[88px] pb-5">
            <SearchHero
                :initial-search="filters.search"
                :initial-city="filters.city"
                @search="handleSearch"
            />
        </div>

        <!-- Main Content -->
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex gap-6">
                <!-- Desktop Filters -->
                <div class="hidden lg:block">
                    <div class="sticky top-20">
                        <FilterSidebar
                            :filters="filters"
                            @update:filters="Object.assign(filters, $event)"
                        />
                    </div>
                </div>

                <!-- Results Column -->
                <div class="min-w-0 flex-1">
                    <!-- Top Bar -->
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <!-- Mobile filter toggle -->
                            <button
                                type="button"
                                class="relative flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 lg:hidden dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                @click="filterDrawerOpen = true"
                            >
                                <SlidersHorizontal :size="16" />
                                Filtri
                                <span
                                    v-if="activeFilterCount > 0"
                                    class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-teal-500 text-[10px] font-bold text-white"
                                >
                                    {{ activeFilterCount }}
                                </span>
                            </button>

                            <p
                                v-if="meta"
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                <span
                                    class="font-semibold text-gray-900 dark:text-white"
                                    >{{ totalCount }}</span
                                >
                                coach
                                {{ totalCount === 1 ? 'trovato' : 'trovati' }}
                            </p>
                        </div>

                        <!-- Sort -->
                        <select
                            v-model="filters.sort"
                            class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <option
                                v-for="opt in sortOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Active filter chips -->
                    <div
                        v-if="activeChips.length"
                        class="mb-4 flex flex-wrap items-center gap-2"
                        data-testid="active-filter-chips"
                    >
                        <button
                            v-for="chip in activeChips"
                            :key="chip.id"
                            type="button"
                            class="flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            :data-testid="`chip-${chip.id}`"
                            @click="chip.remove()"
                        >
                            {{ chip.label }}
                            <X :size="12" class="text-gray-400" />
                        </button>
                        <button
                            type="button"
                            class="ml-1 flex items-center gap-1 text-xs font-semibold text-teal-600 transition-colors hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300"
                            data-testid="reset-filters"
                            @click="resetFilters"
                        >
                            Azzera filtri
                        </button>
                    </div>

                    <!-- Loading skeletons -->
                    <div
                        v-if="loading && coaches.length === 0"
                        class="space-y-4"
                    >
                        <CoachCardSkeleton v-for="i in 5" :key="i" />
                    </div>

                    <!-- Results list -->
                    <div v-else-if="coaches.length" class="space-y-4">
                        <CoachCard
                            v-for="coach in coaches"
                            :key="coach.id"
                            :coach="coach"
                        />

                        <!-- Infinite scroll sentinel -->
                        <div
                            ref="sentinelRef"
                            class="h-4"
                            data-testid="search-sentinel"
                        />

                        <!-- Loading more -->
                        <div v-if="loading" class="py-6 text-center">
                            <div
                                class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                            >
                                <div
                                    class="h-4 w-4 animate-spin rounded-full border-2 border-teal-500 border-t-transparent"
                                />
                                Caricamento...
                            </div>
                        </div>

                        <!-- End of results -->
                        <div
                            v-if="!hasMore && !loading"
                            class="py-6 text-center text-sm text-gray-400 dark:text-gray-500"
                        >
                            Hai visto tutti i risultati
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-20"
                    >
                        <div
                            class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"
                        >
                            <SearchX
                                :size="28"
                                class="text-gray-400 dark:text-gray-500"
                            />
                        </div>
                        <h3
                            class="mb-2 text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Nessun coach trovato
                        </h3>
                        <p
                            class="max-w-sm text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            Prova a modificare i filtri di ricerca o a cercare
                            in un'altra zona
                        </p>
                        <button
                            v-if="activeChips.length > 0"
                            type="button"
                            class="mt-4 rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-teal-700"
                            data-testid="reset-filters-empty"
                            @click="resetFilters"
                        >
                            Azzera filtri
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating Map Button -->
        <button
            v-if="coaches.length > 0"
            type="button"
            class="fixed bottom-6 left-1/2 z-40 flex -translate-x-1/2 items-center gap-2 rounded-full bg-gray-900 px-5 py-3 text-sm font-medium text-white shadow-[0_8px_32px_rgba(0,0,0,0.2)] transition-transform hover:scale-105 dark:bg-white dark:text-gray-900"
            @click="showMap = true"
        >
            <MapIcon :size="16" />
            Vedi sulla Mappa
        </button>

        <!-- Map Modal -->
        <MapView v-if="showMap" :coaches="coaches" @close="showMap = false" />

        <!-- Mobile Filter Drawer -->
        <FilterDrawer
            :open="filterDrawerOpen"
            :filters="filters"
            @update:open="filterDrawerOpen = $event"
            @update:filters="Object.assign(filters, $event)"
        />
    </SiteLayout>
</template>
