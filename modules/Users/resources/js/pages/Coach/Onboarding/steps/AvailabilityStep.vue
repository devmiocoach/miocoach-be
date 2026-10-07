<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Slot {
    day_of_week: number;
    start_time: string;
    end_time: string;
}

interface CoachData {
    session_duration_default: string | null;
    cancellation_window_hours: number | null;
}

interface AvailabilitySlot {
    id?: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
}

const props = defineProps<{
    coach: CoachData | null;
    availabilitySlots: AvailabilitySlot[];
}>();

const DAY_LABELS = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
const DURATION_OPTIONS = [
    { value: '45', label: '45 min' },
    { value: '60', label: '60 min' },
    { value: '90', label: '90 min' },
];
const CANCELLATION_OPTIONS = [
    { value: '12', label: '12 ore' },
    { value: '24', label: '24 ore' },
    { value: '48', label: '48 ore' },
];

const sessionDuration = ref(props.coach?.session_duration_default ?? '60');
const cancellationWindow = ref(
    String(props.coach?.cancellation_window_hours ?? '24'),
);

// Build per-day slots map
const slotsByDay = ref<Record<number, Slot[]>>(
    Object.fromEntries(Array.from({ length: 7 }, (_, i) => [i, []])),
);

// Populate from existing slots
props.availabilitySlots.forEach((s) => {
    slotsByDay.value[s.day_of_week].push({
        day_of_week: s.day_of_week,
        start_time: s.start_time,
        end_time: s.end_time,
    });
});

function addSlot(day: number) {
    slotsByDay.value[day].push({
        day_of_week: day,
        start_time: '09:00',
        end_time: '13:00',
    });
}

function removeSlot(day: number, index: number) {
    slotsByDay.value[day].splice(index, 1);
}

function getData() {
    const slots: Slot[] = [];
    for (let d = 0; d < 7; d++) {
        slots.push(...slotsByDay.value[d]);
    }
    return {
        slots,
        session_duration_default: sessionDuration.value,
        cancellation_window_hours: cancellationWindow.value,
    };
}

defineExpose({ getData });
</script>

<template>
    <div class="space-y-6">
        <!-- Segmented controls: Duration + Cancellation -->
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <span
                    class="text-sm font-medium text-gray-600 dark:text-gray-400"
                    >Durata</span
                >
                <div
                    class="inline-flex rounded-xl border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-900"
                >
                    <button
                        v-for="opt in DURATION_OPTIONS"
                        :key="opt.value"
                        :data-testid="`duration-${opt.value}`"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                            sessionDuration === opt.value
                                ? 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300'
                                : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200',
                        ]"
                        @click="sessionDuration = opt.value"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span
                    class="text-sm font-medium text-gray-600 dark:text-gray-400"
                    >Cancellazione</span
                >
                <div
                    class="inline-flex rounded-xl border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-900"
                >
                    <button
                        v-for="opt in CANCELLATION_OPTIONS"
                        :key="opt.value"
                        :data-testid="`cancel-window-${opt.value}`"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                            cancellationWindow === opt.value
                                ? 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300'
                                : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200',
                        ]"
                        @click="cancellationWindow = opt.value"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Weekly availability card -->
        <div
            class="rounded-3xl border border-gray-200 bg-white p-7 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="space-y-3">
                <div
                    v-for="day in 7"
                    :key="day - 1"
                    :data-testid="`day-row-${day - 1}`"
                    :class="[
                        'group rounded-2xl border p-4 transition-colors',
                        slotsByDay[day - 1].length > 0
                            ? 'border-teal-200 bg-teal-50/20 dark:border-teal-800 dark:bg-teal-900/10'
                            : 'border-gray-200 dark:border-gray-800',
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <!-- Toggle switch button -->
                        <button
                            :data-testid="`toggle-day-${day - 1}`"
                            :class="[
                                'relative h-6 w-11 shrink-0 rounded-full transition-colors',
                                slotsByDay[day - 1].length > 0
                                    ? 'bg-teal-500 dark:bg-teal-600'
                                    : 'bg-gray-300 dark:bg-gray-600',
                            ]"
                            @click="
                                slotsByDay[day - 1].length > 0
                                    ? slotsByDay[day - 1].splice(0)
                                    : addSlot(day - 1)
                            "
                        >
                            <span
                                :class="[
                                    'absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform',
                                    slotsByDay[day - 1].length > 0
                                        ? 'translate-x-5'
                                        : 'translate-x-0',
                                ]"
                            />
                        </button>

                        <!-- Day label -->
                        <span
                            :class="[
                                'w-10 text-sm font-bold',
                                slotsByDay[day - 1].length > 0
                                    ? 'text-gray-900 dark:text-white'
                                    : 'text-gray-400 dark:text-gray-600',
                            ]"
                        >
                            {{ DAY_LABELS[day - 1] }}
                        </span>

                        <!-- Slots or Non disponibile -->
                        <div
                            v-if="slotsByDay[day - 1].length > 0"
                            class="flex flex-1 flex-wrap items-center gap-2"
                        >
                            <div
                                v-for="(slot, idx) in slotsByDay[day - 1]"
                                :key="idx"
                                class="flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-2.5 py-1.5 dark:border-gray-700 dark:bg-gray-800"
                            >
                                <input
                                    v-model="slot.start_time"
                                    type="time"
                                    :data-testid="`slot-start-${day - 1}-${idx}`"
                                    class="border-0 bg-transparent text-sm font-medium text-gray-700 focus:outline-none dark:text-gray-300"
                                />
                                <span class="text-gray-400">–</span>
                                <input
                                    v-model="slot.end_time"
                                    type="time"
                                    :data-testid="`slot-end-${day - 1}-${idx}`"
                                    class="border-0 bg-transparent text-sm font-medium text-gray-700 focus:outline-none dark:text-gray-300"
                                />
                                <button
                                    :data-testid="`remove-slot-${day - 1}-${idx}`"
                                    class="ml-1 text-gray-400 transition hover:text-red-500"
                                    @click="removeSlot(day - 1, idx)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <!-- Add slot button -->
                            <button
                                :data-testid="`add-slot-${day - 1}`"
                                class="flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-semibold text-teal-600 transition hover:bg-teal-50 dark:text-teal-400 dark:hover:bg-teal-900/30"
                                @click="addSlot(day - 1)"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                Fascia
                            </button>
                        </div>

                        <span
                            v-else
                            class="text-sm text-gray-400 italic dark:text-gray-600"
                            >Non disponibile</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
