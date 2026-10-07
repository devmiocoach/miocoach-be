<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import OnboardingLayout from './OnboardingLayout.vue';
import AvailabilityStep from './steps/AvailabilityStep.vue';
import CertificazioniStep from './steps/CertificazioniStep.vue';
import GalleryStep from './steps/GalleryStep.vue';
import PolicyStep from './steps/PolicyStep.vue';
import PricingStep from './steps/PricingStep.vue';
import ProfileStep from './steps/ProfileStep.vue';
import ReviewStep from './steps/ReviewStep.vue';
import SedeStep from './steps/SedeStep.vue';
import SpecializationStep from './steps/SpecializationStep.vue';

interface Specialization {
    id: number;
    name: string;
    slug: string;
}

interface AvailabilitySlot {
    id?: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
    duration_minutes?: number;
}

interface CoachPackage {
    id?: number;
    name: string;
    sessions_count: number;
    price_cents: number;
    sort_order?: number;
}

interface Certification {
    id?: number;
    name: string;
    issuer: string;
    issued_at: string | null;
    file_url: string | null;
}

interface CoachData {
    display_name: string | null;
    tagline: string | null;
    bio: string | null;
    mode: string | null;
    city: string | null;
    lat: number | null;
    lng: number | null;
    languages: string[];
    specialization_ids: number[];
    session_duration_default: string | null;
    cancellation_window_hours: number | null;
    single_session_price_cents: number | null;
    monthly_subscription_cents: number | null;
    quarterly_subscription_cents: number | null;
    is_published: boolean;
    profile_photo_url: string | null;
    cover_photo_url: string | null;
    p_iva: string | null;
    has_insurance: boolean;
    insurance_company: string | null;
    insurance_expiry: string | null;
    insurance_file_url: string | null;
    service_radius_km: number | null;
    available_at_home: boolean;
    partner_gyms: string[] | null;
    gallery_photos: string[] | null;
    intro_video_url: string | null;
    no_show_fee_cents: number | null;
    late_tolerance_minutes: number | null;
    free_first_session: boolean;
    welcome_discount_enabled: boolean;
    welcome_discount_percent: number | null;
    custom_terms: string | null;
    contact_options: {
        free_consultation: boolean;
        direct_message: boolean;
        direct_booking: boolean;
    };
}

const props = defineProps<{
    initialStep?: number;
    coach: CoachData | null;
    completedSteps: number[];
    specializations: Specialization[];
    packages: CoachPackage[];
    availabilitySlots: AvailabilitySlot[];
    certifications: Certification[];
}>();

const TOTAL_STEPS = 9;
const currentStep = ref(props.initialStep ?? 1);
const saving = ref(false);
const uploadError = ref<string | null>(null);

watch(
    () => props.initialStep,
    (val) => {
        if (val !== undefined) currentStep.value = val;
    },
);

const stepComponents = [
    ProfileStep,
    SpecializationStep,
    CertificazioniStep,
    AvailabilityStep,
    SedeStep,
    PricingStep,
    GalleryStep,
    PolicyStep,
    ReviewStep,
];

const stepMeta = computed(() => {
    const metas = [
        {
            title: 'Profilo',
            eyebrow: 'Step 1 · Identità',
            headline: 'Raccontaci',
            highlight: 'chi sei',
            subtitle:
                'I tuoi futuri allievi vogliono conoscerti. Un profilo completo e professionale aumenta le probabilità di prenotazione del 65%.',
            tip: 'La prima impressione è quella che conta. Carica una foto luminosa e professionale, sorriso aperto e sfondo neutro.',
        },
        {
            title: 'Specialità',
            eyebrow: 'Step 2 · Competenze',
            headline: 'Quali sono le tue',
            highlight: 'eccellenze',
            subtitle:
                'Seleziona le aree in cui offri competenze certificate. Min 1, max 10.',
            tip: 'I coach con 3-5 specialità ben definite ricevono in media il 2.3× di richieste rispetto a chi sceglie troppe aree.',
        },
        {
            title: 'Certificazioni',
            eyebrow: 'Step 3 · Trust',
            headline: 'Diventa coach',
            highlight: 'verificato',
            subtitle:
                'I profili verificati ricevono 3.5× più prenotazioni. Carica diplomi, P.IVA e assicurazione.',
            tip: 'Il nostro team verifica ogni documento entro 24h. Una volta approvato, il badge "Verificato" sarà visibile in vetrina.',
        },
        {
            title: 'Disponibilità',
            eyebrow: 'Step 4 · Agenda',
            headline: 'Quando sei',
            highlight: 'disponibile',
            subtitle:
                'Configura i tuoi orari settimanali. I clienti potranno prenotare solo nelle fasce che hai attivato.',
            tip: 'Più fasce disponibili nei weekend = +38% di prenotazioni. Considera di aprire almeno sabato mattina.',
        },
        {
            title: 'Sede',
            eyebrow: 'Step 5 · Geografia',
            headline: 'Da',
            highlight: 'dove',
            subtitle:
                'I clienti cercano coach vicino a loro. Indica la tua zona principale e il raggio coperto.',
            tip: 'Coprire più quartieri/comuni aumenta del 60% le richieste.',
        },
        {
            title: 'Tariffe',
            eyebrow: 'Step 6 · Pricing',
            headline: 'Quanto vale il tuo',
            highlight: 'lavoro',
            subtitle:
                'Imposta la tua tariffa base e crea pacchetti. I pacchetti con sconto 10-15% aumentano la conversione del 40%.',
            tip: 'I clienti preferiscono pacchetti da 5 o 10 sessioni. Offri uno sconto del 10% sul pacchetto da 5 e del 15% su quello da 10.',
        },
        {
            title: 'Gallery',
            eyebrow: 'Step 7 · Visual',
            headline: 'Mostra il tuo',
            highlight: 'lavoro',
            subtitle:
                '3-6 foto in azione e un video intro di 30s. I profili con video convertono 3× di più.',
            tip: 'Luce naturale, mostra te in azione, evita filtri pesanti.',
        },
        {
            title: 'Policy e contatti',
            eyebrow: 'Step 8 · Regole',
            headline: 'Le tue',
            highlight: 'regole',
            subtitle:
                'Definisci policy chiare e scegli come i clienti possono contattarti.',
            tip: 'Policy chiare riducono dispute e no-show. Offrire la prima sessione gratuita aumenta del 45% le prime prenotazioni.',
        },
        {
            title: 'Revisione',
            eyebrow: 'Step 9 · Conferma',
            headline: 'Revisione',
            highlight: 'Finale',
            subtitle:
                'Controlla i dettagli del tuo profilo prima di pubblicarlo.',
            tip: 'Una volta pubblicato, il tuo profilo sarà visibile sulla vetrina pubblica MioCoach.',
        },
    ];
    return metas[currentStep.value - 1];
});

const stepRef = ref<{
    getData: () => Record<string, unknown>;
    getFiles?: () => Array<{ key: string; file: File }>;
    totalSelected?: number;
} | null>(null);

const stepRoutes: Record<number, string> = {
    1: route('coach.onboarding.step1'),
    2: route('coach.onboarding.step2'),
    3: route('coach.onboarding.step3'),
    4: route('coach.onboarding.step4'),
    5: route('coach.onboarding.step5'),
    6: route('coach.onboarding.step6'),
    7: route('coach.onboarding.step7'),
    8: route('coach.onboarding.step8'),
};

function getStepData(): Record<string, unknown> | null {
    return stepRef.value?.getData() ?? null;
}

async function uploadStepFiles(files: Array<{ key: string; file: File }>) {
    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';
    let uploaded = false;
    uploadError.value = null;

    for (const { key, file } of files) {
        const formData = new FormData();
        let endpoint: string;

        // Each endpoint expects its own field name — see OnboardingController.
        if (key.startsWith('cert_file_')) {
            formData.append('file', file);
            formData.append(
                'certification_index',
                key.replace('cert_file_', ''),
            );
            endpoint = route('coach.onboarding.upload-certification');
        } else if (key === 'insurance_file') {
            formData.append('file', file);
            endpoint = route('coach.onboarding.upload-insurance');
        } else if (key.startsWith('gallery_photo_')) {
            formData.append('photo', file);
            endpoint = route('coach.onboarding.upload-gallery-photo');
        } else if (key === 'intro_video') {
            formData.append('video', file);
            endpoint = route('coach.onboarding.upload-video');
        } else {
            continue;
        }

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-TOKEN': csrfToken },
            });
            // A CSRF/auth failure answers with a redirect to an HTML page, which
            // `fetch` follows and reports as ok — treat it as the failure it is.
            if (!response.ok || response.redirected) {
                throw new Error(String(response.status));
            }
            uploaded = true;
        } catch {
            uploadError.value = `Caricamento di "${file.name}" non riuscito. Riprova.`;
        }
    }

    // Uploads run after the step redirect has landed, so the page props still
    // hold the pre-upload state — reload to pick up the stored URLs and the
    // freshly completed step.
    if (uploaded) {
        router.reload({ only: ['coach', 'completedSteps', 'certifications'] });
    }
}

function handleContinue() {
    if (currentStep.value === TOTAL_STEPS) {
        handlePublish();
        return;
    }
    const data = getStepData();
    if (!data) return;

    const files = stepRef.value?.getFiles?.() ?? [];

    saving.value = true;
    // Advance is driven by the server redirect to ?step=N+1 (syncs `currentStep`
    // via the `initialStep` prop watcher). Do NOT increment here too, or steps double-jump.
    router.put(stepRoutes[currentStep.value], data as any, {
        preserveScroll: true,
        onSuccess: async () => {
            if (files.length > 0) {
                await uploadStepFiles(files);
            }
            saving.value = false;
        },
        onError: () => {
            saving.value = false;
            nextTick(() => scrollFirstErrorIntoView());
        },
    });
}

function handleSaveDraft() {
    if (currentStep.value === TOTAL_STEPS) return;
    const data = getStepData();
    if (!data) return;

    const files = stepRef.value?.getFiles?.() ?? [];

    saving.value = true;
    router.put(stepRoutes[currentStep.value], data as any, {
        preserveScroll: true,
        onSuccess: async () => {
            if (files.length > 0) {
                await uploadStepFiles(files);
            }
            saving.value = false;
        },
        onError: () => {
            saving.value = false;
            nextTick(() => scrollFirstErrorIntoView());
        },
    });
}

function handlePublish() {
    const data = getStepData();
    // `getData()` flips ReviewStep's `triedToPublish`, but the warning sits far
    // below the sticky footer — without scrolling it into view the click on
    // "Pubblica il profilo" looks like it did nothing at all.
    if (!data || data.accepted_terms !== '1') {
        nextTick(() => scrollErrorIntoView('error-terms'));
        return;
    }
    saving.value = true;
    router.post(route('coach.onboarding.publish'), data as any, {
        onError: () => {
            nextTick(() => scrollErrorIntoView('error-completeness'));
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}

function scrollErrorIntoView(testId: string) {
    document
        .querySelector(`[data-testid="${testId}"]`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function scrollFirstErrorIntoView() {
    document
        .querySelector('[data-testid^="error-"]')
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function handleBack() {
    if (currentStep.value > 1) {
        router.visit(
            route('coach.onboarding.index', { step: currentStep.value - 1 }),
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <OnboardingLayout
        :current-step="currentStep"
        :total-steps="TOTAL_STEPS"
        :completed-steps="completedSteps"
        :title="stepMeta.title"
        :saving="saving"
        :can-continue="true"
        @back="handleBack"
        @continue="handleContinue"
        @save-draft="handleSaveDraft"
    >
        <template #aside>
            <div>
                <div
                    class="mb-4 inline-flex items-center gap-2 rounded-full border border-teal-200 bg-teal-50 px-3 py-1 text-xs font-semibold tracking-wider text-teal-700 uppercase dark:border-teal-800 dark:bg-teal-900/30 dark:text-teal-300"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-gradient-to-r from-teal-600 to-[#ED7A22]"
                    />
                    {{ stepMeta.eyebrow }}
                </div>
                <h1
                    class="text-[2.4rem] leading-[1.15] font-bold tracking-tight text-gray-900 dark:text-white"
                >
                    {{ stepMeta.headline }}
                    <span class="whitespace-nowrap"
                        ><span
                            class="inline-block bg-clip-text pr-1 pb-0.5 text-transparent italic"
                            style="
                                background-image: linear-gradient(
                                    90deg,
                                    #0d9488,
                                    #ed7a22
                                );
                            "
                            >{{ stepMeta.highlight }}</span
                        >.</span
                    >
                </h1>
                <p
                    class="mt-5 text-base leading-relaxed text-gray-500 dark:text-gray-400"
                >
                    {{ stepMeta.subtitle }}
                </p>
            </div>

            <!-- Step 2: specialità counter card -->
            <div
                v-if="currentStep === 2"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <div
                    class="mb-1 text-xs font-semibold tracking-wider uppercase"
                    style="color: #ed7a22"
                >
                    {{ stepRef?.totalSelected ?? 0 }} / 10 selezionate
                </div>
                <div
                    class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
                >
                    <div
                        class="h-full rounded-full transition-all duration-300"
                        :style="{
                            width: `${((stepRef?.totalSelected ?? 0) / 10) * 100}%`,
                            backgroundImage:
                                'linear-gradient(90deg, #0d9488, #ED7A22)',
                        }"
                    />
                </div>
                <p
                    class="mt-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400"
                >
                    {{ stepMeta.tip }}
                </p>
            </div>

            <!-- Tip card (altri step) -->
            <div
                v-else
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-sm leading-relaxed text-gray-600 dark:text-gray-400"
                >
                    {{ stepMeta.tip }}
                </p>
            </div>
        </template>

        <template #aside-mobile>
            <div
                class="mb-2 text-xs font-semibold tracking-wider text-teal-600 uppercase dark:text-teal-400"
            >
                {{ stepMeta.eyebrow }}
            </div>
            <h1
                class="text-3xl leading-[1.2] font-bold tracking-tight text-gray-900 dark:text-white"
            >
                {{ stepMeta.headline }}
                <span
                    class="inline-block bg-clip-text pr-1 pb-0.5 text-transparent italic"
                    style="
                        background-image: linear-gradient(
                            90deg,
                            #0d9488,
                            #ed7a22
                        );
                    "
                    >{{ stepMeta.highlight }}</span
                >.
            </h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ stepMeta.subtitle }}
            </p>
        </template>

        <p
            v-if="uploadError"
            class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600 dark:border-red-900 dark:bg-red-950/40 dark:text-red-400"
            data-testid="error-upload"
        >
            {{ uploadError }}
        </p>

        <Transition name="step-slide" mode="out-in">
            <component
                :is="stepComponents[currentStep - 1]"
                :key="currentStep"
                :ref="
                    (el) => {
                        stepRef = el;
                    }
                "
                :coach="coach"
                :specializations="specializations"
                :packages="packages"
                :availability-slots="availabilitySlots"
                :certifications="certifications"
                :completed-steps="completedSteps"
            />
        </Transition>
    </OnboardingLayout>
</template>

<style scoped>
.step-slide-enter-active,
.step-slide-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}
.step-slide-enter-from {
    opacity: 0;
    transform: translateX(24px);
}
.step-slide-leave-to {
    opacity: 0;
    transform: translateX(-24px);
}
</style>
