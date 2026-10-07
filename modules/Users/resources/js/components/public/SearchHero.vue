<script setup lang="ts">
import { Dumbbell, Loader2, MapPin, Search, X } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import { useCityAutocomplete } from '../../composables/useCityAutocomplete';
import { useSpecializationAutocomplete } from '../../composables/useSpecializationAutocomplete';

const props = defineProps<{
    initialSearch?: string;
    initialCity?: string;
}>();

const emit = defineEmits<{
    search: [{ search: string; city: string }];
}>();

const {
    query: specQuery,
    suggestions: specSuggestions,
    isOpen: specOpen,
    isLoading: specLoading,
    selectSuggestion: selectSpec,
    setQuerySilent: setSpecSilent,
    onFocus: onSpecFocus,
    close: closeSpec,
} = useSpecializationAutocomplete();

const {
    query: cityQuery,
    suggestions: citySuggestions,
    isOpen: cityOpen,
    isLoading: cityLoading,
    selectSuggestion: selectCity,
    setQuerySilent: setCitySilent,
    onFocus: onCityFocus,
    close: closeCity,
} = useCityAutocomplete();

const specActiveIdx = ref(-1);
const cityActiveIdx = ref(-1);

onMounted(() => {
    if (props.initialSearch) setSpecSilent(props.initialSearch);
    if (props.initialCity) setCitySilent(props.initialCity);
});

watch(
    () => props.initialSearch,
    (val) => {
        if (val !== undefined) setSpecSilent(val);
    },
);
watch(
    () => props.initialCity,
    (val) => {
        if (val !== undefined) setCitySilent(val);
    },
);

watch(specSuggestions, () => {
    specActiveIdx.value = -1;
});
watch(citySuggestions, () => {
    cityActiveIdx.value = -1;
});

const handleSearch = () => {
    closeSpec();
    closeCity();
    emit('search', { search: specQuery.value, city: cityQuery.value });
};

const clearSpec = () => {
    setSpecSilent('');
    handleSearch();
};

const clearCity = () => {
    setCitySilent('');
    handleSearch();
};

const handleSelectSpec = (suggestion: (typeof specSuggestions.value)[0]) => {
    selectSpec(suggestion);
    emit('search', { search: specQuery.value, city: cityQuery.value });
};

const handleSelectCity = (suggestion: (typeof citySuggestions.value)[0]) => {
    selectCity(suggestion);
    emit('search', { search: specQuery.value, city: cityQuery.value });
};

const closeSpecSoon = () => {
    setTimeout(() => closeSpec(), 150);
};

const closeCitySoon = () => {
    setTimeout(() => closeCity(), 150);
};

const onSpecKeydown = (e: KeyboardEvent) => {
    if (!specOpen.value || !specSuggestions.value.length) {
        if (e.key === 'Enter') handleSearch();
        return;
    }
    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault();
            specActiveIdx.value = Math.min(
                specActiveIdx.value + 1,
                specSuggestions.value.length - 1,
            );
            break;
        case 'ArrowUp':
            e.preventDefault();
            specActiveIdx.value = Math.max(specActiveIdx.value - 1, -1);
            break;
        case 'Enter':
            e.preventDefault();
            if (specActiveIdx.value >= 0) {
                handleSelectSpec(specSuggestions.value[specActiveIdx.value]);
            } else {
                handleSearch();
            }
            break;
        case 'Escape':
            closeSpec();
            break;
    }
};

const onCityKeydown = (e: KeyboardEvent) => {
    if (!cityOpen.value || !citySuggestions.value.length) {
        if (e.key === 'Enter') handleSearch();
        return;
    }
    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault();
            cityActiveIdx.value = Math.min(
                cityActiveIdx.value + 1,
                citySuggestions.value.length - 1,
            );
            break;
        case 'ArrowUp':
            e.preventDefault();
            cityActiveIdx.value = Math.max(cityActiveIdx.value - 1, -1);
            break;
        case 'Enter':
            e.preventDefault();
            if (cityActiveIdx.value >= 0) {
                handleSelectCity(citySuggestions.value[cityActiveIdx.value]);
            } else {
                handleSearch();
            }
            break;
        case 'Escape':
            closeCity();
            break;
    }
};
</script>

<template>
    <div class="mx-auto w-full max-w-4xl px-4">
        <div
            class="flex flex-col rounded-2xl border border-gray-200 bg-white shadow-lg focus-within:border-teal-500 focus-within:ring-2 focus-within:ring-teal-500/40 sm:flex-row dark:border-gray-800 dark:bg-gray-900"
        >
            <!-- Specialization -->
            <div
                class="relative flex h-14 flex-1 items-center gap-2.5 border-b border-gray-200 px-4 sm:border-r sm:border-b-0 sm:px-5 dark:border-gray-800"
            >
                <Dumbbell :size="18" class="shrink-0 text-teal-500" />
                <input
                    v-model="specQuery"
                    type="text"
                    placeholder="Specialità o nome"
                    autocomplete="off"
                    class="w-full bg-transparent text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none dark:text-white dark:placeholder:text-gray-500"
                    data-testid="search-specialization"
                    @focus="onSpecFocus"
                    @keydown="onSpecKeydown"
                    @blur="closeSpecSoon"
                />
                <button
                    v-if="specQuery"
                    type="button"
                    class="shrink-0 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    data-testid="clear-search-specialization"
                    aria-label="Svuota campo specialità"
                    @mousedown.prevent="clearSpec"
                >
                    <X :size="14" />
                </button>
                <Loader2
                    v-else-if="specLoading"
                    :size="14"
                    class="shrink-0 animate-spin text-gray-400"
                />

                <!-- Specialization dropdown -->
                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-1"
                >
                    <div
                        v-if="specOpen && specSuggestions.length"
                        class="absolute top-full right-0 left-0 z-50 mt-2 overflow-hidden rounded-xl border border-gray-100 bg-white py-1 shadow-xl dark:border-gray-700 dark:bg-gray-900"
                    >
                        <button
                            v-for="(suggestion, idx) in specSuggestions"
                            :key="suggestion.id"
                            type="button"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm transition-colors"
                            :class="
                                idx === specActiveIdx
                                    ? 'bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300'
                                    : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800'
                            "
                            @mousedown.prevent="handleSelectSpec(suggestion)"
                            @mouseenter="specActiveIdx = idx"
                        >
                            <Dumbbell
                                :size="14"
                                class="shrink-0 text-gray-400"
                            />
                            {{ suggestion.name }}
                        </button>
                    </div>
                </Transition>
            </div>

            <!-- City -->
            <div
                class="relative flex h-14 flex-1 items-center gap-2.5 px-4 sm:px-5"
            >
                <MapPin :size="18" class="shrink-0 text-teal-500" />
                <input
                    v-model="cityQuery"
                    type="text"
                    placeholder="Città"
                    autocomplete="off"
                    class="w-full bg-transparent text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none dark:text-white dark:placeholder:text-gray-500"
                    data-testid="search-city"
                    @focus="onCityFocus"
                    @keydown="onCityKeydown"
                    @blur="closeCitySoon"
                />
                <button
                    v-if="cityQuery"
                    type="button"
                    class="shrink-0 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    data-testid="clear-search-city"
                    aria-label="Svuota campo città"
                    @mousedown.prevent="clearCity"
                >
                    <X :size="14" />
                </button>
                <Loader2
                    v-else-if="cityLoading"
                    :size="14"
                    class="shrink-0 animate-spin text-gray-400"
                />

                <!-- City dropdown -->
                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-1"
                >
                    <div
                        v-if="cityOpen && citySuggestions.length"
                        class="absolute top-full right-0 left-0 z-50 mt-2 overflow-hidden rounded-xl border border-gray-100 bg-white py-1 shadow-xl dark:border-gray-700 dark:bg-gray-900"
                    >
                        <button
                            v-for="(suggestion, idx) in citySuggestions"
                            :key="suggestion.display_name"
                            type="button"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm transition-colors"
                            :class="
                                idx === cityActiveIdx
                                    ? 'bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300'
                                    : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800'
                            "
                            @mousedown.prevent="handleSelectCity(suggestion)"
                            @mouseenter="cityActiveIdx = idx"
                        >
                            <MapPin :size="14" class="shrink-0 text-gray-400" />
                            <span class="truncate">{{
                                suggestion.display_name
                            }}</span>
                        </button>
                    </div>
                </Transition>
            </div>

            <!-- Search button -->
            <div class="flex items-center p-2 sm:pl-0">
                <button
                    type="button"
                    class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-teal-600 px-6 text-sm font-semibold text-white shadow-md transition-colors hover:bg-teal-700 sm:h-12 sm:w-auto"
                    data-testid="search-btn"
                    @click="handleSearch"
                >
                    <Search :size="16" />
                    <span>Cerca</span>
                </button>
            </div>
        </div>
    </div>
</template>
