<script setup lang="ts">
import { ClipboardList, Target, TrendingUp } from 'lucide-vue-next';

const steps = [
    {
        icon: ClipboardList,
        title: 'Analisi',
        description:
            'Valutiamo insieme i tuoi obiettivi, il tuo livello attuale e le tue preferenze di allenamento.',
    },
    {
        icon: Target,
        title: 'Piano Personalizzato',
        description:
            'Creo un programma su misura per te, adattato ai tuoi tempi e alla tua disponibilità.',
    },
    {
        icon: TrendingUp,
        title: 'Risultati',
        description:
            'Monitoriamo i progressi e adattiamo il piano per raggiungere i tuoi obiettivi.',
    },
];
</script>

<template>
    <section class="py-8">
        <h2 class="mb-6 text-xl font-bold text-gray-900 dark:text-white">
            Come lavoro
        </h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
                v-for="(step, i) in steps"
                :key="i"
                class="relative rounded-xl bg-[#eff4ff] p-5 dark:bg-gray-800/50"
            >
                <div
                    class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-teal-500/10"
                >
                    <component
                        :is="step.icon"
                        :size="20"
                        class="text-teal-600 dark:text-teal-400"
                    />
                </div>
                <h3 class="mb-1 font-semibold text-gray-900 dark:text-white">
                    {{ step.title }}
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ step.description }}
                </p>
            </div>
        </div>
    </section>
</template>
