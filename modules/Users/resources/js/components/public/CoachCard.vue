<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin, Star, User } from 'lucide-vue-next';
import { computed } from 'vue';
import type { CoachListingItem } from '../../types/public';

const props = defineProps<{
    coach: CoachListingItem;
}>();

const modeLabel = computed(() => {
    switch (props.coach.mode) {
        case 'online':
            return 'Online';
        case 'in_person':
            return 'In Presenza';
        case 'hybrid':
            return 'Ibrido';
        default:
            return '';
    }
});

const specializations = computed(() => {
    const specs = props.coach.specializations ?? [];
    return specs.slice(0, 3);
});

const priceLabel = computed(() => {
    if (props.coach.price_from_cents === null) return null;

    return `da ${new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 0,
    }).format(props.coach.price_from_cents / 100)}`;
});

const isTopRated = computed(
    () => props.coach.rating !== null && props.coach.rating >= 4.5,
);
const isNew = computed(
    () => props.coach.reviews_count === 0 && !props.coach.is_verified,
);
</script>

<template>
    <div
        class="group flex flex-col overflow-hidden rounded-xl border border-gray-100 bg-white transition-all hover:shadow-md sm:flex-row dark:border-gray-800 dark:bg-gray-900"
        data-testid="coach-card"
    >
        <!-- Photo -->
        <div
            class="relative h-52 w-full shrink-0 overflow-hidden bg-gray-100 sm:h-auto sm:w-48 dark:bg-gray-800"
        >
            <img
                v-if="coach.profile_photo_url"
                :src="coach.profile_photo_url"
                :alt="coach.display_name"
                class="h-full w-full object-cover"
            />
            <div v-else class="flex h-full w-full items-center justify-center">
                <User :size="48" class="text-gray-300 dark:text-gray-600" />
            </div>
            <span
                v-if="isTopRated"
                class="absolute top-3 left-3 rounded-md bg-teal-500 px-2.5 py-1 text-[10px] font-bold tracking-wider text-white uppercase"
            >
                TOP RATED
            </span>
            <span
                v-else-if="isNew"
                class="absolute top-3 left-3 rounded-md bg-emerald-500 px-2.5 py-1 text-[10px] font-bold tracking-wider text-white uppercase"
            >
                NUOVO
            </span>
        </div>

        <!-- Content -->
        <div class="flex min-w-0 flex-1 flex-col justify-between p-5">
            <div>
                <!-- Name + Rating -->
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ coach.display_name }}
                    </h3>
                    <div
                        v-if="coach.rating"
                        class="flex shrink-0 items-center gap-1 rounded-md bg-teal-50 px-2 py-1 dark:bg-teal-950"
                    >
                        <Star
                            :size="13"
                            class="fill-amber-400 text-amber-400"
                        />
                        <span
                            class="text-sm font-bold text-gray-900 dark:text-white"
                            >{{ coach.rating }}</span
                        >
                    </div>
                </div>

                <!-- Specializations -->
                <div
                    v-if="specializations.length"
                    class="mt-1.5 flex flex-wrap gap-1"
                >
                    <span
                        v-for="(spec, idx) in specializations"
                        :key="spec.slug"
                        class="text-xs font-bold tracking-wide text-teal-600 uppercase dark:text-teal-400"
                    >
                        {{ spec.name
                        }}<span v-if="idx < specializations.length - 1"
                            >&nbsp;&bull;&nbsp;</span
                        >
                    </span>
                </div>

                <!-- Bio excerpt -->
                <p
                    class="mt-3 line-clamp-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400"
                >
                    {{ coach.bio_excerpt }}
                </p>
            </div>

            <!-- Footer -->
            <div class="mt-4 flex items-center justify-between">
                <div
                    class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400"
                >
                    <span
                        v-if="coach.city || modeLabel"
                        class="flex items-center gap-1"
                    >
                        <MapPin :size="14" class="text-gray-400" />
                        <span v-if="modeLabel && coach.city"
                            >{{ modeLabel }}, {{ coach.city }}</span
                        >
                        <span v-else-if="coach.city">{{ coach.city }}</span>
                        <span v-else>{{ modeLabel }}</span>
                    </span>
                    <span
                        v-if="priceLabel"
                        class="text-lg font-bold text-gray-900 dark:text-white"
                    >
                        {{ priceLabel }}</span
                    >
                </div>

                <Link
                    :href="`/coach/${coach.slug}`"
                    class="rounded-lg border border-teal-500 px-4 py-2 text-sm font-semibold text-teal-600 transition-colors hover:bg-teal-50 dark:border-teal-600 dark:text-teal-400 dark:hover:bg-teal-950"
                    data-testid="view-profile"
                >
                    Vedi Profilo
                </Link>
            </div>
        </div>
    </div>
</template>
