<script setup lang="ts">
import SiteLayout from '@/layouts/SiteLayout.vue';
import AvailabilityPreview from '../../components/public/profile/AvailabilityPreview.vue';
import BioSection from '../../components/public/profile/BioSection.vue';
import CertificationsSection from '../../components/public/profile/CertificationsSection.vue';
import CoachMap from '../../components/public/profile/CoachMap.vue';
import ContactWidget from '../../components/public/profile/ContactWidget.vue';
import HowIWorkSection from '../../components/public/profile/HowIWorkSection.vue';
import PricingSection from '../../components/public/profile/PricingSection.vue';
import ProfileHero from '../../components/public/profile/ProfileHero.vue';
import ReviewsSection from '../../components/public/profile/ReviewsSection.vue';
import SpecializationsSection from '../../components/public/profile/SpecializationsSection.vue';
import type { PublicCoachData } from '../../types/public';

const props = defineProps<{
    coach: PublicCoachData;
}>();

const showMap =
    props.coach.lat && props.coach.lng && props.coach.mode !== 'online';
</script>

<template>
    <SiteLayout
        :title="coach.seo.title"
        :description="coach.seo.description"
        :image="coach.seo.og_image ?? undefined"
    >
        <ProfileHero :coach="coach" />

        <div class="mx-auto max-w-6xl px-4 pb-24 sm:px-6 lg:px-8 lg:pb-12">
            <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_20rem] lg:gap-8">
                <div class="min-w-0 space-y-8">
                    <BioSection :coach="coach" />
                    <SpecializationsSection :coach="coach" />
                    <HowIWorkSection />
                    <CertificationsSection
                        :certifications="coach.certifications"
                    />
                    <PricingSection :coach="coach" />
                    <AvailabilityPreview :availability="coach.availability" />
                    <ReviewsSection :coach-id="coach.id" />
                    <CoachMap
                        v-if="showMap"
                        :lat="coach.lat!"
                        :lng="coach.lng!"
                        :display-name="coach.display_name"
                    />
                </div>

                <aside class="mt-8 lg:sticky lg:top-6 lg:mt-0 lg:self-start">
                    <ContactWidget :coach="coach" />
                </aside>
            </div>
        </div>
    </SiteLayout>
</template>
