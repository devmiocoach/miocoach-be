<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Calendar,
    CheckCircle,
    MapPin,
    MessageCircle,
    Monitor,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type { PublicCoachData } from '../../../types/public';
import RatingStars from '../RatingStars.vue';

const props = defineProps<{
    coach: PublicCoachData;
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

const modeIcon = computed(() => {
    switch (props.coach.mode) {
        case 'online':
            return Monitor;
        case 'hybrid':
            return Users;
        default:
            return MapPin;
    }
});
</script>

<template>
    <section class="relative">
        <!-- Cover -->
        <div
            class="h-48 w-full overflow-hidden bg-gradient-to-br from-teal-600 to-teal-400 sm:h-64 lg:h-72"
        >
            <img
                v-if="coach.cover_photo_url"
                :src="coach.cover_photo_url"
                :alt="`Cover ${coach.display_name}`"
                class="h-full w-full object-cover opacity-80"
            />
        </div>

        <!-- Profile info -->
        <div
            class="relative z-10 mx-auto -mt-16 max-w-5xl px-4 sm:px-6 lg:px-8"
        >
            <div class="flex flex-col items-start gap-5 sm:flex-row">
                <!-- Avatar -->
                <div
                    class="h-28 w-28 shrink-0 overflow-hidden rounded-full border-4 border-white bg-gray-200 shadow-lg sm:h-32 sm:w-32 dark:border-gray-900 dark:bg-gray-800"
                >
                    <img
                        v-if="coach.profile_photo_url"
                        :src="coach.profile_photo_url"
                        :alt="coach.display_name"
                        class="h-full w-full object-cover"
                    />
                </div>

                <!-- Info -->
                <div class="flex-1 pt-2 sm:pt-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h1
                                class="text-2xl font-bold text-gray-900 sm:text-3xl dark:text-white"
                            >
                                {{ coach.display_name }}
                            </h1>
                            <p
                                v-if="coach.tagline"
                                class="mt-1 text-gray-600 dark:text-gray-400"
                            >
                                {{ coach.tagline }}
                            </p>
                        </div>
                        <div v-if="coach.is_verified" class="shrink-0">
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2 py-1 text-xs font-medium text-teal-600 dark:bg-teal-950 dark:text-teal-400"
                            >
                                <CheckCircle :size="14" />
                                Verificato
                            </span>
                        </div>
                    </div>

                    <!-- Meta -->
                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        <span
                            v-if="coach.city"
                            class="flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            <MapPin :size="14" />
                            {{ coach.city
                            }}<span v-if="coach.province"
                                >, {{ coach.province }}</span
                            >
                        </span>
                        <span
                            v-if="coach.mode"
                            class="flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            <component :is="modeIcon" :size="14" />
                            {{ modeLabel }}
                        </span>
                        <div
                            v-if="coach.rating"
                            class="flex items-center gap-1.5"
                        >
                            <RatingStars :rating="coach.rating" :size="16" />
                            <span
                                class="text-sm font-medium text-gray-900 dark:text-white"
                                >{{ coach.rating }}</span
                            >
                            <span
                                class="text-sm text-gray-500 dark:text-gray-400"
                                >({{ coach.reviews_count }} recensioni)</span
                            >
                        </div>
                    </div>

                    <!-- CTAs -->
                    <div class="mt-5 flex flex-wrap gap-3">
                        <Link
                            :href="`/coach/${coach.slug}/book`"
                            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-teal-600 to-teal-500 px-6 py-3 text-sm font-semibold text-white shadow-[0_4px_16px_rgba(20,184,166,0.3)] transition-all hover:from-teal-700 hover:to-teal-600"
                            data-testid="book-now-btn"
                        >
                            <Calendar :size="16" />
                            Prenota ora
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-gray-100 px-6 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            <MessageCircle :size="16" />
                            Contatta
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
