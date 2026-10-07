<script setup lang="ts">
import { Calendar, CheckCircle, Download } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import type { SelectedService } from '../../../composables/useBookingFlow';
import type { TimeSlotData } from '../../../types/public';

defineProps<{
    coachName: string;
    service: SelectedService | null;
    timeSlot: TimeSlotData | null;
    guestEmail: string;
    cancellationToken?: string;
}>();

const showConfetti = ref(false);

onMounted(() => {
    showConfetti.value = true;
    setTimeout(() => {
        showConfetti.value = false;
    }, 3000);
});

const formatDateTime = (iso: string) => {
    const d = new Date(iso);
    return (
        d.toLocaleDateString('it-IT', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
        }) +
        ' alle ' +
        d.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })
    );
};
</script>

<template>
    <div class="py-8 text-center">
        <!-- Confetti -->
        <div
            v-if="showConfetti"
            class="pointer-events-none fixed inset-0 z-50 overflow-hidden"
        >
            <div
                v-for="i in 30"
                :key="i"
                class="confetti-piece"
                :style="{
                    left: `${Math.random() * 100}%`,
                    animationDelay: `${Math.random() * 0.5}s`,
                }"
            />
        </div>

        <!-- Success icon -->
        <div
            class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-teal-100 dark:bg-teal-950"
        >
            <CheckCircle :size="32" class="text-teal-600 dark:text-teal-400" />
        </div>

        <h2 class="mb-2 text-2xl font-bold text-gray-900 dark:text-white">
            Prenotazione confermata!
        </h2>
        <p class="mb-6 text-gray-600 dark:text-gray-400">
            Abbiamo inviato una conferma a <strong>{{ guestEmail }}</strong>
        </p>

        <!-- Summary card -->
        <div
            class="mx-auto mb-6 max-w-sm rounded-xl bg-[#eff4ff] p-5 text-left dark:bg-gray-800/50"
        >
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Coach:
                <strong class="text-gray-900 dark:text-white">{{
                    coachName
                }}</strong>
            </p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Servizio:
                <strong class="text-gray-900 dark:text-white">{{
                    service?.name
                }}</strong>
            </p>
            <p
                v-if="timeSlot"
                class="mt-1 text-sm text-gray-500 capitalize dark:text-gray-400"
            >
                Data:
                <strong class="text-gray-900 dark:text-white">{{
                    formatDateTime(timeSlot.starts_at)
                }}</strong>
            </p>
        </div>

        <!-- Actions -->
        <div
            class="mx-auto flex max-w-sm flex-col justify-center gap-3 sm:flex-row"
        >
            <a
                v-if="cancellationToken"
                :href="`/api/v1/bookings/public/${cancellationToken}/calendar.ics`"
                class="flex items-center justify-center gap-2 rounded-xl bg-teal-500 px-5 py-3 text-sm font-medium text-white transition-colors hover:bg-teal-600"
            >
                <Calendar :size="16" />
                Aggiungi al Calendario
            </a>
            <a
                href="#"
                class="flex items-center justify-center gap-2 rounded-xl bg-gray-100 px-5 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
                <Download :size="16" />
                Scarica l'app
            </a>
        </div>
    </div>
</template>

<style scoped>
.confetti-piece {
    position: absolute;
    top: -10px;
    width: 10px;
    height: 10px;
    background: var(--color, #14b8a6);
    animation: confetti-fall 2.5s ease-out forwards;
}
.confetti-piece:nth-child(odd) {
    --color: #f59e0b;
    border-radius: 50%;
}
.confetti-piece:nth-child(even) {
    --color: #14b8a6;
}
.confetti-piece:nth-child(3n) {
    --color: #06b6d4;
    width: 8px;
    height: 8px;
}

@keyframes confetti-fall {
    0% {
        transform: translateY(0) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(100vh) rotate(720deg);
        opacity: 0;
    }
}
</style>
