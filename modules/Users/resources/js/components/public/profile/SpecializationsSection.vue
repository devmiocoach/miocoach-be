<script setup lang="ts">
import type { PublicCoachData } from '../../../types/public';

defineProps<{
    coach: PublicCoachData;
}>();
</script>

<template>
    <section v-if="coach.specializations?.length" class="py-8">
        <h2 class="mb-4 text-xl font-bold text-gray-900 dark:text-white">
            Specializzazioni
        </h2>
        <div class="flex flex-wrap gap-2">
            <span
                v-for="spec in coach.specializations"
                :key="spec"
                class="rounded-full bg-teal-50 px-4 py-2 text-sm font-medium text-teal-700 dark:bg-teal-950 dark:text-teal-300"
            >
                {{ spec }}
            </span>
        </div>
    </section>
</template>
