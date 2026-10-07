<script setup lang="ts">
import BecomeCoachCta from '@/components/landing/BecomeCoachCta.vue';
import FaqSection from '@/components/landing/FaqSection.vue';
import HeroSection from '@/components/landing/HeroSection.vue';
import HowItWorksSection from '@/components/landing/HowItWorksSection.vue';
import { ModuleModal } from '@/components/ui/miocoach';
import SiteLayout from '@/layouts/SiteLayout.vue';
import { ref } from 'vue';

const selectedMod = ref(null);
</script>

<template>
    <SiteLayout
        :title="$t('MioCoach | Trova il tuo Personal Trainer ideale')"
        :description="
            $t(
                'MioCoach ti connette con i migliori Personal Trainer certificati in Italia. Prenota la tua prima sessione in pochi clic.',
            )
        "
    >
        <main class="flex w-full flex-1 flex-col">
            <HeroSection />
            <HowItWorksSection />
            <BecomeCoachCta />
            <FaqSection />
        </main>
    </SiteLayout>

    <ModuleModal :selected-mod="selectedMod" @close="selectedMod = null" />
</template>
