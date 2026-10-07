<script setup lang="ts">
import { Award, CheckCircle } from 'lucide-vue-next';
import type { CertificationData } from '../../../types/public';

defineProps<{
    certifications: CertificationData[];
}>();
</script>

<template>
    <section v-if="certifications.length" class="py-8">
        <h2 class="mb-4 text-xl font-bold text-gray-900 dark:text-white">
            Certificazioni
        </h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div
                v-for="cert in certifications"
                :key="cert.id"
                class="flex items-center gap-3 rounded-xl bg-[#eff4ff] p-4 dark:bg-gray-800/50"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-950"
                >
                    <Award
                        :size="20"
                        class="text-amber-600 dark:text-amber-400"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <p
                        class="truncate font-medium text-gray-900 dark:text-white"
                    >
                        {{ cert.name }}
                    </p>
                    <p
                        v-if="cert.issuer"
                        class="text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ cert.issuer }}
                    </p>
                </div>
                <CheckCircle
                    v-if="cert.verified_at"
                    :size="18"
                    class="shrink-0 text-teal-500"
                />
            </div>
        </div>
    </section>
</template>
