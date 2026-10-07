<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { CoachPackageData, PublicCoachData } from '../../../types/public';

const props = defineProps<{
    coach: PublicCoachData;
}>();

const formatPrice = (cents: number) => {
    return (cents / 100).toFixed(0);
};

const singleSessionPrice = () => {
    if (props.coach.single_session_price_cents) {
        return formatPrice(props.coach.single_session_price_cents);
    }
    if (props.coach.price_per_session) {
        return Math.round(Number(props.coach.price_per_session));
    }
    return null;
};

const packageSavings = (pkg: CoachPackageData) => {
    const singlePrice = props.coach.single_session_price_cents;
    if (!singlePrice || !pkg.sessions_count) return null;
    const fullPrice = singlePrice * pkg.sessions_count;
    const savings = fullPrice - pkg.price_cents;
    return savings > 0 ? formatPrice(savings) : null;
};
</script>

<template>
    <section class="py-8">
        <h2 class="mb-4 text-xl font-bold text-gray-900 dark:text-white">
            Tariffe
        </h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Single session -->
            <div
                v-if="singleSessionPrice()"
                class="rounded-xl bg-white p-5 shadow-[0_2px_12px_rgba(0,0,0,0.04)] dark:bg-gray-900 dark:shadow-none"
            >
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Sessione singola
                </p>
                <p
                    class="mt-2 text-3xl font-bold text-gray-900 dark:text-white"
                >
                    {{ singleSessionPrice() }}€
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ coach.session_duration_default ?? '60' }} minuti
                </p>
                <span
                    v-if="coach.free_first_session"
                    class="mt-3 inline-block rounded bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400"
                >
                    Prima sessione gratuita
                </span>
                <Link
                    :href="`/coach/${coach.slug}/book`"
                    class="mt-4 block w-full rounded-lg bg-teal-500 px-4 py-2.5 text-center text-sm font-medium text-white transition-colors hover:bg-teal-600"
                >
                    Prenota
                </Link>
            </div>

            <!-- Packages -->
            <div
                v-for="pkg in coach.packages"
                :key="pkg.id"
                class="rounded-xl bg-white p-5 shadow-[0_2px_12px_rgba(0,0,0,0.04)] dark:bg-gray-900 dark:shadow-none"
            >
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ pkg.name }}
                </p>
                <p
                    class="mt-2 text-3xl font-bold text-gray-900 dark:text-white"
                >
                    {{ pkg.price_in_euros }}€
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ pkg.sessions_count }} sessioni &middot;
                    {{ pkg.validity_days }} giorni
                </p>
                <span
                    v-if="packageSavings(pkg)"
                    class="mt-3 inline-block rounded bg-amber-50 px-2 py-1 text-xs font-medium text-amber-600 dark:bg-amber-950 dark:text-amber-400"
                >
                    Risparmi {{ packageSavings(pkg) }}€
                </span>
                <p
                    v-if="pkg.description"
                    class="mt-2 line-clamp-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ pkg.description }}
                </p>
                <Link
                    :href="`/coach/${coach.slug}/book`"
                    class="mt-4 block w-full rounded-lg bg-teal-500 px-4 py-2.5 text-center text-sm font-medium text-white transition-colors hover:bg-teal-600"
                >
                    Prenota
                </Link>
            </div>
        </div>
    </section>
</template>
