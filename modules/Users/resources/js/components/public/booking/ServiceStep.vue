<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import type { SelectedService } from '../../../composables/useBookingFlow';
import type { PublicCoachData } from '../../../types/public';

const props = defineProps<{
    coach: PublicCoachData;
    selectedService: SelectedService | null;
}>();

const emit = defineEmits<{
    selectSingle: [];
    selectPackage: [id: number];
}>();

const singlePrice = () => {
    if (props.coach.single_session_price_cents) {
        return (props.coach.single_session_price_cents / 100).toFixed(0);
    }
    if (props.coach.price_per_session) {
        return Math.round(Number(props.coach.price_per_session)).toString();
    }
    return null;
};

const isSelected = (type: string, pkgId?: number) => {
    if (!props.selectedService) return false;
    if (type === 'single')
        return props.selectedService.type === 'single_session';
    return props.selectedService.package_id === pkgId;
};
</script>

<template>
    <div>
        <h2 class="mb-2 text-xl font-bold text-gray-900 dark:text-white">
            Scegli il servizio
        </h2>
        <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
            Seleziona la tipologia di sessione che preferisci
        </p>

        <div class="space-y-3">
            <!-- Single session -->
            <button
                v-if="singlePrice()"
                type="button"
                class="w-full rounded-xl p-4 text-left transition-all"
                :class="
                    isSelected('single')
                        ? 'bg-teal-50 ring-2 ring-teal-500 dark:bg-teal-950'
                        : 'bg-[#eff4ff] hover:bg-teal-50/50 dark:bg-gray-800/50 dark:hover:bg-teal-950/50'
                "
                @click="emit('selectSingle')"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">
                            Sessione singola
                        </p>
                        <p
                            class="mt-0.5 text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ coach.session_duration_default ?? '60' }} minuti
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span
                            class="text-xl font-bold text-gray-900 dark:text-white"
                            >{{ singlePrice() }}€</span
                        >
                        <div
                            class="flex h-5 w-5 items-center justify-center rounded-full border-2"
                            :class="
                                isSelected('single')
                                    ? 'border-teal-500 bg-teal-500'
                                    : 'border-gray-300 dark:border-gray-600'
                            "
                        >
                            <Check
                                v-if="isSelected('single')"
                                :size="12"
                                class="text-white"
                            />
                        </div>
                    </div>
                </div>
                <span
                    v-if="coach.free_first_session"
                    class="mt-2 inline-block rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400"
                >
                    Prima sessione gratuita
                </span>
            </button>

            <!-- Packages -->
            <button
                v-for="pkg in coach.packages"
                :key="pkg.id"
                type="button"
                class="w-full rounded-xl p-4 text-left transition-all"
                :class="
                    isSelected('package', pkg.id)
                        ? 'bg-teal-50 ring-2 ring-teal-500 dark:bg-teal-950'
                        : 'bg-[#eff4ff] hover:bg-teal-50/50 dark:bg-gray-800/50 dark:hover:bg-teal-950/50'
                "
                @click="emit('selectPackage', pkg.id)"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ pkg.name }}
                        </p>
                        <p
                            class="mt-0.5 text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ pkg.sessions_count }} sessioni &middot; Valido
                            {{ pkg.validity_days }} giorni
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span
                            class="text-xl font-bold text-gray-900 dark:text-white"
                            >{{ pkg.price_in_euros }}€</span
                        >
                        <div
                            class="flex h-5 w-5 items-center justify-center rounded-full border-2"
                            :class="
                                isSelected('package', pkg.id)
                                    ? 'border-teal-500 bg-teal-500'
                                    : 'border-gray-300 dark:border-gray-600'
                            "
                        >
                            <Check
                                v-if="isSelected('package', pkg.id)"
                                :size="12"
                                class="text-white"
                            />
                        </div>
                    </div>
                </div>
                <p
                    v-if="pkg.description"
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ pkg.description }}
                </p>
            </button>
        </div>
    </div>
</template>
