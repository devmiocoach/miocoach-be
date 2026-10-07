<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { CheckCircle, Pencil, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CoachData {
    display_name: string | null;
    tagline: string | null;
    bio: string | null;
    mode: string | null;
    city: string | null;
    languages: string[];
    specialization_ids: number[];
    session_duration_default: string | null;
    cancellation_window_hours: number | null;
    single_session_price_cents: number | null;
    monthly_subscription_cents: number | null;
    quarterly_subscription_cents: number | null;
    is_published: boolean;
    profile_photo_url: string | null;
    p_iva: string | null;
    has_insurance: boolean;
    service_radius_km: number | null;
    available_at_home: boolean;
    partner_gyms: string[] | null;
    gallery_photos: string[] | null;
    intro_video_url: string | null;
    no_show_fee_cents: number | null;
    free_first_session: boolean;
    welcome_discount_enabled: boolean;
    welcome_discount_percent: number | null;
    contact_options?: {
        free_consultation: boolean;
        direct_message: boolean;
        direct_booking: boolean;
    };
}

interface Specialization {
    id: number;
    name: string;
    slug: string;
}

interface CoachPackage {
    id?: number;
    name: string;
    sessions_count: number;
    price_cents: number;
}

interface Certification {
    id?: number;
    name: string;
    issuer: string;
}

const props = defineProps<{
    coach: CoachData | null;
    specializations: Specialization[];
    packages: CoachPackage[];
    certifications: Certification[];
    completedSteps: number[];
}>();

const acceptedTerms = ref(false);
const triedToPublish = ref(false);

const page = usePage();
const serverError = computed(
    () => (page.props.errors as Record<string, string>)?.completeness ?? null,
);

const MODE_LABELS: Record<string, string> = {
    online: 'Online',
    in_person: 'In presenza',
    hybrid: 'Ibrido',
};

// `ok` is driven by the server-computed `completedSteps` (same logic as the
// publish gate), so a green chip always means that step is publishable.
const checklist = computed(() =>
    [
        { key: 'profile', label: 'Profilo', step: 1 },
        { key: 'specializations', label: 'Specialità', step: 2 },
        {
            key: 'certifications',
            label: 'Certificazioni',
            step: 3,
            optional: true,
        },
        { key: 'availability', label: 'Disponibilità', step: 4 },
        { key: 'sede', label: 'Sede', step: 5, optional: true },
        { key: 'pricing', label: 'Tariffe', step: 6 },
        { key: 'gallery', label: 'Gallery', step: 7, optional: true },
        { key: 'policy', label: 'Policy', step: 8, optional: true },
    ].map((c) => ({ ...c, ok: props.completedSteps.includes(c.step) })),
);

const requiredComplete = computed(() =>
    checklist.value.filter((c) => !c.optional).every((c) => c.ok),
);

const coachSpecializations = computed(() =>
    props.specializations.filter((s) =>
        props.coach?.specialization_ids?.includes(s.id),
    ),
);

const activeContactChannels = computed(() => {
    const options = props.coach?.contact_options;
    if (!options) return '—';

    const labels: string[] = [];
    if (options.free_consultation) labels.push('Consulenza gratuita');
    if (options.direct_message) labels.push('Contatto diretto');
    if (options.direct_booking) labels.push('Prenotazione diretta');

    return labels.length ? labels.join(', ') : '—';
});

function formatPrice(cents: number | null | undefined): string {
    if (!cents) return '—';
    return `€${(cents / 100).toFixed(0)}`;
}

function goToStep(step: number) {
    router.visit(route('coach.onboarding.index', { step }), {
        preserveScroll: true,
    });
}

function getData() {
    triedToPublish.value = true;
    return { accepted_terms: acceptedTerms.value ? '1' : '0' };
}

defineExpose({ getData });
</script>

<template>
    <div class="space-y-6">
        <!-- Progress checklist -->
        <div
            class="rounded-2xl border border-teal-200 bg-teal-50/30 p-3 dark:border-teal-800 dark:bg-teal-900/10"
        >
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="item in checklist"
                    :key="item.key"
                    type="button"
                    :data-testid="`checklist-${item.key}`"
                    class="flex cursor-pointer items-center gap-1.5 rounded-full bg-white px-3 py-1 text-sm font-medium text-teal-700 transition hover:ring-2 hover:ring-teal-500/30 dark:bg-gray-900 dark:text-teal-300"
                    @click="goToStep(item.step)"
                >
                    <CheckCircle v-if="item.ok" class="h-4 w-4 text-teal-500" />
                    <XCircle
                        v-else-if="!item.optional"
                        class="h-4 w-4 text-amber-500"
                    />
                    <span
                        v-else
                        class="h-4 w-4 rounded-full border-2 border-gray-300 dark:border-gray-600"
                    />
                    <span>{{ item.label }}</span>
                </button>
            </div>
        </div>

        <!-- Summary cards grid -->
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <!-- Profile card -->
            <div
                class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                data-testid="review-profile"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-900/40 dark:text-teal-400"
                        >
                            <img
                                v-if="coach?.profile_photo_url"
                                :src="coach.profile_photo_url"
                                alt=""
                                class="h-full w-full rounded-lg object-cover"
                            />
                            <span v-else class="text-xs">P</span>
                        </div>
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >Profilo</span
                        >
                    </div>
                    <button
                        class="cursor-pointer text-gray-400 hover:text-teal-500"
                        data-testid="edit-step-1"
                        @click="goToStep(1)"
                    >
                        <Pencil class="h-4 w-4" />
                    </button>
                </div>
                <div
                    class="space-y-0 divide-y divide-gray-100 text-sm dark:divide-gray-800"
                >
                    <div
                        class="flex items-center justify-between py-2 first:pt-0"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Nome</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{ coach?.display_name || '—' }}</span
                        >
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-gray-500 dark:text-gray-400"
                            >Tagline</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{ coach?.tagline || '—' }}</span
                        >
                    </div>
                    <div
                        v-if="coach?.bio"
                        class="flex items-center justify-between py-2"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Bio</span
                        >
                        <span
                            class="max-w-[60%] truncate font-semibold text-gray-900 dark:text-white"
                            >{{ coach.bio }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Specializations card -->
            <div
                class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                data-testid="review-specializations"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-900/40 dark:text-teal-400"
                        >
                            <span class="text-xs">S</span>
                        </div>
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >Specialità</span
                        >
                        <span
                            v-if="coachSpecializations.length"
                            class="rounded-full bg-teal-100 px-2 py-0.5 text-xs font-medium text-teal-700 dark:bg-teal-900/40 dark:text-teal-300"
                            >{{ coachSpecializations.length }}</span
                        >
                    </div>
                    <button
                        class="cursor-pointer text-gray-400 hover:text-teal-500"
                        data-testid="edit-step-2"
                        @click="goToStep(2)"
                    >
                        <Pencil class="h-4 w-4" />
                    </button>
                </div>
                <div
                    class="space-y-0 divide-y divide-gray-100 text-sm dark:divide-gray-800"
                >
                    <div
                        class="flex items-center justify-between py-2 first:pt-0"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Modalità</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{
                                coach?.mode ? MODE_LABELS[coach.mode] : '—'
                            }}</span
                        >
                    </div>
                    <div class="py-2">
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="spec in coachSpecializations"
                                :key="spec.id"
                                class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                            >
                                {{ spec.name }}
                            </span>
                            <span
                                v-if="!coachSpecializations.length"
                                class="text-sm text-gray-400 italic"
                                >—</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Certifications card -->
            <div
                class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                data-testid="review-certifications"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-900/40 dark:text-teal-400"
                        >
                            <span class="text-xs">C</span>
                        </div>
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >Certificazioni</span
                        >
                    </div>
                    <button
                        class="cursor-pointer text-gray-400 hover:text-teal-500"
                        data-testid="edit-step-3"
                        @click="goToStep(3)"
                    >
                        <Pencil class="h-4 w-4" />
                    </button>
                </div>
                <div
                    class="space-y-0 divide-y divide-gray-100 text-sm dark:divide-gray-800"
                >
                    <div
                        v-for="cert in certifications"
                        :key="cert.name"
                        class="flex items-center justify-between py-2 first:pt-0"
                    >
                        <span class="text-gray-500 dark:text-gray-400">{{
                            cert.name
                        }}</span>
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{ cert.issuer || '—' }}</span
                        >
                    </div>
                    <div
                        v-if="!certifications?.length"
                        class="flex items-center justify-between py-2 first:pt-0"
                    >
                        <span class="text-gray-400 italic"
                            >Nessuna certificazione</span
                        >
                    </div>
                    <div
                        v-if="coach?.p_iva"
                        class="flex items-center justify-between py-2"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >P.IVA</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{ coach.p_iva }}</span
                        >
                    </div>
                    <div
                        v-if="coach?.has_insurance"
                        class="flex items-center justify-between py-2"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Assicurazione</span
                        >
                        <span class="font-bold text-teal-600 dark:text-teal-400"
                            >Attiva</span
                        >
                    </div>
                </div>
            </div>

            <!-- Availability card -->
            <div
                class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                data-testid="review-availability"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-900/40 dark:text-teal-400"
                        >
                            <span class="text-xs">D</span>
                        </div>
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >Disponibilità</span
                        >
                    </div>
                    <button
                        class="cursor-pointer text-gray-400 hover:text-teal-500"
                        data-testid="edit-step-4"
                        @click="goToStep(4)"
                    >
                        <Pencil class="h-4 w-4" />
                    </button>
                </div>
                <div
                    class="space-y-0 divide-y divide-gray-100 text-sm dark:divide-gray-800"
                >
                    <div
                        class="flex items-center justify-between py-2 first:pt-0"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Durata sessione</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{
                                coach?.session_duration_default
                                    ? `${coach.session_duration_default} min`
                                    : '—'
                            }}</span
                        >
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-gray-500 dark:text-gray-400"
                            >Cancellazione entro</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{
                                coach?.cancellation_window_hours
                                    ? `${coach.cancellation_window_hours}h`
                                    : '—'
                            }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Sede card -->
            <div
                class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                data-testid="review-sede"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-900/40 dark:text-teal-400"
                        >
                            <span class="text-xs">L</span>
                        </div>
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >Sede</span
                        >
                    </div>
                    <button
                        class="cursor-pointer text-gray-400 hover:text-teal-500"
                        data-testid="edit-step-5"
                        @click="goToStep(5)"
                    >
                        <Pencil class="h-4 w-4" />
                    </button>
                </div>
                <div
                    class="space-y-0 divide-y divide-gray-100 text-sm dark:divide-gray-800"
                >
                    <div
                        v-if="coach?.city"
                        class="flex items-center justify-between py-2 first:pt-0"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Città</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{ coach.city }}</span
                        >
                    </div>
                    <div
                        v-if="coach?.service_radius_km"
                        class="flex items-center justify-between py-2 first:pt-0"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Raggio</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{ coach.service_radius_km }} km</span
                        >
                    </div>
                    <div
                        v-if="coach?.available_at_home"
                        class="flex items-center justify-between py-2"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >A domicilio</span
                        >
                        <span class="font-bold text-teal-600 dark:text-teal-400"
                            >Si</span
                        >
                    </div>
                    <div
                        v-if="coach?.partner_gyms?.length"
                        class="flex items-center justify-between py-2"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Palestre</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{ coach.partner_gyms.length }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Pricing card -->
            <div
                class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                data-testid="review-pricing"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-900/40 dark:text-teal-400"
                        >
                            <span class="text-xs">T</span>
                        </div>
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >Tariffe</span
                        >
                    </div>
                    <button
                        class="cursor-pointer text-gray-400 hover:text-teal-500"
                        data-testid="edit-step-6"
                        @click="goToStep(6)"
                    >
                        <Pencil class="h-4 w-4" />
                    </button>
                </div>
                <div
                    class="space-y-0 divide-y divide-gray-100 text-sm dark:divide-gray-800"
                >
                    <div
                        class="flex items-center justify-between py-2 first:pt-0"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Sessione singola</span
                        >
                        <span
                            class="font-bold text-teal-600 dark:text-teal-400"
                            >{{
                                formatPrice(coach?.single_session_price_cents)
                            }}</span
                        >
                    </div>
                    <div
                        v-for="pkg in packages"
                        :key="pkg.name"
                        class="flex items-center justify-between py-2"
                    >
                        <span class="text-gray-500 dark:text-gray-400">{{
                            pkg.name
                        }}</span>
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{ formatPrice(pkg.price_cents) }}</span
                        >
                    </div>
                    <div
                        v-if="coach?.monthly_subscription_cents"
                        class="flex items-center justify-between py-2"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Mensile</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{
                                formatPrice(coach.monthly_subscription_cents)
                            }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Gallery card -->
            <div
                class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                data-testid="review-gallery"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-900/40 dark:text-teal-400"
                        >
                            <span class="text-xs">G</span>
                        </div>
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >Gallery</span
                        >
                    </div>
                    <button
                        class="cursor-pointer text-gray-400 hover:text-teal-500"
                        data-testid="edit-step-7"
                        @click="goToStep(7)"
                    >
                        <Pencil class="h-4 w-4" />
                    </button>
                </div>
                <div
                    class="space-y-0 divide-y divide-gray-100 text-sm dark:divide-gray-800"
                >
                    <div
                        class="flex items-center justify-between py-2 first:pt-0"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Foto</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{ coach?.gallery_photos?.length ?? 0 }}/6</span
                        >
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-gray-500 dark:text-gray-400"
                            >Video</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{
                                coach?.intro_video_url ? 'Caricato' : 'Nessuno'
                            }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Policy card -->
            <div
                class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                data-testid="review-policy"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-900/40 dark:text-teal-400"
                        >
                            <span class="text-xs">R</span>
                        </div>
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >Policy</span
                        >
                    </div>
                    <button
                        class="cursor-pointer text-gray-400 hover:text-teal-500"
                        data-testid="edit-step-8"
                        @click="goToStep(8)"
                    >
                        <Pencil class="h-4 w-4" />
                    </button>
                </div>
                <div
                    class="space-y-0 divide-y divide-gray-100 text-sm dark:divide-gray-800"
                >
                    <div
                        v-if="coach?.no_show_fee_cents"
                        class="flex items-center justify-between py-2 first:pt-0"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >No-show</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{ formatPrice(coach.no_show_fee_cents) }}</span
                        >
                    </div>
                    <div
                        v-if="coach?.free_first_session"
                        class="flex items-center justify-between py-2 first:pt-0"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Prima sessione</span
                        >
                        <span class="font-bold text-teal-600 dark:text-teal-400"
                            >Gratuita</span
                        >
                    </div>
                    <div
                        v-if="coach?.welcome_discount_enabled"
                        class="flex items-center justify-between py-2 first:pt-0"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Sconto benvenuto</span
                        >
                        <span class="font-bold text-teal-600 dark:text-teal-400"
                            >{{ coach.welcome_discount_percent }}%</span
                        >
                    </div>
                    <div
                        class="flex items-center justify-between py-2"
                        data-testid="review-contacts"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Contatti</span
                        >
                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                            >{{ activeContactChannels }}</span
                        >
                    </div>
                    <div
                        v-if="
                            !coach?.no_show_fee_cents &&
                            !coach?.free_first_session &&
                            !coach?.welcome_discount_enabled
                        "
                        class="py-2 first:pt-0"
                    >
                        <span class="text-gray-400 italic"
                            >Nessuna policy configurata</span
                        >
                    </div>
                </div>
            </div>
        </div>

        <!-- Terms acceptance -->
        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <label class="flex cursor-pointer items-start gap-3">
                <input
                    v-model="acceptedTerms"
                    type="checkbox"
                    data-testid="accept-terms-checkbox"
                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500"
                />
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    Dichiaro che tutte le informazioni fornite sono accurate e
                    accetto i
                    <a
                        href="#"
                        class="font-semibold text-teal-600 hover:underline dark:text-teal-400"
                        >Termini e Condizioni</a
                    >
                    e la
                    <a
                        href="#"
                        class="font-semibold text-teal-600 hover:underline dark:text-teal-400"
                        >Privacy Policy</a
                    >
                    di MioCoach.
                </span>
            </label>

            <p
                v-if="triedToPublish && !acceptedTerms"
                class="mt-3 text-sm font-medium text-red-600 dark:text-red-400"
                data-testid="error-terms"
            >
                Devi accettare i Termini e Condizioni per poter pubblicare il
                profilo.
            </p>

            <p
                v-if="serverError"
                class="mt-3 text-sm font-medium text-red-600 dark:text-red-400"
                data-testid="error-completeness"
            >
                {{ serverError }}
            </p>

            <div
                v-if="!requiredComplete"
                class="mt-3 text-sm text-amber-600 dark:text-amber-400"
            >
                Completa tutte le sezioni obbligatorie prima di poter
                pubblicare.
            </div>
        </div>
    </div>
</template>
