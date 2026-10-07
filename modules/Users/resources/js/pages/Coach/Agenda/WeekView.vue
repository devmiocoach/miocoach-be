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
    weekDays: Date[];
    availabilities: AgendaAvailability[];
}>();

const emit = defineEmits<{
    slotClick: [payload: { date: string; time: string }];
    bookingClick: [booking: AgendaBooking];
}>();

const START_HOUR = 6;
const END_HOUR = 22;
const SLOT_HEIGHT = 3; // rem per hour

const hours = Array.from(
    { length: END_HOUR - START_HOUR },
    (_, i) => START_HOUR + i,
);

const { now } = useCurrentTime();
const { resizingBookingId, resizeGhostHeight, onResizeStart } =
    useBookingResize(SLOT_HEIGHT, START_HOUR);

const dayColumnRefs = ref<HTMLElement[]>([]);

const currentTimeTop = computed(() => {
    const minutes =
        now.value.getHours() * 60 + now.value.getMinutes() - START_HOUR * 60;
    return (minutes / 60) * SLOT_HEIGHT;
});

function todayColumnIndex(): number {
    const todayStr = new Date().toDateString();
    return props.weekDays.findIndex((d) => d.toDateString() === todayStr);
}

function bookingsForDay(day: Date) {
    const dayStr = day.toISOString().split('T')[0];
    return props.bookings.filter((b) => b.starts_at.startsWith(dayStr));
}

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
        totalColumns > 1 ? `${100 / totalColumns}%` : 'calc(100% - 0.25rem)';
    const left =
        totalColumns > 1 ? `${(column / totalColumns) * 100}%` : '0.125rem';

    return {
        top: `${top}rem`,
        height:
            resizingBookingId.value === booking.id && resizeGhostHeight.value
                ? resizeGhostHeight.value
                : `${Math.max(height, 1.5)}rem`,
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

function isAvailable(day: Date, hour: number): boolean {
    const dow = day.getDay();
    return props.availabilities.some((a) => {
        if (a.day_of_week !== dow) return false;
        const startH = parseInt(a.start_time.split(':')[0]);
        const endH = parseInt(a.end_time.split(':')[0]);
        return hour >= startH && hour < endH;
    });
}

function handleSlotClick(day: Date, hour: number) {
    const date = day.toISOString().split('T')[0];
    const time = `${hour.toString().padStart(2, '0')}:00`;
    emit('slotClick', { date, time });
}

function formatDayLabel(day: Date): string {
    return day.toLocaleDateString('it-IT', {
        weekday: 'short',
        day: 'numeric',
    });
}

function isToday(day: Date): boolean {
    const today = new Date();
    return day.toDateString() === today.toDateString();
}

function handleDrop(event: DragEvent, day: Date, hour: number) {
    event.preventDefault();
    const bookingId = event.dataTransfer?.getData('booking-id');
    const duration = parseInt(event.dataTransfer?.getData('duration') || '60');
    if (!bookingId) return;

    const startsAt = new Date(day);
    startsAt.setHours(hour, 0, 0, 0);
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

function handleResizeStart(
    event: MouseEvent,
    booking: AgendaBooking,
    dayIndex: number,
) {
    const container = dayColumnRefs.value[dayIndex];
    if (container) onResizeStart(event, booking, container);
}
</script>

<template>
    <div
        class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
        data-testid="agenda-week-view"
    >
        <div class="min-w-[700px]">
            <!-- Day headers -->
            <div
                class="grid grid-cols-[4rem_repeat(7,1fr)] border-b border-gray-200 dark:border-gray-800"
            >
                <div class="p-2" />
                <div
                    v-for="day in weekDays"
                    :key="day.toISOString()"
                    :class="[
                        'border-l border-gray-200 p-2 text-center text-xs font-medium dark:border-gray-800',
                        isToday(day)
                            ? 'bg-primary/5 text-primary font-bold'
                            : 'text-gray-500 dark:text-gray-400',
                    ]"
                >
                    {{ formatDayLabel(day) }}
                </div>
            </div>

            <!-- Time grid -->
            <div class="relative grid grid-cols-[4rem_repeat(7,1fr)]">
                <!-- Time labels -->
                <div class="relative">
                    <div
                        v-for="hour in hours"
                        :key="hour"
                        :style="{ height: `${SLOT_HEIGHT}rem` }"
                        class="flex items-start justify-end pr-2 text-[10px] text-gray-400 dark:text-gray-500"
                    >
                        {{ hour.toString().padStart(2, '0') }}:00
                    </div>
                </div>

                <!-- Day columns -->
                <div
                    v-for="(day, dayIndex) in weekDays"
                    :key="day.toISOString()"
                    :ref="
                        (el) => {
                            if (el) dayColumnRefs[dayIndex] = el as HTMLElement;
                        }
                    "
                    class="relative border-l border-gray-200 dark:border-gray-800"
                >
                    <!-- Hour slots -->
                    <div
                        v-for="hour in hours"
                        :key="hour"
                        :class="[
                            'border-b border-gray-100 dark:border-gray-800/50',
                            isAvailable(day, hour)
                                ? 'bg-green-50/50 dark:bg-green-900/10'
                                : '',
                        ]"
                        :style="{ height: `${SLOT_HEIGHT}rem` }"
                        class="cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        @click="handleSlotClick(day, hour)"
                        @drop="handleDrop($event, day, hour)"
                        @dragover="handleDragOver"
                    />

                    <!-- Current time line -->
                    <div
                        v-if="dayIndex === todayColumnIndex()"
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

                    <!-- Booking cards (absolute positioned with overlap) -->
                    <div
                        v-for="{
                            booking,
                            column,
                            totalColumns,
                        } in layoutBookings(bookingsForDay(day))"
                        :key="booking.id"
                        class="absolute z-10"
                        :style="bookingStyle(booking, column, totalColumns)"
                    >
                        <SessionCard
                            :booking="booking"
                            :is-past="isBookingPast(booking)"
                            class="h-full"
                            @click="emit('bookingClick', booking)"
                            @resize-start="
                                handleResizeStart($event, booking, dayIndex)
                            "
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
