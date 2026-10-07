<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CoachPackageInput {
    name: string;
    sessions_count: number;
    price_cents: number;
}

interface CoachData {
    single_session_price_cents: number | null;
    monthly_subscription_cents: number | null;
    quarterly_subscription_cents: number | null;
}

interface CoachPackageProp {
    id?: number;
    name: string;
    sessions_count: number;
    price_cents: number;
}

const props = defineProps<{
    coach: CoachData | null;
    packages: CoachPackageProp[];
}>();

const page = usePage();
const errors = computed(
    () => (page.props.errors as Record<string, string>) ?? {},
);

// Prices displayed as euros, stored as cents
const singlePriceEur = ref(
    props.coach?.single_session_price_cents
        ? props.coach.single_session_price_cents / 100
        : 0,
);
const monthlyEnabled = ref(!!props.coach?.monthly_subscription_cents);
const monthlyPriceEur = ref(
    props.coach?.monthly_subscription_cents
        ? props.coach.monthly_subscription_cents / 100
        : 0,
);
const quarterlyEnabled = ref(!!props.coach?.quarterly_subscription_cents);
const quarterlyPriceEur = ref(
    props.coach?.quarterly_subscription_cents
        ? props.coach.quarterly_subscription_cents / 100
        : 0,
);

const packageList = ref<CoachPackageInput[]>(
    props.packages.map((p) => ({
        name: p.name,
        sessions_count: p.sessions_count,
        price_cents: p.price_cents,
    })),
);

function addPackage() {
    packageList.value.push({ name: '', sessions_count: 5, price_cents: 0 });
}

function removePackage(index: number) {
    packageList.value.splice(index, 1);
}

function packagePriceEur(pkg: CoachPackageInput): number {
    return pkg.price_cents / 100;
}

function setPackagePriceEur(index: number, eur: number) {
    packageList.value[index].price_cents = Math.round(eur * 100);
}

function savingsCents(pkg: CoachPackageInput): number {
    if (!singlePriceEur.value) return 0;
    const fullPrice = singlePriceEur.value * 100 * pkg.sessions_count;
    return Math.max(0, fullPrice - pkg.price_cents);
}

function getData() {
    return {
        single_session_price_cents: Math.round(singlePriceEur.value * 100),
        monthly_subscription_cents: monthlyEnabled.value
            ? Math.round(monthlyPriceEur.value * 100)
            : null,
        quarterly_subscription_cents: quarterlyEnabled.value
            ? Math.round(quarterlyPriceEur.value * 100)
            : null,
        packages: packageList.value.filter((p) => p.name && p.price_cents > 0),
    };
}

function onlyInt(e: KeyboardEvent) {
    // Allow: backspace, delete, tab, escape, enter, arrows, home, end
    if (
        [
            'Backspace',
            'Delete',
            'Tab',
            'Escape',
            'Enter',
            'ArrowLeft',
            'ArrowRight',
            'ArrowUp',
            'ArrowDown',
            'Home',
            'End',
        ].includes(e.key)
    )
        return;
    // Block anything not a digit
    if (!/^\d$/.test(e.key)) e.preventDefault();
}

defineExpose({ getData });
</script>

<template>
    <div class="space-y-5">
        <!-- Single session card (highlighted) -->
        <div
            class="relative rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            style="
                background-image: linear-gradient(
                    135deg,
                    rgba(13, 148, 136, 0.04),
                    transparent 60%
                );
            "
        >
            <div
                class="absolute top-6 bottom-6 left-0 w-1 rounded-r-full bg-gradient-to-b from-teal-600 to-teal-400"
            />

            <div class="flex items-start justify-between pl-4">
                <div>
                    <p
                        class="text-xs font-bold tracking-widest text-teal-600 uppercase dark:text-teal-400"
                    >
                        Servizio standard
                    </p>
                    <h2
                        class="mt-1 text-2xl font-bold text-gray-900 dark:text-white"
                    >
                        Sessione singola
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Tariffa base &middot; per ora
                    </p>
                </div>
                <div class="flex items-baseline gap-1">
                    <span
                        class="text-lg font-bold text-gray-500 dark:text-gray-400"
                        >&euro;</span
                    >
                    <input
                        v-model.number="singlePriceEur"
                        type="number"
                        min="1"
                        step="1"
                        placeholder="45"
                        data-testid="input-single-price"
                        class="w-28 border-0 bg-transparent pr-6 text-right text-3xl font-extrabold text-teal-600 focus:outline-none dark:text-teal-400 [&::-webkit-inner-spin-button]:opacity-30 [&::-webkit-outer-spin-button]:opacity-30"
                        @keydown="onlyInt"
                    />
                    <span class="text-sm text-gray-400">/ ora</span>
                </div>
            </div>

            <p
                v-if="errors.single_session_price_cents"
                class="mt-3 pl-4 text-xs text-red-500 dark:text-red-400"
                data-testid="error-single-price"
            >
                {{ errors.single_session_price_cents }}
            </p>
        </div>

        <!-- Packages card -->
        <div
            class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                    Pacchetti Sessioni
                </h2>
                <button
                    :disabled="packageList.length >= 10"
                    data-testid="add-package-btn"
                    class="flex items-center gap-1.5 rounded-lg bg-teal-50 px-4 py-2 text-sm font-semibold text-teal-700 transition hover:bg-teal-100 disabled:opacity-40 dark:bg-teal-900/30 dark:text-teal-300"
                    @click="addPackage"
                >
                    <Plus class="h-4 w-4" />
                    Aggiungi pacchetto
                </button>
            </div>

            <div
                v-if="packageList.length === 0"
                class="rounded-2xl border-2 border-dashed border-gray-200 py-10 text-center dark:border-gray-700"
            >
                <p class="text-sm text-gray-400 dark:text-gray-500">
                    Nessun pacchetto. Clicca "Aggiungi pacchetto" per crearne
                    uno.
                </p>
            </div>

            <div class="space-y-3">
                <div
                    v-for="(pkg, index) in packageList"
                    :key="index"
                    :data-testid="`package-card-${index}`"
                    class="rounded-2xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50"
                >
                    <div class="grid grid-cols-12 items-end gap-3">
                        <div class="col-span-12 sm:col-span-4">
                            <label
                                class="mb-1 block text-xs font-semibold tracking-wide text-gray-400 uppercase"
                                >Nome</label
                            >
                            <input
                                v-model="pkg.name"
                                type="text"
                                placeholder="Pacchetto 5 sessioni"
                                :data-testid="`package-name-${index}`"
                                class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-900 dark:text-white dark:ring-gray-700"
                            />
                        </div>
                        <div class="col-span-5 sm:col-span-3">
                            <label
                                class="mb-1 block text-xs font-semibold tracking-wide text-gray-400 uppercase"
                                >N° sessioni</label
                            >
                            <input
                                v-model.number="pkg.sessions_count"
                                type="number"
                                min="2"
                                max="100"
                                :data-testid="`package-sessions-${index}`"
                                class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-900 dark:text-white dark:ring-gray-700"
                                @keydown="onlyInt"
                            />
                        </div>
                        <div class="col-span-5 sm:col-span-3">
                            <label
                                class="mb-1 block text-xs font-semibold tracking-wide text-gray-400 uppercase"
                                >Prezzo (&euro;)</label
                            >
                            <input
                                :value="packagePriceEur(pkg)"
                                type="number"
                                min="1"
                                step="1"
                                :data-testid="`package-price-${index}`"
                                class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-900 dark:text-white dark:ring-gray-700"
                                @keydown="onlyInt"
                                @input="
                                    setPackagePriceEur(
                                        index,
                                        Number(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        ),
                                    )
                                "
                            />
                        </div>
                        <div class="col-span-2 flex justify-end">
                            <button
                                class="rounded-lg p-2 text-gray-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20"
                                :data-testid="`remove-package-${index}`"
                                @click="removePackage(index)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="savingsCents(pkg) > 0"
                        class="mt-3 inline-flex items-center gap-1 rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-teal-600 dark:bg-teal-900/30 dark:text-teal-300"
                    >
                        Risparmi &euro;{{
                            (savingsCents(pkg) / 100).toFixed(0)
                        }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Subscriptions card -->
        <div
            class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                Abbonamenti
            </h2>
            <p class="mt-1 mb-5 text-sm text-gray-500 dark:text-gray-400">
                Offri piani ricorrenti ai tuoi clienti per entrate prevedibili.
            </p>

            <div class="space-y-3">
                <!-- Monthly -->
                <div
                    :class="
                        monthlyEnabled
                            ? 'border-teal-200 bg-teal-50/30 dark:border-teal-800 dark:bg-teal-900/10'
                            : 'border-gray-100 bg-white dark:border-gray-800 dark:bg-gray-900'
                    "
                    class="flex items-center justify-between rounded-xl border p-4 transition-colors"
                >
                    <label class="flex cursor-pointer items-center gap-3">
                        <input
                            v-model="monthlyEnabled"
                            type="checkbox"
                            data-testid="toggle-monthly"
                            class="peer sr-only"
                        />
                        <span
                            :class="
                                monthlyEnabled
                                    ? 'bg-teal-500'
                                    : 'bg-gray-200 dark:bg-gray-700'
                            "
                            class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                        >
                            <span
                                :class="
                                    monthlyEnabled
                                        ? 'translate-x-4'
                                        : 'translate-x-0.5'
                                "
                                class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform"
                            />
                        </span>
                        <span
                            class="text-sm font-medium text-gray-900 dark:text-white"
                            >Abbonamento Mensile</span
                        >
                    </label>
                    <div
                        v-if="monthlyEnabled"
                        class="flex items-baseline gap-1"
                    >
                        <span class="text-sm font-bold text-gray-500"
                            >&euro;</span
                        >
                        <input
                            v-model.number="monthlyPriceEur"
                            type="number"
                            min="1"
                            step="1"
                            data-testid="input-monthly-price"
                            class="w-20 border-0 bg-transparent text-right text-lg font-bold text-gray-900 focus:outline-none dark:text-white"
                            @keydown="onlyInt"
                        />
                        <span class="text-xs text-gray-400">/mese</span>
                    </div>
                </div>

                <!-- Quarterly -->
                <div
                    :class="
                        quarterlyEnabled
                            ? 'border-teal-200 bg-teal-50/30 dark:border-teal-800 dark:bg-teal-900/10'
                            : 'border-gray-100 bg-white dark:border-gray-800 dark:bg-gray-900'
                    "
                    class="flex items-center justify-between rounded-xl border p-4 transition-colors"
                >
                    <label class="flex cursor-pointer items-center gap-3">
                        <input
                            v-model="quarterlyEnabled"
                            type="checkbox"
                            data-testid="toggle-quarterly"
                            class="peer sr-only"
                        />
                        <span
                            :class="
                                quarterlyEnabled
                                    ? 'bg-teal-500'
                                    : 'bg-gray-200 dark:bg-gray-700'
                            "
                            class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                        >
                            <span
                                :class="
                                    quarterlyEnabled
                                        ? 'translate-x-4'
                                        : 'translate-x-0.5'
                                "
                                class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform"
                            />
                        </span>
                        <span
                            class="text-sm font-medium text-gray-900 dark:text-white"
                            >Abbonamento Trimestrale</span
                        >
                    </label>
                    <div
                        v-if="quarterlyEnabled"
                        class="flex items-baseline gap-1"
                    >
                        <span class="text-sm font-bold text-gray-500"
                            >&euro;</span
                        >
                        <input
                            v-model.number="quarterlyPriceEur"
                            type="number"
                            min="1"
                            step="1"
                            data-testid="input-quarterly-price"
                            class="w-20 border-0 bg-transparent text-right text-lg font-bold text-gray-900 focus:outline-none dark:text-white"
                            @keydown="onlyInt"
                        />
                        <span class="text-xs text-gray-400">/trimestre</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
