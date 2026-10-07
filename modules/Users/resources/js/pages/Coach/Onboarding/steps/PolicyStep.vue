<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    CalendarCheck,
    Clock,
    Gift,
    MessageCircle,
    Scale,
    ShieldAlert,
    Tag,
    Ticket,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface ContactOptions {
    free_consultation: boolean;
    direct_message: boolean;
    direct_booking: boolean;
}

interface CoachData {
    no_show_fee_cents: number | null;
    late_tolerance_minutes: number | null;
    free_first_session: boolean;
    welcome_discount_enabled: boolean;
    welcome_discount_percent: number | null;
    custom_terms: string | null;
    contact_options?: ContactOptions;
}

const props = defineProps<{
    coach: CoachData | null;
}>();

const page = usePage();
const serverErrors = computed(
    () => (page.props.errors as Record<string, string>) ?? {},
);

const noShowFee = ref(
    props.coach?.no_show_fee_cents ? props.coach.no_show_fee_cents / 100 : 50,
);
const lateTolerance = ref(String(props.coach?.late_tolerance_minutes ?? 15));
const freeFirst = ref(props.coach?.free_first_session ?? true);
const welcomeDiscount = ref(props.coach?.welcome_discount_enabled ?? false);
const discountPercent = ref(props.coach?.welcome_discount_percent ?? 15);
const customTerms = ref(props.coach?.custom_terms ?? '');

const contact = reactive<ContactOptions>({
    free_consultation: props.coach?.contact_options?.free_consultation ?? false,
    direct_message: props.coach?.contact_options?.direct_message ?? false,
    direct_booking: props.coach?.contact_options?.direct_booking ?? true,
});

const CONTACT_CHANNELS = [
    {
        key: 'free_consultation',
        label: 'Prima consulenza gratuita',
        description: 'Il cliente ti chiede una call conoscitiva gratuita.',
        icon: Ticket,
        color: 'bg-sky-100 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400',
    },
    {
        key: 'direct_message',
        label: 'Contatto diretto',
        description: 'Il cliente ti invia un messaggio dalla tua vetrina.',
        icon: MessageCircle,
        color: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
    },
    {
        key: 'direct_booking',
        label: 'Prenotazione diretta',
        description:
            'Il cliente prenota subito una sessione nei tuoi slot liberi.',
        icon: CalendarCheck,
        color: 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400',
    },
] as const;

const activeContactCount = computed(
    () =>
        Number(contact.free_consultation) +
        Number(contact.direct_message) +
        Number(contact.direct_booking),
);

function toggleContact(key: keyof ContactOptions) {
    if (contact[key] && activeContactCount.value <= 1) {
        // Non si può spegnere l'ultimo canale attivo.
        return;
    }
    contact[key] = !contact[key];
}

const LATE_OPTIONS = [
    { value: '10', label: '10 min' },
    { value: '15', label: '15 min' },
    { value: '20', label: '20 min' },
    { value: '30', label: '30 min' },
];

function getData() {
    return {
        no_show_fee_cents: Math.round(noShowFee.value * 100),
        late_tolerance_minutes: parseInt(lateTolerance.value),
        free_first_session: freeFirst.value,
        welcome_discount_enabled: welcomeDiscount.value,
        welcome_discount_percent: welcomeDiscount.value
            ? discountPercent.value
            : null,
        custom_terms: customTerms.value || null,
        accepts_free_consultation: contact.free_consultation,
        accepts_direct_message: contact.direct_message,
        accepts_direct_booking: contact.direct_booking,
    };
}

defineExpose({ getData });
</script>

<template>
    <div class="space-y-5">
        <!-- Contact preferences -->
        <div
            class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            data-testid="contact-preferences"
        >
            <div class="flex items-start gap-3">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400"
                >
                    <MessageCircle class="h-5 w-5" />
                </span>
                <div class="flex-1">
                    <h3
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Come preferisci essere contattato?
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Scegli i canali attraverso cui i clienti possono
                        raggiungerti dalla tua vetrina.
                    </p>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                <div
                    v-for="channel in CONTACT_CHANNELS"
                    :key="channel.key"
                    :class="[
                        'rounded-2xl border p-4 transition-colors',
                        contact[channel.key]
                            ? 'border-teal-200 bg-teal-50/30 dark:border-teal-800 dark:bg-teal-900/10'
                            : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/40',
                    ]"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                :class="channel.color"
                            >
                                <component
                                    :is="channel.icon"
                                    class="h-4.5 w-4.5"
                                />
                            </span>
                            <div>
                                <h4
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ channel.label }}
                                </h4>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ channel.description }}
                                </p>
                                <p
                                    v-if="channel.key === 'free_consultation'"
                                    class="mt-1 text-xs text-teal-600 dark:text-teal-400"
                                >
                                    Una call conoscitiva — diversa dalla prima
                                    sessione gratuita, che è un vero
                                    allenamento.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            :data-testid="`toggle-contact-${channel.key}`"
                            :aria-pressed="
                                contact[channel.key] ? 'true' : 'false'
                            "
                            :class="[
                                'relative h-6 w-11 shrink-0 rounded-full transition-colors',
                                contact[channel.key]
                                    ? 'bg-teal-600'
                                    : 'bg-gray-300 dark:bg-gray-600',
                            ]"
                            :disabled="
                                contact[channel.key] && activeContactCount <= 1
                            "
                            @click="toggleContact(channel.key)"
                        >
                            <span
                                :class="[
                                    'absolute top-0.5 left-0 h-5 w-5 rounded-full bg-white shadow transition-transform',
                                    contact[channel.key]
                                        ? 'translate-x-5'
                                        : 'translate-x-0.5',
                                ]"
                            />
                        </button>
                    </div>
                </div>
            </div>

            <p
                v-if="serverErrors.contact_channels"
                class="mt-3 text-sm font-medium text-red-600 dark:text-red-400"
                data-testid="error-contact_channels"
            >
                {{ serverErrors.contact_channels }}
            </p>
            <p
                v-else-if="activeContactCount <= 1"
                class="mt-3 text-xs text-gray-400 dark:text-gray-500"
            >
                Deve restare attivo almeno un canale di contatto.
            </p>
        </div>

        <!-- No-show fee -->
        <div
            class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="flex items-start gap-3">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400"
                >
                    <ShieldAlert class="h-5 w-5" />
                </span>
                <div class="flex-1">
                    <h3
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Penale no-show
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Importo addebitato quando il cliente non si presenta
                        senza preavviso
                    </p>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-3">
                <div
                    class="flex items-center gap-1 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
                >
                    <span class="text-sm text-gray-500">€</span>
                    <input
                        v-model.number="noShowFee"
                        type="number"
                        min="0"
                        data-testid="input-noshow-fee"
                        class="w-16 border-0 bg-transparent p-0 text-lg font-semibold text-gray-900 focus:outline-none dark:text-white"
                    />
                </div>
            </div>
        </div>

        <!-- Late tolerance -->
        <div
            class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="flex items-start gap-3">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400"
                >
                    <Clock class="h-5 w-5" />
                </span>
                <div class="flex-1">
                    <h3
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Tolleranza ritardo
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Dopo quanti minuti la sessione è considerata persa
                    </p>
                </div>
            </div>
            <div
                class="mt-4 inline-flex rounded-xl border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-800"
            >
                <button
                    v-for="opt in LATE_OPTIONS"
                    :key="opt.value"
                    :data-testid="`late-${opt.value}`"
                    :class="[
                        'rounded-lg px-3.5 py-1.5 text-sm font-medium transition-all',
                        lateTolerance === opt.value
                            ? 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400',
                    ]"
                    @click="lateTolerance = opt.value"
                >
                    {{ opt.label }}
                </button>
            </div>
        </div>

        <!-- Free first session -->
        <div
            :class="[
                'rounded-3xl border p-6 shadow-sm transition-colors',
                freeFirst
                    ? 'border-teal-200 bg-teal-50/30 dark:border-teal-800 dark:bg-teal-900/10'
                    : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900',
            ]"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400"
                    >
                        <Gift class="h-5 w-5" />
                    </span>
                    <div>
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Prima sessione conoscitiva gratuita
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            30 minuti per conoscervi prima dell'acquisto
                        </p>
                    </div>
                </div>
                <button
                    data-testid="toggle-free-first"
                    :class="[
                        'relative h-6 w-11 shrink-0 rounded-full transition-colors',
                        freeFirst
                            ? 'bg-teal-600'
                            : 'bg-gray-300 dark:bg-gray-600',
                    ]"
                    @click="freeFirst = !freeFirst"
                >
                    <span
                        :class="[
                            'absolute top-0.5 left-0 h-5 w-5 rounded-full bg-white shadow transition-transform',
                            freeFirst ? 'translate-x-5' : 'translate-x-0.5',
                        ]"
                    />
                </button>
            </div>
            <div v-if="freeFirst" class="mt-3 ml-13">
                <div
                    class="rounded-xl bg-teal-100/50 px-3 py-2 text-xs font-medium text-teal-700 dark:bg-teal-900/30 dark:text-teal-300"
                >
                    Apparirà in vetrina come badge "Prova gratuita"
                </div>
            </div>
        </div>

        <!-- Welcome discount -->
        <div
            :class="[
                'rounded-3xl border p-6 shadow-sm transition-colors',
                welcomeDiscount
                    ? 'border-teal-200 bg-teal-50/30 dark:border-teal-800 dark:bg-teal-900/10'
                    : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900',
            ]"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400"
                    >
                        <Tag class="h-5 w-5" />
                    </span>
                    <div>
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Sconto primo cliente
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Promo applicata automaticamente sulla prima
                            prenotazione
                        </p>
                    </div>
                </div>
                <button
                    data-testid="toggle-welcome-discount"
                    :class="[
                        'relative h-6 w-11 shrink-0 rounded-full transition-colors',
                        welcomeDiscount
                            ? 'bg-teal-600'
                            : 'bg-gray-300 dark:bg-gray-600',
                    ]"
                    @click="welcomeDiscount = !welcomeDiscount"
                >
                    <span
                        :class="[
                            'absolute top-0.5 left-0 h-5 w-5 rounded-full bg-white shadow transition-transform',
                            welcomeDiscount
                                ? 'translate-x-5'
                                : 'translate-x-0.5',
                        ]"
                    />
                </button>
            </div>
            <div
                v-if="welcomeDiscount"
                class="mt-4 ml-13 flex items-center gap-3"
            >
                <div
                    class="flex items-center gap-1 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
                >
                    <input
                        v-model.number="discountPercent"
                        type="number"
                        min="1"
                        max="100"
                        data-testid="input-discount-percent"
                        class="w-12 border-0 bg-transparent p-0 text-right text-lg font-semibold text-gray-900 focus:outline-none dark:text-white"
                    />
                    <span class="text-sm text-gray-500">%</span>
                </div>
                <div
                    class="rounded-xl bg-purple-100 px-3 py-2 text-xs font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-300"
                >
                    Codice:
                    <span class="font-mono font-bold"
                        >BENVENUTO{{ discountPercent }}</span
                    >
                </div>
            </div>
        </div>

        <!-- Custom terms -->
        <div
            class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="flex items-start gap-3">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                >
                    <Scale class="h-5 w-5" />
                </span>
                <div class="flex-1">
                    <h3
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Termini personalizzati
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Regole specifiche da mostrare al cliente prima della
                        prenotazione
                    </p>
                </div>
            </div>
            <div class="mt-4">
                <textarea
                    v-model="customTerms"
                    rows="4"
                    placeholder="Es: La sessione si svolge presso la palestra concordata. Materiale fornito dal coach…"
                    data-testid="input-custom-terms"
                    class="w-full resize-none rounded-lg border-0 bg-gray-50 px-4 py-3 text-sm text-gray-900 ring-1 ring-gray-200 transition placeholder:text-gray-400 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-800 dark:text-white dark:ring-gray-700 dark:placeholder:text-gray-600"
                />
            </div>
        </div>
    </div>
</template>
