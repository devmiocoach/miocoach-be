<script setup lang="ts">
import { CalendarCheck, Dumbbell, Search } from 'lucide-vue-next';

const steps = [
    {
        id: 1,
        title: 'Cerca il Coach',
        description:
            'Filtra per specialità, budget e posizione per trovare il professionista più adatto ai tuoi obiettivi fitness.',
        icon: Search,
    },
    {
        id: 2,
        title: 'Prenota Online',
        description:
            'Consulta il calendario in tempo reale e prenota la tua sessione individuale o di gruppo in pochi istanti.',
        icon: CalendarCheck,
    },
    {
        id: 3,
        title: 'Allenati con Successo',
        description:
            'Raggiungi i tuoi traguardi con un piano personalizzato e il supporto costante del tuo esperto dedicato.',
        icon: Dumbbell,
    },
];
</script>

<template>
    <section class="bg-background dark:bg-background py-24">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mb-16">
                <span
                    class="text-primary mb-3 block text-sm font-semibold tracking-wider uppercase"
                >
                    Il tuo percorso
                </span>
                <h2 class="text-foreground text-3xl font-bold md:text-4xl">
                    Come Funziona MioCoach
                </h2>
            </div>

            <div class="grid grid-cols-1 gap-12 md:grid-cols-3 lg:gap-16">
                <div
                    v-for="(step, index) in steps"
                    :key="step.id"
                    class="relative flex flex-col"
                >
                    <!-- Connector line for desktop -->
                    <div
                        v-if="index !== steps.length - 1"
                        class="bg-border absolute top-8 left-[calc(100%-2rem)] z-0 hidden h-[2px] w-[calc(100%+4rem)] md:block"
                    ></div>

                    <div
                        class="bg-background border-border relative z-10 mb-6 flex h-16 w-16 items-center justify-center rounded-2xl border shadow-sm transition-transform duration-300 group-hover:scale-110"
                    >
                        <component
                            :is="step.icon"
                            class="text-primary h-7 w-7"
                        />
                    </div>

                    <h3 class="text-foreground mb-3 text-xl font-bold">
                        {{ step.id }}. {{ step.title }}
                    </h3>

                    <p class="text-muted-foreground leading-relaxed">
                        {{ step.description }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
