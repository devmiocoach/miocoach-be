<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import type { SearchFilters } from '../../types/public';
import DayPicker from './DayPicker.vue';
import PriceRangeSlider from './PriceRangeSlider.vue';
import TimeSlotPicker from './TimeSlotPicker.vue';

const props = defineProps<{
    filters: SearchFilters;
}>();

const emit = defineEmits<{
    'update:filters': [SearchFilters];
}>();

const availableSpecs = ref<{ id: number; name: string; slug: string }[]>([]);
const specSearch = ref('');
const filteredSpecs = computed(() => {
    const q = specSearch.value.trim().toLowerCase();
    if (!q) return availableSpecs.value;
    return availableSpecs.value.filter((s) => s.name.toLowerCase().includes(q));
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

const update = <K extends keyof SearchFilters>(
    key: K,
    value: SearchFilters[K],
) => {
    emit('update:filters', { ...props.filters, [key]: value });
};

const toggleMode = (mode: string) => {
    const current = [...props.filters.mode];
    const idx = current.indexOf(mode);
    if (idx >= 0) current.splice(idx, 1);
    else current.push(mode);
    update('mode', current);
};

const toggleSpec = (spec: string) => {
    const current = [...props.filters.specialization];
    const idx = current.indexOf(spec);
    if (idx >= 0) current.splice(idx, 1);
    else current.push(spec);
    update('specialization', current);
};

const modeOptions = [
    { label: 'In Presenza', value: 'in_person' },
    { label: 'Online', value: 'online' },
    { label: 'Ibrido', value: 'hybrid' },
];

const ratingOptions = [
    { label: '4.5+', value: 4.5 },
    { label: '4.0+', value: 4.0 },
];

const genderOptions = [
    { label: 'Uomo', value: 'male' },
    { label: 'Donna', value: 'female' },
];
</script>

<template>
    <aside
        class="w-64 shrink-0 rounded-xl border border-gray-100 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        data-testid="filter-sidebar"
    >
        <h2
            class="mb-5 text-sm font-bold tracking-wide text-gray-900 uppercase dark:text-white"
        >
            Filtra Risultati
        </h2>

        <div class="space-y-6">
            <!-- Prezzo -->
            <div>
                <h3
                    class="mb-3 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    Prezzo a sessione
                </h3>
                <PriceRangeSlider
                    :model-value="[filters.price_min, filters.price_max]"
                    :min="0"
                    :max="200"
                    @update:model-value="
                        ([min, max]) => {
                            update('price_min', min);
                            update('price_max', max);
                        }
                    "
                />
            </div>

            <!-- Modalita -->
            <div>
                <h3
                    class="mb-3 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    Modalità
                </h3>
                <div class="space-y-2.5">
                    <label
                        v-for="option in modeOptions"
                        :key="option.value"
                        class="flex cursor-pointer items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300"
                    >
                        <input
                            type="checkbox"
                            :checked="filters.mode.includes(option.value)"
                            class="h-4 w-4 rounded border-gray-300 text-teal-500 focus:ring-teal-500 dark:border-gray-600"
                            @change="toggleMode(option.value)"
                        />
                        {{ option.label }}
                    </label>
                </div>
            </div>

            <!-- Specializzazione -->
            <div v-if="availableSpecs.length">
                <h3
                    class="mb-3 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    Specialità
                </h3>
                <input
                    v-if="availableSpecs.length > 10"
                    v-model="specSearch"
                    type="text"
                    placeholder="Cerca specialità..."
                    autocomplete="off"
                    class="mb-2.5 w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 placeholder:text-gray-400 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:placeholder:text-gray-500"
                    data-testid="spec-search"
                />
                <div class="max-h-40 space-y-2.5 overflow-y-auto">
                    <label
                        v-for="spec in filteredSpecs"
                        :key="spec.id"
                        class="flex cursor-pointer items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300"
                    >
                        <input
                            type="checkbox"
                            :checked="
                                filters.specialization.includes(spec.slug)
                            "
                            class="h-4 w-4 rounded border-gray-300 text-teal-500 focus:ring-teal-500 dark:border-gray-600"
                            @change="toggleSpec(spec.slug)"
                        />
                        {{ spec.name }}
                    </label>
                    <p
                        v-if="!filteredSpecs.length"
                        class="text-sm text-gray-400 dark:text-gray-500"
                    >
                        Nessuna specialità trovata
                    </p>
                </div>
            </div>

            <!-- Giorni -->
            <div>
                <h3
                    class="mb-2 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    Giorni
                </h3>
                <DayPicker
                    :model-value="filters.available_days"
                    @update:model-value="(v) => update('available_days', v)"
                />
            </div>

            <!-- Fasce Orarie -->
            <div>
                <h3
                    class="mb-2 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    Fascia oraria
                </h3>
                <TimeSlotPicker
                    :model-value="filters.time_slots"
                    @update:model-value="(v) => update('time_slots', v)"
                />
            </div>

            <!-- Valutazione -->
            <div>
                <h3
                    class="mb-3 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    Valutazione
                </h3>
                <div class="space-y-2.5">
                    <label
                        v-for="option in ratingOptions"
                        :key="option.value"
                        class="flex cursor-pointer items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300"
                    >
                        <input
                            type="checkbox"
                            :checked="filters.rating_min === option.value"
                            class="h-4 w-4 rounded border-gray-300 text-teal-500 focus:ring-teal-500 dark:border-gray-600"
                            @change="
                                update(
                                    'rating_min',
                                    filters.rating_min === option.value
                                        ? null
                                        : option.value,
                                )
                            "
                        />
                        {{ option.label }} ★
                    </label>
                </div>
            </div>

            <!-- Genere -->
            <div>
                <h3
                    class="mb-3 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    Genere
                </h3>
                <div class="flex gap-2">
                    <button
                        v-for="option in genderOptions"
                        :key="option.value"
                        type="button"
                        class="flex-1 rounded-lg border px-3 py-2 text-sm font-medium transition-colors"
                        :class="
                            filters.gender === option.value
                                ? 'border-teal-500 bg-teal-500 text-white'
                                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-gray-600'
                        "
                        @click="
                            update(
                                'gender',
                                filters.gender === option.value
                                    ? null
                                    : option.value,
                            )
                        "
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>
        </div>
    </aside>
</template>
