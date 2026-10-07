<script setup lang="ts">
import type { AgendaBooking } from '@modules/Users/resources/js/types/agenda';

const props = withDefaults(
    defineProps<{
        booking: AgendaBooking;
        isPast?: boolean;
        resizable?: boolean;
    }>(),
    { isPast: false, resizable: true },
);

const emit = defineEmits<{
    click: [];
    resizeStart: [event: MouseEvent];
}>();

const SERVICE_COLORS: Record<string, string> = {
    personal_training:
        'bg-blue-100 border-blue-300 text-blue-800 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-300',
    online_coaching:
        'bg-indigo-100 border-indigo-300 text-indigo-800 dark:bg-indigo-900/30 dark:border-indigo-700 dark:text-indigo-300',
    assessment:
        'bg-amber-100 border-amber-300 text-amber-800 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-300',
    group_session:
        'bg-green-100 border-green-300 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300',
    nutrition_consultation:
        'bg-purple-100 border-purple-300 text-purple-800 dark:bg-purple-900/30 dark:border-purple-700 dark:text-purple-300',
};

function getColorClass(): string {
    if (!props.booking.service_type)
        return 'bg-gray-100 border-gray-300 text-gray-800 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300';
    return (
        SERVICE_COLORS[props.booking.service_type] ||
        SERVICE_COLORS.personal_training
    );
}

function getOpacityClass(): string {
    if (props.booking.status === 'no_show') return 'opacity-50 line-through';
    if (props.booking.status === 'completed') return 'opacity-60';
    if (props.isPast && props.booking.status === 'confirmed')
        return 'opacity-50';
    return '';
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('it-IT', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function handleDragStart(event: DragEvent) {
    if (!event.dataTransfer) return;
    event.dataTransfer.setData('booking-id', props.booking.id.toString());
    event.dataTransfer.setData(
        'duration',
        (props.booking.duration_minutes || 60).toString(),
    );
    event.dataTransfer.effectAllowed = 'move';
}
</script>

<template>
    <div
        :class="[
            'group relative cursor-pointer overflow-hidden rounded border px-1.5 py-0.5 text-[11px] leading-tight shadow-sm transition-shadow hover:shadow-md',
            getColorClass(),
            getOpacityClass(),
        ]"
        :data-testid="`session-card-${booking.id}`"
        draggable="true"
        @click.stop="emit('click')"
        @dragstart="handleDragStart"
    >
        <div class="font-medium">
            {{ formatTime(booking.starts_at) }}
        </div>
        <div class="truncate">
            {{ booking.client?.name || booking.guest_name || '—' }}
        </div>
        <!-- Resize handle -->
        <div
            v-if="resizable"
            class="absolute inset-x-0 bottom-0 flex h-2 cursor-s-resize items-center justify-center opacity-0 transition-opacity group-hover:opacity-100"
            @mousedown.stop.prevent="emit('resizeStart', $event)"
        >
            <div class="h-0.5 w-4 rounded-full bg-current opacity-60" />
        </div>
    </div>
</template>
