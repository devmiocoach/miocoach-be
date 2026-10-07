<script setup lang="ts">
import SiteLayout from '@/layouts/SiteLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import BookingSuccess from '../../components/public/booking/BookingSuccess.vue';
import ConfirmStep from '../../components/public/booking/ConfirmStep.vue';
import DateTimeStep from '../../components/public/booking/DateTimeStep.vue';
import GuestDataStep from '../../components/public/booking/GuestDataStep.vue';
import ServiceStep from '../../components/public/booking/ServiceStep.vue';
import { useBookingFlow } from '../../composables/useBookingFlow';
import type { PublicCoachData } from '../../types/public';

const props = defineProps<{
    coach: PublicCoachData;
}>();

const {
    currentStep,
    selectedService,
    selectedDate,
    selectedSlot,
    guestData,
    acceptedTerms,
    isSubmitting,
    bookingResult,
    error,
    canProceed,
    nextStep,
    prevStep,
    selectSingleSession,
    selectPackage,
    submitBooking,
} = useBookingFlow(props.coach.id);

const handleSelectSingle = () => {
    const price =
        props.coach.single_session_price_cents ??
        Math.round(Number(props.coach.price_per_session ?? 0) * 100);
    selectSingleSession(
        price,
        props.coach.session_duration_default ?? '60 min',
    );
};

const handleSelectPackage = (pkgId: number) => {
    const pkg = props.coach.packages.find((p) => p.id === pkgId);
    if (pkg) selectPackage(pkg);
};

const handleConfirm = async () => {
    await submitBooking();
};

const stepLabels = ['Servizio', 'Data e Ora', 'Dati', 'Conferma'];
</script>

<template>
    <SiteLayout
        :title="`Prenota - ${coach?.display_name ?? 'Coach'} | MioCoach`"
        description="Prenota la tua sessione di personal training"
    >
        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Back link -->
            <Link
                :href="`/coach/${coach.slug}`"
                class="mb-6 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
            >
                <ArrowLeft :size="16" />
                Torna al profilo
            </Link>

            <!-- Booking wizard -->
            <div>
                <!-- Step indicator (not on success) -->
                <div v-if="currentStep !== 'success'" class="mb-8">
                    <div class="flex items-center gap-2">
                        <template v-for="(label, i) in stepLabels" :key="i">
                            <div class="flex items-center gap-2">
                                <div
                                    class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold"
                                    :class="
                                        typeof currentStep === 'number' &&
                                        currentStep > i + 1
                                            ? 'bg-teal-500 text-white'
                                            : currentStep === i + 1
                                              ? 'bg-teal-500 text-white'
                                              : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                                    "
                                >
                                    {{ i + 1 }}
                                </div>
                                <span
                                    class="hidden text-xs font-medium text-gray-500 sm:inline dark:text-gray-400"
                                    >{{ label }}</span
                                >
                            </div>
                            <div
                                v-if="i < stepLabels.length - 1"
                                class="h-px flex-1 bg-gray-200 dark:bg-gray-700"
                            />
                        </template>
                    </div>
                </div>

                <!-- Steps -->
                <ServiceStep
                    v-if="currentStep === 1"
                    :coach="coach"
                    :selected-service="selectedService"
                    @select-single="handleSelectSingle"
                    @select-package="handleSelectPackage"
                />

                <DateTimeStep
                    v-else-if="currentStep === 2"
                    :coach-id="coach.id"
                    :selected-date="selectedDate"
                    :selected-slot="selectedSlot"
                    @update:date="selectedDate = $event"
                    @update:slot="selectedSlot = $event"
                />

                <GuestDataStep
                    v-else-if="currentStep === 3"
                    :guest-data="guestData"
                    :accepted-terms="acceptedTerms"
                    @update:guest-data="Object.assign(guestData, $event)"
                    @update:accepted-terms="acceptedTerms = $event"
                />

                <ConfirmStep
                    v-else-if="currentStep === 4"
                    :coach-name="coach.display_name"
                    :service="selectedService"
                    :time-slot="selectedSlot"
                    :guest-name="guestData.name"
                    :is-submitting="isSubmitting"
                    :error="error"
                    @confirm="handleConfirm"
                />

                <BookingSuccess
                    v-else-if="currentStep === 'success'"
                    :coach-name="coach.display_name"
                    :service="selectedService"
                    :time-slot="selectedSlot"
                    :guest-email="guestData.email"
                    :cancellation-token="bookingResult?.cancellation_token"
                />

                <!-- Navigation buttons (not on success or confirm) -->
                <div
                    v-if="currentStep !== 'success' && currentStep !== 4"
                    class="mt-8 flex items-center justify-between pt-6"
                >
                    <button
                        v-if="
                            typeof currentStep === 'number' && currentStep > 1
                        "
                        type="button"
                        class="px-5 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                        @click="prevStep"
                    >
                        Indietro
                    </button>
                    <div v-else />

                    <button
                        type="button"
                        class="rounded-xl bg-teal-500 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-teal-600 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!canProceed"
                        @click="nextStep"
                    >
                        Continua
                    </button>
                </div>
            </div>
        </div>
    </SiteLayout>
</template>
