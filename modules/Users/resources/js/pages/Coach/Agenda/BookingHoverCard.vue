<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';

import type { AgendaBooking } from '@modules/Users/resources/js/types/agenda';

defineProps<{
    booking: AgendaBooking;
}>();

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('it-IT', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatTimeRange(booking: AgendaBooking): string {
    const start = formatTime(booking.starts_at);
    const end = booking.ends_at ? formatTime(booking.ends_at) : '';
    return end ? `${start} — ${end}` : start;
}
</script>

<template>
    <div
        class="pointer-events-none absolute z-50 w-56 rounded-lg border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-700 dark:bg-gray-800"
        data-testid="booking-hover-card"
    >
        <div class="space-y-1.5">
            <div class="text-sm font-medium text-gray-900 dark:text-white">
                {{ booking.client?.name || booking.guest_name || '—' }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ formatTimeRange(booking) }}
                <span v-if="booking.duration_minutes" class="ml-1"
                    >({{ booking.duration_minutes }}min)</span
                >
            </div>
            <div
                v-if="booking.service_type_label"
                class="flex items-center gap-1.5"
            >
                <Badge variant="secondary" class="text-[10px]">{{
                    booking.service_type_label
                }}</Badge>
            </div>
            <div
                v-if="booking.price"
                class="text-xs font-medium text-gray-700 dark:text-gray-300"
            >
                €{{ booking.price.toFixed(2) }}
            </div>
            <div
                v-if="booking.notes"
                class="line-clamp-2 text-[11px] text-gray-500 dark:text-gray-400"
            >
                {{ booking.notes }}
            </div>
        </div>
    </div>
</template>
