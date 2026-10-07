<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { MapPin, Monitor, Plus, Search, Shuffle, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Specialization {
    id: number;
    name: string;
    slug: string;
}

interface CoachData {
    specialization_ids: number[];
    languages: string[];
    mode: string | null;
}

const props = defineProps<{
    coach: CoachData | null;
    specializations: Specialization[];
}>();

const MAX_SPECIALIZATIONS = 10;

const selectedIds = ref<number[]>(props.coach?.specialization_ids ?? []);
const customSpecializations = ref<string[]>([]);
const searchQuery = ref('');
const customInput = ref('');
const selectedLanguages = ref<string[]>(props.coach?.languages ?? ['it']);
const selectedMode = ref<string>(props.coach?.mode ?? 'online');

const page = usePage();
const errors = computed(
    () => (page.props.errors as Record<string, string>) ?? {},
);

const AVAILABLE_LANGUAGES = [
    { code: 'it', label: 'Italiano' },
    { code: 'en', label: 'English' },
    { code: 'es', label: 'Español' },
    { code: 'fr', label: 'Français' },
    { code: 'de', label: 'Deutsch' },
    { code: 'pt', label: 'Português' },
];

const MODES = [
    { value: 'online', label: 'Online', icon: Monitor },
    { value: 'in_person', label: 'In presenza', icon: MapPin },
    { value: 'hybrid', label: 'Ibrido', icon: Shuffle },
];

const filteredSpecializations = computed(() =>
    props.specializations.filter((s) =>
        s.name.toLowerCase().includes(searchQuery.value.toLowerCase()),
    ),
);

const totalSelected = computed(
    () => selectedIds.value.length + customSpecializations.value.length,
);

function toggleSpecialization(id: number) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((i) => i !== id);
    } else if (totalSelected.value < MAX_SPECIALIZATIONS) {
        selectedIds.value.push(id);
    }
}

function addCustom() {
    const name = customInput.value.trim();
    if (
        !name ||
        customSpecializations.value.includes(name) ||
        totalSelected.value >= MAX_SPECIALIZATIONS
    )
        return;
    customSpecializations.value.push(name);
    customInput.value = '';
}

function removeCustom(name: string) {
    customSpecializations.value = customSpecializations.value.filter(
        (s) => s !== name,
    );
}

function toggleLanguage(code: string) {
    if (selectedLanguages.value.includes(code)) {
        if (selectedLanguages.value.length > 1) {
            selectedLanguages.value = selectedLanguages.value.filter(
                (l) => l !== code,
            );
        }
    } else {
        selectedLanguages.value.push(code);
    }
}

function getData() {
    return {
        specialization_ids: selectedIds.value,
        custom_specializations: customSpecializations.value,
        languages: selectedLanguages.value,
        mode: selectedMode.value,
    };
}

defineExpose({ getData, totalSelected });
</script>

<template>
    <div
        class="rounded-3xl border border-gray-200 bg-white p-7 shadow-sm dark:border-gray-800 dark:bg-gray-900"
    >
        <!-- Counter badge -->
        <div
            v-if="totalSelected > 0"
            data-testid="selected-count"
            class="mb-5 inline-flex items-center gap-2 rounded-full bg-teal-50 px-4 py-2 text-sm font-semibold text-teal-700 dark:bg-teal-900/30 dark:text-teal-300"
        >
            {{ totalSelected }} / {{ MAX_SPECIALIZATIONS }} selezionate
        </div>

        <!-- Search -->
        <div class="relative mb-6">
            <Search
                class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400"
            />
            <input
                v-model="searchQuery"
                type="text"
                placeholder="Cerca specializzazione…"
                data-testid="specialization-search"
                class="h-12 w-full rounded-xl border border-gray-200 bg-white pr-4 pl-11 text-gray-900 transition focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-teal-400 dark:focus:ring-teal-400/20"
            />
        </div>

        <!-- Specializations grid -->
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <button
                v-for="spec in filteredSpecializations"
                :key="spec.id"
                :data-testid="`spec-${spec.slug}`"
                :class="[
                    'rounded-xl border px-4 py-3.5 text-left text-sm font-semibold transition-all',
                    selectedIds.includes(spec.id)
                        ? 'border-teal-600 bg-teal-50 text-gray-900 shadow-[0_0_0_4px_rgba(13,148,136,0.1)] dark:border-teal-400 dark:bg-teal-900/20 dark:text-white'
                        : totalSelected >= MAX_SPECIALIZATIONS &&
                            !selectedIds.includes(spec.id)
                          ? 'cursor-not-allowed border-gray-200 bg-gray-50 text-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-600'
                          : 'border-gray-200 bg-white text-gray-900 hover:border-teal-400/40 dark:border-gray-700 dark:bg-gray-900 dark:text-white',
                ]"
                :disabled="
                    totalSelected >= MAX_SPECIALIZATIONS &&
                    !selectedIds.includes(spec.id)
                "
                @click="toggleSpecialization(spec.id)"
            >
                {{ spec.name }}
            </button>
        </div>

        <p
            v-if="errors.specialization_ids"
            class="mb-4 text-xs text-red-500 dark:text-red-400"
            data-testid="error-specialization-ids"
        >
            {{ errors.specialization_ids }}
        </p>

        <!-- Custom specialization input -->
        <div
            class="mb-8 rounded-xl border border-dashed border-gray-200 p-5 dark:border-gray-700"
        >
            <p
                class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
                Aggiungi specializzazione personalizzata
            </p>
            <div class="flex gap-2">
                <input
                    v-model="customInput"
                    type="text"
                    placeholder="Es: Kettlebell"
                    maxlength="60"
                    data-testid="custom-specialization-input"
                    class="h-12 flex-1 rounded-xl border border-gray-200 bg-white pr-4 pl-4 text-sm text-gray-900 transition focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-teal-400 dark:focus:ring-teal-400/20"
                    @keydown.enter.prevent="addCustom"
                />
                <button
                    :disabled="
                        !customInput.trim() ||
                        totalSelected >= MAX_SPECIALIZATIONS
                    "
                    class="flex h-12 items-center gap-1.5 rounded-xl bg-teal-600 px-5 text-sm font-semibold text-white transition hover:bg-teal-500 disabled:opacity-40"
                    data-testid="add-custom-specialization"
                    @click="addCustom"
                >
                    <Plus class="h-4 w-4" />
                    Aggiungi
                </button>
            </div>
            <div
                v-if="customSpecializations.length > 0"
                class="mt-3 flex flex-wrap gap-2"
            >
                <span
                    v-for="name in customSpecializations"
                    :key="name"
                    class="flex items-center gap-1.5 rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-700 dark:bg-orange-900/30 dark:text-orange-300"
                >
                    {{ name }}
                    <button
                        :data-testid="`remove-custom-${name}`"
                        @click="removeCustom(name)"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </span>
            </div>
        </div>

        <!-- Mode + Languages -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Modalita -->
            <div>
                <p
                    class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300"
                >
                    Modalita di lavoro *
                </p>
                <div class="grid grid-cols-3 gap-3">
                    <button
                        v-for="m in MODES"
                        :key="m.value"
                        :data-testid="`mode-${m.value}`"
                        :class="[
                            'flex flex-col items-center gap-2 rounded-xl border px-3 py-4 text-sm font-semibold transition-all',
                            selectedMode === m.value
                                ? 'border-teal-600 bg-teal-50 text-teal-600 shadow-[0_0_0_4px_rgba(13,148,136,0.1)] dark:border-teal-400 dark:bg-teal-900/20 dark:text-teal-400'
                                : 'border-gray-200 bg-white text-gray-500 hover:border-teal-400/40 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400',
                        ]"
                        @click="selectedMode = m.value"
                    >
                        <component :is="m.icon" class="h-5 w-5" />
                        <span
                            :class="
                                selectedMode === m.value
                                    ? 'text-teal-600 dark:text-teal-400'
                                    : 'text-gray-700 dark:text-gray-300'
                            "
                            >{{ m.label }}</span
                        >
                    </button>
                </div>
                <p
                    v-if="errors.mode"
                    class="mt-2 text-xs text-red-500 dark:text-red-400"
                    data-testid="error-mode"
                >
                    {{ errors.mode }}
                </p>
            </div>

            <!-- Lingue -->
            <div>
                <p
                    class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300"
                >
                    Lingue parlate *
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="lang in AVAILABLE_LANGUAGES"
                        :key="lang.code"
                        :data-testid="`lang-${lang.code}`"
                        :class="[
                            'rounded-full border px-4 py-2 text-sm font-semibold transition-all',
                            selectedLanguages.includes(lang.code)
                                ? 'border-teal-600 bg-teal-50 text-gray-900 shadow-[0_0_0_4px_rgba(13,148,136,0.1)] dark:border-teal-400 dark:bg-teal-900/20 dark:text-white'
                                : 'border-gray-200 bg-white text-gray-900 hover:border-teal-400/40 dark:border-gray-700 dark:bg-gray-900 dark:text-white',
                        ]"
                        @click="toggleLanguage(lang.code)"
                    >
                        {{ lang.label }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
