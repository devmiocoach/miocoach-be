import { computed, ref } from 'vue';
import type {
    BookingFormData,
    CoachPackageData,
    TimeSlotData,
} from '../types/public';

export type BookingStep = 1 | 2 | 3 | 4 | 'success';

export interface SelectedService {
    type: 'single_session' | 'package';
    package_id: number | null;
    name: string;
    price_cents: number;
    sessions_count: number;
}

export function useBookingFlow(coachId: number) {
    const currentStep = ref<BookingStep>(1);
    const selectedService = ref<SelectedService | null>(null);
    const selectedDate = ref<string | null>(null);
    const selectedSlot = ref<TimeSlotData | null>(null);
    const guestData = ref({
        name: '',
        email: '',
        phone: '',
    });
    const acceptedTerms = ref(false);
    const isSubmitting = ref(false);
    const bookingResult = ref<{ cancellation_token?: string } | null>(null);
    const error = ref<string | null>(null);

    const canProceed = computed(() => {
        switch (currentStep.value) {
            case 1:
                return selectedService.value !== null;
            case 2:
                return selectedSlot.value !== null;
            case 3:
                return (
                    guestData.value.name.trim() !== '' &&
                    guestData.value.email.trim() !== '' &&
                    acceptedTerms.value
                );
            case 4:
                return true;
            default:
                return false;
        }
    });

    const nextStep = () => {
        if (currentStep.value === 'success') return;
        if (currentStep.value < 4) {
            currentStep.value = (currentStep.value + 1) as BookingStep;
        }
    };

    const prevStep = () => {
        if (currentStep.value === 'success') return;
        if (typeof currentStep.value === 'number' && currentStep.value > 1) {
            currentStep.value = (currentStep.value - 1) as BookingStep;
        }
    };

    const goToStep = (step: BookingStep) => {
        currentStep.value = step;
    };

    const selectService = (service: SelectedService) => {
        selectedService.value = service;
    };

    const selectSingleSession = (priceCents: number, durationLabel: string) => {
        selectedService.value = {
            type: 'single_session',
            package_id: null,
            name: `Sessione singola (${durationLabel})`,
            price_cents: priceCents,
            sessions_count: 1,
        };
    };

    const selectPackage = (pkg: CoachPackageData) => {
        selectedService.value = {
            type: 'package',
            package_id: pkg.id,
            name: pkg.name,
            price_cents: pkg.price_cents,
            sessions_count: pkg.sessions_count,
        };
    };

    const submitBooking = async (): Promise<boolean> => {
        if (!selectedSlot.value || !selectedService.value) return false;

        isSubmitting.value = true;
        error.value = null;

        const payload: BookingFormData = {
            coach_id: coachId,
            service_type: selectedService.value.type,
            package_id: selectedService.value.package_id,
            slot_starts_at: selectedSlot.value.starts_at,
            duration_minutes: selectedSlot.value.duration_minutes,
            guest_name: guestData.value.name,
            guest_email: guestData.value.email,
            guest_phone: guestData.value.phone,
        };

        try {
            const response = await fetch('/api/v1/bookings/public', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                const data = await response.json();
                error.value = data.message || 'Errore durante la prenotazione.';
                return false;
            }

            const data = await response.json();
            bookingResult.value = data.data ?? data;
            currentStep.value = 'success';
            return true;
        } catch {
            error.value = 'Errore di rete. Riprova.';
            return false;
        } finally {
            isSubmitting.value = false;
        }
    };

    return {
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
        goToStep,
        selectService,
        selectSingleSession,
        selectPackage,
        submitBooking,
    };
}
