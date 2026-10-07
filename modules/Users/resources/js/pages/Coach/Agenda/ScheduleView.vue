<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import { computed } from 'vue';

import type { AgendaBooking } from '@modules/Users/resources/js/types/agenda';

const props = defineProps<{
    bookings: AgendaBooking[];
    weekDays: Date[];
}>();

const emit = defineEmits<{
    bookingClick: [booking: AgendaBooking];
}>();

interface DayGroup {
    date: Date;
    label: string;
    isToday: boolean;
    bookings: AgendaBooking[];
}

const groupedByDay = computed<DayGroup[]>(() => {
    const today = new Date().toDateString();
    return props.weekDays
        .map((day) => {
            const dayStr = day.toISOString().split('T')[0];
            const dayBookings = props.bookings
                .filter((b) => b.starts_at.startsWith(dayStr))
                .sort((a, b) => a.starts_at.localeCompare(b.starts_at));

            return {
                date: day,
                label: day.toLocaleDateString('it-IT', {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                }),
                isToday: day.toDateString() === today,
                bookings: dayBookings,
            };
        })
        .filter((g) => g.bookings.length > 0);
});

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

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'confirmed':
            return 'secondary';
        case 'cancelled':
            return 'destructive';
        default:
            return 'outline';
    }
}
</script>

<template>
    <div
        class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
        data-testid="agenda-schedule-view"
    >
        <div v-if="groupedByDay.length === 0" class="py-16 text-center">
            <p class="text-gray-500 dark:text-gray-400">
                {{ $t('No sessions this week') }}
            </p>
        </div>

        <div v-else class="divide-y divide-gray-100 dark:divide-gray-800">
            <div v-for="group in groupedByDay" :key="group.date.toISOString()">
                <!-- Day header -->
                <div
                    :class="[
                        'sticky top-0 z-10 border-b border-gray-100 px-4 py-2 text-xs font-medium capitalize dark:border-gray-800',
                        group.isToday
                            ? 'bg-primary/5 text-primary font-bold'
                            : 'bg-gray-50 text-gray-600 dark:bg-gray-800/50 dark:text-gray-400',
                    ]"
                >
                    {{ group.label }}
                </div>

                <!-- Bookings -->
                <div
                    v-for="booking in group.bookings"
                    :key="booking.id"
                    class="flex cursor-pointer items-center gap-4 px-4 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                    :data-testid="`schedule-row-${booking.id}`"
                    @click="emit('bookingClick', booking)"
                >
                    <!-- Time -->
                    <div
                        class="w-28 shrink-0 text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        {{ formatTimeRange(booking) }}
                    </div>

                    <!-- Service color dot -->
                    <div
                        :class="[
                            'size-2 shrink-0 rounded-full',
                            booking.service_type === 'personal_training'
                                ? 'bg-blue-500'
                                : booking.service_type === 'online_coaching'
                                  ? 'bg-indigo-500'
                                  : booking.service_type === 'assessment'
                                    ? 'bg-amber-500'
                                    : booking.service_type === 'group_session'
                                      ? 'bg-green-500'
                                      : booking.service_type ===
                                          'nutrition_consultation'
                                        ? 'bg-purple-500'
                                        : 'bg-gray-400',
                        ]"
                    />

                    <!-- Client + Service -->
                    <div class="min-w-0 flex-1">
                        <div
                            class="truncate text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                booking.client?.name ||
                                booking.guest_name ||
                                '—'
                            }}
                        </div>
                        <div
                            v-if="booking.service_type_label"
                            class="truncate text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ booking.service_type_label }}
                        </div>
                    </div>

                    <!-- Status + Price -->
                    <div class="flex shrink-0 items-center gap-2">
                        <span
                            v-if="booking.price"
                            class="text-sm text-gray-600 dark:text-gray-400"
                        >
                            €{{ booking.price.toFixed(0) }}
                        </span>
                        <Badge
                            :variant="statusVariant(booking.status)"
                            class="text-[10px]"
                        >
                            {{ booking.status }}
                        </Badge>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
