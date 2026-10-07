<script setup lang="ts">
import { CheckCircle } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import type { ReviewData } from '../../../types/public';
import RatingStars from '../RatingStars.vue';

const props = defineProps<{
    coachId: number;
}>();

const reviews = ref<ReviewData[]>([]);
const loading = ref(false);
const hasMore = ref(true);
const page = ref(1);

const fetchReviews = async () => {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    try {
        const response = await fetch(
            `/api/v1/coaches/${props.coachId}/reviews?page=${page.value}&limit=5`,
        );
        if (!response.ok) {
            hasMore.value = false;
            return;
        }
        const json = await response.json();
        reviews.value = [...reviews.value, ...(json.data ?? [])];
        hasMore.value = !!json.links?.next;
        page.value++;
    } finally {
        loading.value = false;
    }
};

const formatDate = (iso: string) => {
    return new Date(iso).toLocaleDateString('it-IT', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

onMounted(fetchReviews);
</script>

<template>
    <section class="py-8">
        <h2 class="mb-4 text-xl font-bold text-gray-900 dark:text-white">
            Recensioni
        </h2>

        <div v-if="reviews.length" class="space-y-4">
            <div
                v-for="review in reviews"
                :key="review.id"
                class="rounded-xl bg-[#eff4ff] p-4 dark:bg-gray-800/50"
            >
                <div class="mb-2 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <RatingStars :rating="review.rating" :size="14" />
                        <span
                            v-if="review.is_verified"
                            class="flex items-center gap-1 text-xs text-teal-600 dark:text-teal-400"
                        >
                            <CheckCircle :size="12" />
                            Verificata
                        </span>
                    </div>
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{
                        formatDate(review.created_at)
                    }}</span>
                </div>
                <p
                    v-if="review.title"
                    class="mb-1 text-sm font-medium text-gray-900 dark:text-white"
                >
                    {{ review.title }}
                </p>
                <p
                    v-if="review.body"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >
                    {{ review.body }}
                </p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    — {{ review.reviewer_name }}
                </p>
            </div>

            <button
                v-if="hasMore"
                type="button"
                class="w-full py-3 text-sm font-medium text-teal-600 hover:underline dark:text-teal-400"
                :disabled="loading"
                @click="fetchReviews"
            >
                {{ loading ? 'Caricamento...' : 'Carica altre recensioni' }}
            </button>
        </div>

        <p
            v-else-if="!loading"
            class="text-sm text-gray-500 dark:text-gray-400"
        >
            Nessuna recensione ancora.
        </p>
    </section>
</template>
