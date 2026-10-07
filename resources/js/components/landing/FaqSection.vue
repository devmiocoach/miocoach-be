<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';

interface FaqItem {
    question: string;
    answer: string;
}

const faqs: FaqItem[] = [
    {
        question: 'Come scelgo il Coach giusto per me?',
        answer: 'Puoi utilizzare i nostri filtri di ricerca per selezionare il coach in base a specializzazione, budget e posizione geografica. Leggi le recensioni degli altri utenti e prenota una sessione conoscitiva.',
    },
    {
        question: 'Quanto costa una sessione con un Personal Trainer?',
        answer: 'I prezzi variano in base allesperienza del coach, alla tipologia di allenamento (online o in presenza) e al pacchetto scelto. Puoi filtrare i risultati di ricerca in base al tuo budget ideale.',
    },
    {
        question: 'Posso allenarmi anche online o solo di persona?',
        answer: 'MioCoach offre entrambe le opzioni. Molti dei nostri professionisti forniscono programmi di allenamento da remoto tramite videochiamata e schede personalizzate.',
    },
    {
        question: 'Come funziona la politica di cancellazione?',
        answer: 'Ogni coach ha la propria politica di cancellazione, generalmente puoi annullare o riprogrammare una sessione senza penali con un preavviso di 24-48 ore. Le specifiche sono indicate nel profilo di ogni professionista.',
    },
];

const activeIndex = ref<number | null>(null);

const toggleAccordion = (index: number) => {
    activeIndex.value = activeIndex.value === index ? null : index;
};
</script>

<template>
    <section class="bg-background dark:bg-background py-24">
        <div class="mx-auto max-w-3xl px-6">
            <div class="mb-12 text-center">
                <span
                    class="text-primary mb-3 block text-sm font-semibold tracking-wider uppercase"
                >
                    Hai dubbi?
                </span>
                <h2 class="text-foreground text-3xl font-bold md:text-4xl">
                    Domande Frequenti
                </h2>
            </div>

            <div class="space-y-4">
                <div
                    v-for="(faq, index) in faqs"
                    :key="index"
                    class="border-border/50 bg-muted/20 dark:bg-card overflow-hidden rounded-2xl border transition-colors duration-200"
                    :class="{
                        'bg-background border-border shadow-sm':
                            activeIndex === index,
                    }"
                >
                    <button
                        @click="toggleAccordion(index)"
                        class="focus-visible:ring-primary flex w-full items-center justify-between rounded-2xl px-6 py-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                    >
                        <span
                            class="text-foreground pr-4 text-left text-[15px] font-semibold sm:text-base"
                        >
                            {{ faq.question }}
                        </span>
                        <ChevronDown
                            class="text-muted-foreground h-5 w-5 shrink-0 transition-transform duration-300"
                            :class="{
                                'text-primary rotate-180':
                                    activeIndex === index,
                            }"
                        />
                    </button>

                    <div
                        class="overflow-hidden transition-all duration-300 ease-in-out"
                        :style="{
                            maxHeight: activeIndex === index ? '500px' : '0px',
                            opacity: activeIndex === index ? 1 : 0,
                        }"
                    >
                        <div
                            class="text-muted-foreground px-6 pt-0 pb-6 text-[15px] leading-relaxed sm:text-base"
                        >
                            {{ faq.answer }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
