<script setup lang="ts">
import { CreditCard, Lock } from 'lucide-vue-next';
import type { SelectedService } from '../../../composables/useBookingFlow';
import type { TimeSlotData } from '../../../types/public';

defineProps<{
    coachName: string;
    service: SelectedService | null;
    timeSlot: TimeSlotData | null;
    guestName: string;
    isSubmitting: boolean;
    error: string | null;
}>();

const emit = defineEmits<{
    confirm: [];
}>();

const formatDateTime = (iso: string) => {
    const d = new Date(iso);
    return (
        d.toLocaleDateString('it-IT', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }) +
        ' alle ' +
        d.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })
    );
};

const formatPrice = (cents: number) => (cents / 100).toFixed(2);
</script>

<template>
    <div>
        <h2 class="mb-2 text-xl font-bold text-gray-900 dark:text-white">
            Conferma prenotazione
        </h2>
        <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
            Verifica i dettagli e conferma
        </p>

        <!-- Summary -->
        <div
            class="mb-6 space-y-3 rounded-xl bg-[#eff4ff] p-5 dark:bg-gray-800/50"
        >
            <div class="flex justify-between text-sm">
                <span class="text-gray-500 dark:text-gray-400">Coach</span>
                <span class="font-medium text-gray-900 dark:text-white">{{
                    coachName
                }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500 dark:text-gray-400">Servizio</span>
                <span class="font-medium text-gray-900 dark:text-white">{{
                    service?.name
                }}</span>
            </div>
            <div v-if="timeSlot" class="flex justify-between text-sm">
                <span class="text-gray-500 dark:text-gray-400">Data e ora</span>
                <span
                    class="font-medium text-gray-900 capitalize dark:text-white"
                    >{{ formatDateTime(timeSlot.starts_at) }}</span
                >
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500 dark:text-gray-400">Cliente</span>
                <span class="font-medium text-gray-900 dark:text-white">{{
                    guestName
                }}</span>
            </div>
            <hr class="border-gray-200 dark:border-gray-700" />
            <div class="flex justify-between">
                <span class="font-semibold text-gray-900 dark:text-white"
                    >Totale</span
                >
                <span class="text-xl font-bold text-gray-900 dark:text-white">
                    {{ service ? formatPrice(service.price_cents) : '0' }}€
                </span>
            </div>
        </div>

        <!-- Stripe mock -->
        <div
            v-if="service && service.price_cents > 0"
            class="mb-6 rounded-xl bg-white p-5 shadow-[0_2px_12px_rgba(0,0,0,0.04)] dark:bg-gray-900 dark:shadow-none"
        >
            <div class="mb-4 flex items-center gap-2">
                <CreditCard :size="18" class="text-gray-500" />
                <span
                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                    >Pagamento con carta</span
                >
                <Lock :size="14" class="ml-auto text-gray-400" />
            </div>
            <div class="space-y-3">
                <div
                    class="flex h-11 items-center rounded-lg bg-gray-100 px-4 dark:bg-gray-800"
                >
                    <span class="text-sm text-gray-400"
                        >4242 4242 4242 4242</span
                    >
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div
                        class="flex h-11 items-center rounded-lg bg-gray-100 px-4 dark:bg-gray-800"
                    >
                        <span class="text-sm text-gray-400">MM/AA</span>
                    </div>
                    <div
                        class="flex h-11 items-center rounded-lg bg-gray-100 px-4 dark:bg-gray-800"
                    >
                        <span class="text-sm text-gray-400">CVC</span>
                    </div>
                </div>
            </div>
            <p class="mt-3 text-center text-xs text-gray-400">
                Pagamento sicuro con Stripe (demo)
            </p>
        </div>

        <!-- Error -->
        <p v-if="error" class="mb-4 text-sm text-red-600 dark:text-red-400">
            {{ error }}
        </p>

        <!-- Confirm button -->
        <button
            type="button"
            class="w-full rounded-xl bg-gradient-to-r from-teal-600 to-teal-500 py-4 text-base font-semibold text-white shadow-[0_4px_16px_rgba(20,184,166,0.3)] transition-all hover:from-teal-700 hover:to-teal-600 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="isSubmitting"
            @click="emit('confirm')"
        >
            {{
                isSubmitting
                    ? 'Prenotazione in corso...'
                    : 'Conferma prenotazione'
            }}
        </button>
    </div>
</template>
