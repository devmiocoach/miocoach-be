<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { Dumbbell, Loader2, MapPin, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface SpecOption {
    id: number;
    name: string;
    slug: string;
}

interface NominatimSuggestion {
    display_name: string;
    name: string;
    address?: {
        city?: string;
        town?: string;
        village?: string;
        municipality?: string;
    };
}

const specialization = ref('');
const location = ref('');

const specSuggestions = ref<SpecOption[]>([]);
const specOpen = ref(false);
const specLoading = ref(false);
const specFocused = ref(false);
const specActiveIdx = ref(-1);

const citySuggestions = ref<NominatimSuggestion[]>([]);
const cityOpen = ref(false);
const cityLoading = ref(false);
const cityFocused = ref(false);
const cityActiveIdx = ref(-1);

let specTimer: ReturnType<typeof setTimeout> | null = null;
let cityTimer: ReturnType<typeof setTimeout> | null = null;

watch(specialization, (val) => {
    if (specTimer) clearTimeout(specTimer);
    specActiveIdx.value = -1;
    if (val.length < 1) {
        specSuggestions.value = [];
        specOpen.value = false;
        return;
    }
    specTimer = setTimeout(async () => {
        specLoading.value = true;
        try {
            const res = await fetch(
                `/api/v1/specializations?q=${encodeURIComponent(val)}`,
            );
            if (res.ok) {
                const data: SpecOption[] = await res.json();
                specSuggestions.value = data;
                specOpen.value = specFocused.value && data.length > 0;
            }
        } catch {
            specSuggestions.value = [];
        } finally {
            specLoading.value = false;
        }
    }, 200);
});

watch(location, (val) => {
    if (cityTimer) clearTimeout(cityTimer);
    cityActiveIdx.value = -1;
    if (val.length < 2) {
        citySuggestions.value = [];
        cityOpen.value = false;
        return;
    }
    cityTimer = setTimeout(async () => {
        cityLoading.value = true;
        try {
            const params = new URLSearchParams({
                format: 'json',
                countrycodes: 'it',
                q: val,
                limit: '5',
                addressdetails: '1',
                'accept-language': 'it',
            });
            const res = await fetch(
                `https://nominatim.openstreetmap.org/search?${params}`,
                {
                    headers: {
                        'User-Agent': 'MioCoach/1.0 (contact@miocoach.dev)',
                    },
                },
            );
            if (res.ok) {
                const data: NominatimSuggestion[] = await res.json();
                citySuggestions.value = data;
                cityOpen.value = cityFocused.value && data.length > 0;
            }
        } catch {
            citySuggestions.value = [];
        } finally {
            cityLoading.value = false;
        }
    }, 300);
});

const selectSpec = (spec: SpecOption) => {
    specialization.value = spec.name;
    specSuggestions.value = [];
    specOpen.value = false;
};

const selectCity = (suggestion: NominatimSuggestion) => {
    const city =
        suggestion.address?.city ||
        suggestion.address?.town ||
        suggestion.address?.village ||
        suggestion.address?.municipality ||
        suggestion.name;
    location.value = city;
    citySuggestions.value = [];
    cityOpen.value = false;
};

const search = () => {
    specOpen.value = false;
    cityOpen.value = false;
    const params: Record<string, string> = {};
    if (specialization.value) params.q = specialization.value;
    if (location.value) params.city = location.value;
    router.visit('/search', { data: params });
};

const onSpecKeydown = (e: KeyboardEvent) => {
    if (!specOpen.value || !specSuggestions.value.length) {
        if (e.key === 'Enter') search();
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
                selectSpec(specSuggestions.value[specActiveIdx.value]);
            }
            search();
            break;
        case 'Escape':
            specOpen.value = false;
            break;
    }
};

const onCityKeydown = (e: KeyboardEvent) => {
    if (!cityOpen.value || !citySuggestions.value.length) {
        if (e.key === 'Enter') search();
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
                selectCity(citySuggestions.value[cityActiveIdx.value]);
            }
            search();
            break;
        case 'Escape':
            cityOpen.value = false;
            break;
    }
};
</script>

<template>
    <section class="relative overflow-hidden pt-32 pb-20 lg:pt-48 lg:pb-32">
        <!-- Background Image with Overlay -->
        <div class="absolute inset-0 z-0">
            <img
                src="https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?q=80&w=2070&auto=format&fit=crop"
                alt="Fitness background"
                class="h-full w-full object-cover object-center"
            />
            <div
                class="from-background/95 via-background/80 dark:from-background/95 dark:via-background/80 absolute inset-0 bg-gradient-to-r to-transparent dark:to-transparent"
            ></div>
        </div>

        <div class="relative z-10 mx-auto max-w-7xl px-6">
            <div class="max-w-2xl">
                <!-- Badges -->
                <div class="mb-6 flex flex-wrap gap-3">
                    <Badge
                        variant="secondary"
                        class="bg-primary/10 text-primary hover:bg-primary/20 px-3 py-1 text-xs font-semibold"
                    >
                        OLTRE 2.000 COACH
                    </Badge>
                    <Badge
                        variant="secondary"
                        class="bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-500/20 dark:text-amber-500"
                    >
                        RECENSIONI CERTIFICATE
                    </Badge>
                </div>

                <!-- Title -->
                <h1
                    class="text-foreground mb-6 text-4xl leading-tight font-bold tracking-tight md:text-6xl"
                >
                    Trova il Coach perfetto per la tua
                    <span class="text-primary italic">trasformazione</span>.
                </h1>

                <!-- Subtitle -->
                <p
                    class="text-muted-foreground mb-10 max-w-xl text-lg md:text-xl"
                >
                    MioCoach ti connette con i migliori Personal Trainer
                    certificati in Italia. Prenota la tua prima sessione in
                    pochi clic.
                </p>

                <!-- Search Bar -->
                <div
                    class="bg-background dark:bg-card border-border/50 flex flex-col items-center gap-2 rounded-2xl border p-2 shadow-lg sm:flex-row"
                >
                    <!-- Specialization with autocomplete -->
                    <div
                        class="bg-muted/50 relative flex w-full items-center rounded-xl px-3 py-2 sm:flex-1"
                    >
                        <Dumbbell class="text-muted-foreground ml-1 h-5 w-5" />
                        <input
                            v-model="specialization"
                            type="text"
                            placeholder="Specializzazione (Yoga, Hiit...)"
                            autocomplete="off"
                            class="text-foreground placeholder:text-muted-foreground w-full border-0 bg-transparent px-3 text-sm focus:outline-none"
                            @focus="
                                () => {
                                    specFocused = true;
                                    if (specSuggestions.length)
                                        specOpen = true;
                                }
                            "
                            @keydown="onSpecKeydown"
                            @blur="
                                () => {
                                    setTimeout(() => {
                                        specFocused = false;
                                        specOpen = false;
                                    }, 150);
                                }
                            "
                        />
                        <Loader2
                            v-if="specLoading"
                            class="text-muted-foreground h-4 w-4 animate-spin"
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
                                class="bg-popover border-border absolute top-full right-0 left-0 z-50 mt-2 overflow-hidden rounded-xl border py-1 shadow-xl"
                            >
                                <button
                                    v-for="(spec, idx) in specSuggestions"
                                    :key="spec.id"
                                    type="button"
                                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm transition-colors"
                                    :class="
                                        idx === specActiveIdx
                                            ? 'bg-accent text-accent-foreground'
                                            : 'text-popover-foreground hover:bg-accent/50'
                                    "
                                    @mousedown.prevent="selectSpec(spec)"
                                    @mouseenter="specActiveIdx = idx"
                                >
                                    <Dumbbell
                                        class="h-3.5 w-3.5 shrink-0 text-gray-400"
                                    />
                                    {{ spec.name }}
                                </button>
                            </div>
                        </Transition>
                    </div>

                    <div class="bg-border hidden h-8 w-[1px] sm:block"></div>

                    <!-- City with autocomplete -->
                    <div
                        class="bg-muted/50 relative flex w-full items-center rounded-xl px-3 py-2 sm:flex-1"
                    >
                        <MapPin class="text-muted-foreground ml-1 h-5 w-5" />
                        <input
                            v-model="location"
                            type="text"
                            placeholder="Città o CAP"
                            autocomplete="off"
                            class="text-foreground placeholder:text-muted-foreground w-full border-0 bg-transparent px-3 text-sm focus:outline-none"
                            @focus="
                                () => {
                                    cityFocused = true;
                                    if (citySuggestions.length)
                                        cityOpen = true;
                                }
                            "
                            @keydown="onCityKeydown"
                            @blur="
                                () => {
                                    setTimeout(() => {
                                        cityFocused = false;
                                        cityOpen = false;
                                    }, 150);
                                }
                            "
                        />
                        <Loader2
                            v-if="cityLoading"
                            class="text-muted-foreground h-4 w-4 animate-spin"
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
                                class="bg-popover border-border absolute top-full right-0 left-0 z-50 mt-2 overflow-hidden rounded-xl border py-1 shadow-xl"
                            >
                                <button
                                    v-for="(
                                        suggestion, idx
                                    ) in citySuggestions"
                                    :key="suggestion.display_name"
                                    type="button"
                                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm transition-colors"
                                    :class="
                                        idx === cityActiveIdx
                                            ? 'bg-accent text-accent-foreground'
                                            : 'text-popover-foreground hover:bg-accent/50'
                                    "
                                    @mousedown.prevent="selectCity(suggestion)"
                                    @mouseenter="cityActiveIdx = idx"
                                >
                                    <MapPin
                                        class="h-3.5 w-3.5 shrink-0 text-gray-400"
                                    />
                                    <span class="truncate">{{
                                        suggestion.display_name
                                    }}</span>
                                </button>
                            </div>
                        </Transition>
                    </div>

                    <Button
                        @click="search"
                        class="h-12 w-full rounded-xl px-8 text-base font-semibold shadow-md sm:w-auto"
                    >
                        <Search class="mr-2 h-4 w-4" />
                        Cerca
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
