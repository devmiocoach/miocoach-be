<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useBookingResize } from '@modules/Users/resources/js/composables/useBookingResize';
import { useCurrentTime } from '@modules/Users/resources/js/composables/useCurrentTime';
import { computed, ref } from 'vue';

import SessionCard from './SessionCard.vue';

import type {
    AgendaAvailability,
    AgendaBooking,
} from '@modules/Users/resources/js/types/agenda';

const props = defineProps<{
    bookings: AgendaBooking[];
    day: Date;
    availabilities: AgendaAvailability[];
}>();

const emit = defineEmits<{
    slotClick: [payload: { date: string; time: string }];
    bookingClick: [booking: AgendaBooking];
}>();

const START_HOUR = 6;
const END_HOUR = 22;
const SLOT_HEIGHT = 4; // rem per hour (larger for day view)

const hours = Array.from(
    { length: END_HOUR - START_HOUR },
    (_, i) => START_HOUR + i,
);

const { now } = useCurrentTime();
const { resizingBookingId, resizeGhostHeight, onResizeStart } =
    useBookingResize(SLOT_HEIGHT, START_HOUR);

const dayColumnRef = ref<HTMLElement | null>(null);

const isToday = computed(
    () => props.day.toDateString() === new Date().toDateString(),
);

const currentTimeTop = computed(() => {
    const minutes =
        now.value.getHours() * 60 + now.value.getMinutes() - START_HOUR * 60;
    return (minutes / 60) * SLOT_HEIGHT;
});

const dayBookings = computed(() => {
    const dayStr = props.day.toISOString().split('T')[0];
    return props.bookings.filter((b) => b.starts_at.startsWith(dayStr));
});

interface LayoutedBooking {
    booking: AgendaBooking;
    column: number;
    totalColumns: number;
}

function layoutBookings(bookings: AgendaBooking[]): LayoutedBooking[] {
    if (bookings.length === 0) return [];

    const items = bookings
        .map((b) => {
            const start = new Date(b.starts_at);
            const end = b.ends_at
                ? new Date(b.ends_at)
                : new Date(
                      start.getTime() + (b.duration_minutes || 60) * 60 * 1000,
                  );
            return {
                booking: b,
                startMin: start.getHours() * 60 + start.getMinutes(),
                endMin: end.getHours() * 60 + end.getMinutes(),
            };
        })
        .sort((a, b) => a.startMin - b.startMin || a.endMin - b.endMin);

    const columns: number[][] = [];
    const result: LayoutedBooking[] = [];

    for (const item of items) {
        let placed = false;
        for (let col = 0; col < columns.length; col++) {
            const lastEnd = columns[col][columns[col].length - 1];
            if (item.startMin >= lastEnd) {
                columns[col].push(item.endMin);
                result.push({
                    booking: item.booking,
                    column: col,
                    totalColumns: 0,
                });
                placed = true;
                break;
            }
        }
        if (!placed) {
            columns.push([item.endMin]);
            result.push({
                booking: item.booking,
                column: columns.length - 1,
                totalColumns: 0,
            });
        }
    }

    const totalCols = columns.length;
    for (const r of result) {
        r.totalColumns = totalCols;
    }

    return result;
}

function bookingStyle(
    booking: AgendaBooking,
    column: number,
    totalColumns: number,
) {
    const start = new Date(booking.starts_at);
    const end = booking.ends_at
        ? new Date(booking.ends_at)
        : new Date(start.getTime() + 60 * 60 * 1000);
    const startMinutes =
        start.getHours() * 60 + start.getMinutes() - START_HOUR * 60;
    const duration = (end.getTime() - start.getTime()) / (1000 * 60);
    const top = (startMinutes / 60) * SLOT_HEIGHT;
    const height = (duration / 60) * SLOT_HEIGHT;

    const width =
        totalColumns > 1 ? `${100 / totalColumns}%` : 'calc(100% - 0.5rem)';
    const left =
        totalColumns > 1 ? `${(column / totalColumns) * 100}%` : '0.25rem';

    return {
        top: `${top}rem`,
        height:
            resizingBookingId.value === booking.id && resizeGhostHeight.value
                ? resizeGhostHeight.value
                : `${Math.max(height, 2)}rem`,
        width,
        left,
    };
}

function isBookingPast(booking: AgendaBooking): boolean {
    const end = booking.ends_at
        ? new Date(booking.ends_at)
        : new Date(
              new Date(booking.starts_at).getTime() +
                  (booking.duration_minutes || 60) * 60 * 1000,
          );
    return end < now.value;
}

function isAvailable(hour: number): boolean {
    const dow = props.day.getDay();
    return props.availabilities.some((a) => {
        if (a.day_of_week !== dow) return false;
        const startH = parseInt(a.start_time.split(':')[0]);
        const endH = parseInt(a.end_time.split(':')[0]);
        return hour >= startH && hour < endH;
    });
}

function handleSlotClick(hour: number, quarter: number) {
    const date = props.day.toISOString().split('T')[0];
    const minutes = quarter * 15;
    const time = `${hour.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
    emit('slotClick', { date, time });
}

function handleDrop(event: DragEvent, hour: number, quarter: number) {
    event.preventDefault();
    const bookingId = event.dataTransfer?.getData('booking-id');
    const duration = parseInt(event.dataTransfer?.getData('duration') || '60');
    if (!bookingId) return;

    const startsAt = new Date(props.day);
    startsAt.setHours(hour, quarter * 15, 0, 0);
    const endsAt = new Date(startsAt.getTime() + duration * 60 * 1000);

    router.patch(
        route('coach.agenda.bookings.update', parseInt(bookingId)),
        {
            starts_at: startsAt.toISOString(),
            ends_at: endsAt.toISOString(),
        },
        { preserveScroll: true },
    );
}

function handleDragOver(event: DragEvent) {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function handleResizeStart(event: MouseEvent, booking: AgendaBooking) {
    if (dayColumnRef.value) onResizeStart(event, booking, dayColumnRef.value);
}

function formatDayHeader(): string {
    return props.day.toLocaleDateString('it-IT', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
</script>

<template>
    <div
        class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
        data-testid="agenda-day-view"
    >
        <!-- Day header -->
        <div
            :class="[
                'border-b border-gray-200 px-4 py-3 text-sm font-medium capitalize dark:border-gray-800',
                isToday
                    ? 'bg-primary/5 text-primary font-bold'
                    : 'text-gray-700 dark:text-gray-300',
            ]"
        >
            {{ formatDayHeader() }}
        </div>

        <!-- Time grid -->
        <div class="relative grid grid-cols-[4rem_1fr]">
            <!-- Time labels -->
            <div class="relative">
                <div
                    v-for="hour in hours"
                    :key="hour"
                    :style="{ height: `${SLOT_HEIGHT}rem` }"
                    class="flex items-start justify-end pt-0.5 pr-2 text-[11px] text-gray-400 dark:text-gray-500"
                >
                    {{ hour.toString().padStart(2, '0') }}:00
                </div>
            </div>

            <!-- Day column -->
            <div
                ref="dayColumnRef"
                class="relative border-l border-gray-200 dark:border-gray-800"
            >
                <!-- Quarter-hour slots (4 per hour) -->
                <div
                    v-for="hour in hours"
                    :key="hour"
                    :style="{ height: `${SLOT_HEIGHT}rem` }"
                >
                    <div
                        v-for="q in 4"
                        :key="q"
                        :class="[
                            'h-1/4 cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50',
                            q === 1
                                ? 'border-t border-gray-100 dark:border-gray-800/50'
                                : 'border-t border-gray-50 dark:border-gray-800/30',
                            isAvailable(hour)
                                ? 'bg-green-50/50 dark:bg-green-900/10'
                                : '',
                        ]"
                        @click="handleSlotClick(hour, q - 1)"
                        @drop="handleDrop($event, hour, q - 1)"
                        @dragover="handleDragOver"
                    />
                </div>

                <!-- Current time line -->
                <div
                    v-if="isToday"
                    class="pointer-events-none absolute inset-x-0 z-20"
                    :style="{ top: `${currentTimeTop}rem` }"
                    data-testid="current-time-line"
                >
                    <div class="relative flex items-center">
                        <div
                            class="absolute -left-1.5 size-3 rounded-full bg-red-500"
                        />
                        <div class="h-0.5 w-full bg-red-500" />
                    </div>
                </div>

                <!-- Booking cards (with overlap layout) -->
                <div
                    v-for="{ booking, column, totalColumns } in layoutBookings(
                        dayBookings,
                    )"
                    :key="booking.id"
                    class="absolute z-10"
                    :style="bookingStyle(booking, column, totalColumns)"
                >
                    <SessionCard
                        :booking="booking"
                        :is-past="isBookingPast(booking)"
                        class="h-full"
                        @click="emit('bookingClick', booking)"
                        @resize-start="handleResizeStart($event, booking)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
