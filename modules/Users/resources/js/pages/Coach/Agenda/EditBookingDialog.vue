<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import type {
    AgendaBooking,
    ServiceType,
} from '@modules/Users/resources/js/types/agenda';

const props = defineProps<{
    booking: AgendaBooking | null;
    serviceTypes: ServiceType[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const isOpen = computed({
    get: () => props.booking !== null,
    set: (val) => {
        if (!val) emit('close');
    },
});

const editing = ref(false);
const showCancelForm = ref(false);
const cancelReason = ref('');

const form = useForm({
    starts_at: '',
    ends_at: '',
    service_type: '',
    notes: '',
    price: null as number | null,
});

watch(isOpen, (val) => {
    if (!val) {
        editing.value = false;
        showCancelForm.value = false;
        cancelReason.value = '';
    }
});

watch(
    () => props.booking,
    (b) => {
        if (b) {
            form.starts_at = b.starts_at;
            form.ends_at = b.ends_at || '';
            form.service_type = b.service_type || '';
            form.notes = b.notes || '';
            form.price = b.price;
        }
    },
);

function formatDateTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('it-IT', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDateTimeInput(iso: string): string {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function startEdit() {
    editing.value = true;
}

function cancelEdit() {
    editing.value = false;
    if (props.booking) {
        form.starts_at = props.booking.starts_at;
        form.ends_at = props.booking.ends_at || '';
        form.service_type = props.booking.service_type || '';
        form.notes = props.booking.notes || '';
        form.price = props.booking.price;
    }
}

function saveEdit() {
    if (!props.booking) return;
    form.patch(route('coach.agenda.bookings.update', props.booking.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
            emit('close');
        },
    });
}

function markComplete() {
    if (!props.booking) return;
    router.patch(
        route('coach.agenda.bookings.complete', props.booking.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        },
    );
}

function cancelBooking() {
    if (!props.booking) return;
    router.delete(route('coach.agenda.bookings.destroy', props.booking.id), {
        data: { reason: cancelReason.value },
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}

function markNoShow() {
    if (!props.booking) return;
    router.patch(
        route('coach.agenda.bookings.no-show', props.booking.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent
            v-if="booking"
            class="max-w-md"
            data-testid="booking-detail-dialog"
        >
            <DialogHeader>
                <DialogTitle>
                    {{
                        booking.client?.name ||
                        booking.guest_name ||
                        $t('Session')
                    }}
                </DialogTitle>
            </DialogHeader>

            <!-- View mode -->
            <div v-if="!editing" class="space-y-4">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">{{
                            $t('Time')
                        }}</span>
                        <span class="text-gray-900 dark:text-white">
                            {{ formatDateTime(booking.starts_at) }} —
                            {{
                                booking.ends_at
                                    ? formatDateTime(booking.ends_at)
                                    : ''
                            }}
                        </span>
                    </div>
                    <div
                        v-if="booking.service_type_label"
                        class="flex justify-between"
                    >
                        <span class="text-gray-500 dark:text-gray-400">{{
                            $t('Service type')
                        }}</span>
                        <Badge variant="secondary">{{
                            booking.service_type_label
                        }}</Badge>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">{{
                            $t('Status')
                        }}</span>
                        <Badge
                            :variant="
                                booking.status === 'completed'
                                    ? 'default'
                                    : 'outline'
                            "
                        >
                            {{
                                $t(
                                    booking.status.charAt(0).toUpperCase() +
                                        booking.status.slice(1),
                                )
                            }}
                        </Badge>
                    </div>
                    <div v-if="booking.price" class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">{{
                            $t('Price')
                        }}</span>
                        <span class="font-medium text-gray-900 dark:text-white"
                            >€{{ booking.price.toFixed(2) }}</span
                        >
                    </div>
                    <div v-if="booking.notes">
                        <span class="text-gray-500 dark:text-gray-400">{{
                            $t('Notes')
                        }}</span>
                        <p
                            class="mt-1 rounded bg-gray-50 p-2 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        >
                            {{ booking.notes }}
                        </p>
                    </div>
                </div>

                <!-- Cancel form -->
                <div v-if="showCancelForm" class="space-y-2">
                    <Textarea
                        v-model="cancelReason"
                        :placeholder="$t('Reason for cancellation...')"
                        rows="2"
                    />
                    <div class="flex gap-2">
                        <Button
                            variant="destructive"
                            size="sm"
                            data-testid="confirm-cancel-booking"
                            @click="cancelBooking"
                        >
                            {{ $t('Confirm cancellation') }}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="showCancelForm = false"
                        >
                            {{ $t('Back') }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Edit mode -->
            <div v-else class="space-y-4">
                <div class="space-y-3">
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
                            >{{ $t('Start') }}</label
                        >
                        <Input
                            :model-value="formatDateTimeInput(form.starts_at)"
                            type="datetime-local"
                            @update:model-value="
                                form.starts_at = new Date($event).toISOString()
                            "
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
                            >{{ $t('End') }}</label
                        >
                        <Input
                            :model-value="formatDateTimeInput(form.ends_at)"
                            type="datetime-local"
                            @update:model-value="
                                form.ends_at = new Date($event).toISOString()
                            "
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
                            >{{ $t('Service type') }}</label
                        >
                        <select
                            v-model="form.service_type"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <option
                                v-for="st in serviceTypes"
                                :key="st.value"
                                :value="st.value"
                            >
                                {{ st.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
                            >{{ $t('Price') }}</label
                        >
                        <Input
                            v-model.number="form.price"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
                            >{{ $t('Notes') }}</label
                        >
                        <Textarea v-model="form.notes" rows="3" />
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter v-if="!showCancelForm">
                <template v-if="editing">
                    <Button variant="outline" size="sm" @click="cancelEdit">
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        size="sm"
                        :disabled="form.processing"
                        data-testid="save-booking-btn"
                        @click="saveEdit"
                    >
                        {{ $t('Save') }}
                    </Button>
                </template>
                <template v-else-if="booking.status === 'confirmed'">
                    <Button
                        variant="outline"
                        size="sm"
                        data-testid="edit-booking-btn"
                        @click="startEdit"
                    >
                        {{ $t('Edit') }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        data-testid="no-show-booking-btn"
                        @click="markNoShow"
                    >
                        {{ $t('No-show') }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        data-testid="cancel-booking-btn"
                        @click="showCancelForm = true"
                    >
                        {{ $t('Cancel session') }}
                    </Button>
                    <Button
                        size="sm"
                        data-testid="complete-booking-btn"
                        @click="markComplete"
                    >
                        {{ $t('Mark completed') }}
                    </Button>
                </template>
                <template v-else>
                    <Button
                        variant="outline"
                        size="sm"
                        data-testid="edit-booking-btn"
                        @click="startEdit"
                    >
                        {{ $t('Edit') }}
                    </Button>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
