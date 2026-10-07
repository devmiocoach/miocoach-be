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
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import type { AgendaBooking } from '@modules/Users/resources/js/types/agenda';

const props = defineProps<{
    booking: AgendaBooking | null;
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

const showCancelForm = ref(false);
const cancelReason = ref('');

watch(isOpen, (val) => {
    if (!val) {
        showCancelForm.value = false;
        cancelReason.value = '';
    }
});

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

            <div class="space-y-4">
                <!-- Info -->
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

            <DialogFooter
                v-if="!showCancelForm && booking.status === 'confirmed'"
            >
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
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
