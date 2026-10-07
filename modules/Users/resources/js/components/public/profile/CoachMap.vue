<script setup lang="ts">
import { onMounted, ref } from 'vue';

const props = defineProps<{
    lat: number;
    lng: number;
    displayName: string;
}>();

const mapContainer = ref<HTMLElement | null>(null);

onMounted(async () => {
    const L = await import('leaflet');
    await import('leaflet/dist/leaflet.css');

    if (!mapContainer.value) return;

    const map = L.map(mapContainer.value).setView([props.lat, props.lng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    L.marker([props.lat, props.lng]).addTo(map).bindPopup(props.displayName);
});
</script>

<template>
    <section class="py-8">
        <h2 class="mb-4 text-xl font-bold text-gray-900 dark:text-white">
            Posizione
        </h2>
        <div
            ref="mapContainer"
            class="h-64 w-full overflow-hidden rounded-xl"
        />
    </section>
</template>
