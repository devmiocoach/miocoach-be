<script setup lang="ts">
import {
    Building2,
    Crosshair,
    Loader2,
    MapPin,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface CoachData {
    city: string | null;
    lat: number | null;
    lng: number | null;
    service_radius_km: number | null;
    available_at_home: boolean;
    partner_gyms: string[] | null;
}

interface GeoResult {
    display_name: string;
    lat: string;
    lon: string;
}

const props = defineProps<{
    coach: CoachData | null;
}>();

const radius = ref(props.coach?.service_radius_km ?? 15);
const availableAtHome = ref(props.coach?.available_at_home ?? true);
const gyms = ref<string[]>(props.coach?.partner_gyms ?? []);
const newGym = ref('');
const address = ref(props.coach?.city ?? '');
const lat = ref(props.coach?.lat ?? 45.4642);
const lng = ref(props.coach?.lng ?? 9.19);

const mapContainer = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);
const dropdownStyle = ref({ top: '0px', left: '0px', width: '0px' });
let map: any = null;
let marker: any = null;
let circle: any = null;

const RADIUS_PRESETS = [1, 10, 25, 50];

const searchQuery = ref(props.coach?.city ?? '');
const searchResults = ref<GeoResult[]>([]);
const searching = ref(false);
const showResults = ref(false);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

watch(searchQuery, (val) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    if (!val || val.length < 3) {
        searchResults.value = [];
        showResults.value = false;
        return;
    }
    searchTimeout = setTimeout(() => geocodeSearch(val), 400);
});

async function geocodeSearch(query: string) {
    searching.value = true;
    try {
        const res = await fetch(
            `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=it&limit=5&addressdetails=1`,
            { headers: { 'Accept-Language': 'it' } },
        );
        const data: GeoResult[] = await res.json();
        searchResults.value = data;
        if (data.length > 0) {
            await nextTick();
            updateDropdownPosition();
            showResults.value = true;
        } else {
            showResults.value = false;
        }
    } catch {
        searchResults.value = [];
        showResults.value = false;
    } finally {
        searching.value = false;
    }
}

function updateDropdownPosition() {
    const el = searchInputRef.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    dropdownStyle.value = {
        top: `${rect.bottom + window.scrollY + 4}px`,
        left: `${rect.left + window.scrollX}px`,
        width: `${rect.width}px`,
    };
}

function selectResult(result: GeoResult) {
    lat.value = parseFloat(result.lat);
    lng.value = parseFloat(result.lon);
    address.value = result.display_name.split(',')[0];
    searchQuery.value = result.display_name.split(',').slice(0, 2).join(',');
    showResults.value = false;

    if (map && marker && circle) {
        const pos = { lat: lat.value, lng: lng.value };
        marker.setLatLng(pos);
        circle.setLatLng(pos);
        map.setView(pos, 13);
    }
}

onMounted(async () => {
    if (!mapContainer.value) return;

    const L = await import('leaflet');
    await import('leaflet/dist/leaflet.css');

    map = L.map(mapContainer.value, {
        center: [lat.value, lng.value],
        zoom: 12,
        zoomControl: false,
        attributionControl: false,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
    }).addTo(map);

    L.control.zoom({ position: 'bottomright' }).addTo(map);

    marker = L.marker([lat.value, lng.value]).addTo(map);

    circle = L.circle([lat.value, lng.value], {
        radius: radius.value * 1000,
        color: '#0d9488',
        fillColor: '#0d9488',
        fillOpacity: 0.1,
        weight: 2,
    }).addTo(map);
});

onUnmounted(() => {
    if (map) {
        map.remove();
        map = null;
    }
});

watch(radius, (val) => {
    if (circle) {
        circle.setRadius(val * 1000);
    }
    if (map && circle) {
        map.fitBounds(circle.getBounds(), { padding: [20, 20] });
    }
});

function addGym() {
    const name = newGym.value.trim();
    if (!name || gyms.value.includes(name)) return;
    gyms.value.push(name);
    newGym.value = '';
}

function removeGym(name: string) {
    gyms.value = gyms.value.filter((g) => g !== name);
}

function getData() {
    return {
        city: address.value || null,
        lat: lat.value,
        lng: lng.value,
        service_radius_km: radius.value,
        available_at_home: availableAtHome.value,
        partner_gyms: gyms.value,
    };
}

defineExpose({ getData });
</script>

<template>
    <div class="space-y-5">
        <!-- Map card -->
        <div
            class="rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="p-6">
                <div
                    class="mb-1 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    <MapPin class="h-5 w-5 text-teal-600 dark:text-teal-400" />
                    Indirizzo principale
                </div>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    Cerca la tua città, via o CAP per posizionare il pin
                </p>
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400"
                    />
                    <Loader2
                        v-if="searching"
                        class="absolute top-1/2 right-3.5 h-4 w-4 -translate-y-1/2 animate-spin text-teal-500"
                    />
                    <input
                        ref="searchInputRef"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cerca via, città o CAP…"
                        data-testid="input-address"
                        class="h-12 w-full rounded-xl border-0 bg-gray-50 pr-10 pl-11 text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-800 dark:text-white dark:ring-gray-700"
                        @focus="
                            showResults = searchResults.length > 0;
                            updateDropdownPosition();
                        "
                        @blur="setTimeout(() => (showResults = false), 200)"
                    />
                </div>

                <!-- Teleported dropdown — escapes Leaflet stacking context -->
                <Teleport to="body">
                    <div
                        v-if="showResults"
                        class="fixed z-[9999] max-h-48 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
                        :style="dropdownStyle"
                    >
                        <button
                            v-for="(result, i) in searchResults"
                            :key="i"
                            type="button"
                            class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-gray-700 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                            @mousedown.prevent="selectResult(result)"
                        >
                            <MapPin class="h-4 w-4 shrink-0 text-teal-500" />
                            <span class="truncate">{{
                                result.display_name
                            }}</span>
                        </button>
                    </div>
                </Teleport>
            </div>

            <!-- Leaflet map — needs overflow-hidden to clip tiles at rounded corners -->
            <div class="overflow-hidden">
                <div
                    ref="mapContainer"
                    class="h-64 w-full"
                    data-testid="leaflet-map"
                />
            </div>

            <!-- Radius slider -->
            <div class="p-6">
                <div class="mb-3 flex items-center justify-between">
                    <label
                        class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300"
                    >
                        <Crosshair
                            class="h-4 w-4 text-teal-600 dark:text-teal-400"
                        />
                        Raggio area di servizio
                    </label>
                    <span
                        class="text-sm font-bold text-teal-600 dark:text-teal-400"
                        >{{ radius }} km</span
                    >
                </div>
                <input
                    v-model.number="radius"
                    type="range"
                    min="1"
                    max="50"
                    data-testid="radius-slider"
                    class="w-full accent-teal-600"
                />
                <div class="mt-2 flex justify-between">
                    <button
                        v-for="preset in RADIUS_PRESETS"
                        :key="preset"
                        :data-testid="`radius-preset-${preset}`"
                        :class="[
                            'rounded px-2 py-0.5 text-xs font-medium transition',
                            radius === preset
                                ? 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300'
                                : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800',
                        ]"
                        @click="radius = preset"
                    >
                        {{ preset }} km
                    </button>
                </div>
            </div>
        </div>

        <!-- Home visits card -->
        <div
            :class="[
                'rounded-3xl border p-6 shadow-sm transition-colors',
                availableAtHome
                    ? 'border-teal-200 bg-teal-50/30 dark:border-teal-800 dark:bg-teal-900/10'
                    : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900',
            ]"
        >
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Disponibile a domicilio
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Raggiungo il cliente nella sua palestra o casa
                    </p>
                </div>
                <button
                    data-testid="toggle-home-visits"
                    :class="[
                        'relative h-6 w-11 rounded-full transition-colors',
                        availableAtHome
                            ? 'bg-teal-600'
                            : 'bg-gray-300 dark:bg-gray-600',
                    ]"
                    @click="availableAtHome = !availableAtHome"
                >
                    <span
                        :class="[
                            'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform',
                            availableAtHome
                                ? 'translate-x-5'
                                : 'translate-x-0.5',
                        ]"
                    />
                </button>
            </div>
        </div>

        <!-- Partner gyms card -->
        <div
            class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="mb-1 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"
            >
                <Building2 class="h-5 w-5 text-teal-600 dark:text-teal-400" />
                Palestre partner
            </div>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                Strutture dove ricevi clienti regolarmente
            </p>

            <div v-if="gyms.length > 0" class="mb-4 flex flex-wrap gap-2">
                <span
                    v-for="gym in gyms"
                    :key="gym"
                    class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 py-1.5 pr-1.5 pl-3 text-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <Building2
                        class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400"
                    />
                    {{ gym }}
                    <button
                        :data-testid="`remove-gym-${gym}`"
                        class="rounded-full p-0.5 text-gray-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20"
                        @click="removeGym(gym)"
                    >
                        <Trash2 class="h-3 w-3" />
                    </button>
                </span>
            </div>

            <div class="flex gap-2">
                <input
                    v-model="newGym"
                    type="text"
                    placeholder="Cerca palestra…"
                    data-testid="input-new-gym"
                    class="flex-1 rounded-lg border-0 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-800 dark:text-white dark:ring-gray-700"
                    @keydown.enter.prevent="addGym"
                />
                <button
                    :disabled="!newGym.trim()"
                    data-testid="add-gym-btn"
                    class="flex items-center gap-1.5 rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-200 disabled:opacity-40 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="addGym"
                >
                    <Plus class="h-4 w-4" />
                    Aggiungi
                </button>
            </div>
        </div>
    </div>
</template>
