<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import type { CoachListingItem } from '../../types/public';

const props = defineProps<{
    coaches: CoachListingItem[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const mapContainer = ref<HTMLElement | null>(null);

let map: any = null;

let leaflet: any = null;

onMounted(async () => {
    leaflet = await import('leaflet');
    await import('leaflet/dist/leaflet.css');

    if (!mapContainer.value) return;

    map = leaflet.map(mapContainer.value).setView([41.9028, 12.4964], 6);

    leaflet
        .tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
        })
        .addTo(map);

    addMarkers();
});

const addMarkers = () => {
    if (!map || !leaflet) return;

    const bounds: [number, number][] = [];

    props.coaches.forEach((coach) => {
        if (coach.lat && coach.lng) {
            const marker = leaflet.marker([coach.lat, coach.lng]).addTo(map);
            marker.bindPopup(`
                <strong>${coach.display_name}</strong><br/>
                ${coach.city ?? ''}<br/>
                ${
                    coach.price_from_cents
                        ? `${(coach.price_from_cents / 100).toFixed(0)}€/ora`
                        : ''
                }
            `);
            bounds.push([coach.lat, coach.lng]);
        }
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [50, 50] });
    }
};

watch(() => props.coaches, addMarkers);
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" @click="emit('close')" />
            <div
                class="relative h-[70vh] w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
            >
                <button
                    type="button"
                    class="absolute top-4 right-4 z-10 rounded-full bg-white p-2 shadow-lg dark:bg-gray-800"
                    @click="emit('close')"
                >
                    <X :size="20" class="text-gray-600 dark:text-gray-400" />
                </button>
                <div ref="mapContainer" class="h-full w-full" />
            </div>
        </div>
    </Teleport>
</template>
