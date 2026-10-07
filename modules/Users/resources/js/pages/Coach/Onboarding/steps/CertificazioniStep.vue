<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Award, FileText, Plus, Shield, Trash2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Certification {
    id?: number;
    name: string;
    issuer: string;
    issued_at: string | null;
    file_url: string | null;
}

interface CoachData {
    p_iva: string | null;
    has_insurance: boolean;
    insurance_company: string | null;
    insurance_expiry: string | null;
    insurance_file_url: string | null;
}

const props = defineProps<{
    coach: CoachData | null;
    certifications: Certification[];
}>();

const COMMON_ISSUERS = [
    'CONI',
    'FIF',
    'ISSA',
    'NASM',
    'FIPE',
    'FIDAL',
    'ACSM',
    'NSCA',
];

const certs = ref<
    Array<{
        name: string;
        issuer: string;
        year: string;
        file: File | null;
        existingUrl: string | null;
    }>
>(
    props.certifications.length > 0
        ? props.certifications.map((c) => ({
              name: c.name,
              issuer: c.issuer,
              year: c.issued_at ? c.issued_at.substring(0, 4) : '',
              file: null,
              existingUrl: c.file_url,
          }))
        : [{ name: '', issuer: '', year: '', file: null, existingUrl: null }],
);

const pIva = ref(props.coach?.p_iva ?? '');
const hasInsurance = ref(props.coach?.has_insurance ?? false);
const insuranceCompany = ref(props.coach?.insurance_company ?? '');
const insuranceExpiry = ref(props.coach?.insurance_expiry ?? '');
const insuranceFile = ref<File | null>(null);

const page = usePage();
const errors = computed(
    () => (page.props.errors as Record<string, string>) ?? {},
);

function validatePIva(value: string): string | null {
    if (!value) return null;
    if (!/^\d+$/.test(value)) return 'Solo cifre numeriche';
    if (value.length !== 11) return `${value.length}/11 cifre`;
    // check digit
    let sum = 0;
    for (let i = 0; i < 10; i++) {
        const d = parseInt(value[i]);
        if (i % 2 === 0) {
            sum += d;
        } else {
            const v = d * 2;
            sum += v >= 10 ? v - 9 : v;
        }
    }
    const check = (10 - (sum % 10)) % 10;
    if (check !== parseInt(value[10])) return 'Cifra di controllo non valida';
    return null;
}

const pIvaError = computed(() => validatePIva(pIva.value));
const pIvaValid = computed(
    () => pIva.value.length === 11 && pIvaError.value === null,
);

function onPIvaInput(e: Event) {
    // strip non-digits
    const raw = (e.target as HTMLInputElement).value
        .replace(/\D/g, '')
        .slice(0, 11);
    pIva.value = raw;
    (e.target as HTMLInputElement).value = raw;
}

function addCert() {
    certs.value.push({
        name: '',
        issuer: '',
        year: '',
        file: null,
        existingUrl: null,
    });
}

function removeCert(index: number) {
    certs.value.splice(index, 1);
}

function handleCertFile(event: Event, index: number) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) certs.value[index].file = file;
}

function handleInsuranceFile(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) insuranceFile.value = file;
}

function getData() {
    return {
        certifications: certs.value
            .filter((c) => c.name.trim())
            .map((c) => ({
                name: c.name,
                issuer: c.issuer,
                year: c.year,
            })),
        p_iva: pIva.value || null,
        has_insurance: hasInsurance.value,
        insurance_company: hasInsurance.value ? insuranceCompany.value : null,
        insurance_expiry: hasInsurance.value ? insuranceExpiry.value : null,
    };
}

function getFiles(): Array<{ key: string; file: File }> {
    const files: Array<{ key: string; file: File }> = [];
    certs.value.forEach((c, i) => {
        if (c.file) files.push({ key: `cert_file_${i}`, file: c.file });
    });
    if (insuranceFile.value)
        files.push({ key: 'insurance_file', file: insuranceFile.value });
    return files;
}

defineExpose({ getData, getFiles });
</script>

<template>
    <div class="space-y-5">
        <!-- Certifications card -->
        <div
            class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="mb-5 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Award class="h-5 w-5 text-teal-600 dark:text-teal-400" />
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Certificazioni
                    </h2>
                </div>
                <button
                    data-testid="add-cert-btn"
                    class="flex items-center gap-1.5 rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-teal-700"
                    @click="addCert"
                >
                    <Plus class="h-3.5 w-3.5" />
                    Aggiungi
                </button>
            </div>

            <div class="space-y-4">
                <div
                    v-for="(cert, index) in certs"
                    :key="index"
                    class="rounded-2xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50"
                    :data-testid="`cert-row-${index}`"
                >
                    <div
                        class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_80px_40px]"
                    >
                        <div>
                            <label
                                class="mb-1 block text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Ente rilascio
                            </label>
                            <input
                                v-model="cert.issuer"
                                type="text"
                                list="issuers-list"
                                placeholder="Es: CONI"
                                :data-testid="`cert-issuer-${index}`"
                                class="w-full rounded-lg border-0 bg-white px-3 py-2.5 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-800 dark:text-white dark:ring-gray-700"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Titolo
                            </label>
                            <input
                                v-model="cert.name"
                                type="text"
                                placeholder="Es: Personal Trainer"
                                :data-testid="`cert-name-${index}`"
                                class="w-full rounded-lg border-0 bg-white px-3 py-2.5 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-800 dark:text-white dark:ring-gray-700"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Anno
                            </label>
                            <input
                                v-model="cert.year"
                                type="text"
                                placeholder="2024"
                                maxlength="4"
                                :data-testid="`cert-year-${index}`"
                                class="w-full rounded-lg border-0 bg-white px-3 py-2.5 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-800 dark:text-white dark:ring-gray-700"
                            />
                        </div>
                        <div class="flex items-end">
                            <button
                                v-if="certs.length > 1"
                                :data-testid="`remove-cert-${index}`"
                                class="mb-0.5 flex h-10 w-10 items-center justify-center rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20"
                                @click="removeCert(index)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- File upload row -->
                    <div class="mt-3">
                        <label
                            :data-testid="`cert-file-btn-${index}`"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-500 transition hover:border-teal-400 hover:text-teal-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                        >
                            <Upload class="h-3.5 w-3.5" />
                            {{
                                cert.file
                                    ? cert.file.name
                                    : cert.existingUrl
                                      ? 'Documento caricato ✓'
                                      : 'Carica certificato (PDF/JPG)'
                            }}
                            <input
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="sr-only"
                                @change="handleCertFile($event, index)"
                            />
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- P.IVA card -->
        <div
            class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="mb-1 flex items-center gap-2">
                <FileText class="h-5 w-5 text-teal-600 dark:text-teal-400" />
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Partita IVA
                </h2>
            </div>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                11 cifre numeriche
            </p>
            <div class="relative">
                <input
                    :value="pIva"
                    type="text"
                    inputmode="numeric"
                    placeholder="12345678901"
                    maxlength="11"
                    data-testid="input-piva"
                    :class="[
                        'w-full rounded-lg border-0 bg-gray-50 px-4 py-3 font-mono text-gray-900 ring-1 transition focus:ring-2 focus:outline-none dark:bg-gray-800 dark:text-white',
                        pIva.length > 0 && pIvaError
                            ? 'ring-red-400 focus:ring-red-400 dark:ring-red-600'
                            : pIvaValid
                              ? 'ring-teal-500 focus:ring-teal-500'
                              : 'ring-gray-200 focus:ring-teal-500 dark:ring-gray-700',
                    ]"
                    @input="onPIvaInput"
                />
                <!-- Valid checkmark -->
                <div
                    v-if="pIvaValid"
                    class="absolute top-1/2 right-3 -translate-y-1/2"
                >
                    <svg
                        class="h-5 w-5 text-teal-500"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </div>
            </div>
            <!-- Inline error (real-time or server) -->
            <p
                v-if="pIva.length > 0 && pIvaError"
                class="mt-1.5 flex items-center gap-1 text-xs text-red-500 dark:text-red-400"
                data-testid="piva-error"
            >
                {{ pIvaError }}
            </p>
            <p
                v-else-if="errors.p_iva"
                class="mt-1.5 text-xs text-red-500 dark:text-red-400"
            >
                {{ errors.p_iva }}
            </p>
            <p
                v-else-if="pIvaValid"
                class="mt-1.5 text-xs text-teal-600 dark:text-teal-400"
            >
                P.IVA valida
            </p>
        </div>

        <!-- Insurance card -->
        <div
            :class="[
                'rounded-3xl border p-6 shadow-sm transition-colors',
                hasInsurance
                    ? 'border-teal-200 bg-teal-50/30 dark:border-teal-800 dark:bg-teal-900/10'
                    : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900',
            ]"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Shield class="h-5 w-5 text-teal-600 dark:text-teal-400" />
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Assicurazione professionale
                    </h2>
                </div>
                <button
                    data-testid="toggle-insurance"
                    :class="[
                        'relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors',
                        hasInsurance
                            ? 'bg-teal-600'
                            : 'bg-gray-300 dark:bg-gray-600',
                    ]"
                    @click="hasInsurance = !hasInsurance"
                >
                    <span
                        :class="[
                            'inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white shadow transition-transform',
                            hasInsurance
                                ? 'translate-x-[1.375rem]'
                                : 'translate-x-0.5',
                        ]"
                    />
                </button>
            </div>

            <div
                v-if="hasInsurance"
                class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2"
            >
                <div>
                    <label
                        class="mb-1 block text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                    >
                        Compagnia assicurativa
                    </label>
                    <input
                        v-model="insuranceCompany"
                        type="text"
                        placeholder="Es: Zurich"
                        data-testid="input-insurance-company"
                        class="w-full rounded-lg border-0 bg-white px-4 py-3 text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-800 dark:text-white dark:ring-gray-700"
                    />
                </div>
                <div>
                    <label
                        class="mb-1 block text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                    >
                        Scadenza polizza
                    </label>
                    <input
                        v-model="insuranceExpiry"
                        type="date"
                        data-testid="input-insurance-expiry"
                        class="w-full rounded-lg border-0 bg-white px-4 py-3 text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-800 dark:text-white dark:ring-gray-700"
                    />
                </div>
                <div class="sm:col-span-2">
                    <label
                        data-testid="upload-insurance-btn"
                        class="flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-500 transition hover:border-teal-400 hover:text-teal-600 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <Upload class="h-4 w-4" />
                        {{
                            insuranceFile
                                ? insuranceFile.name
                                : 'Carica polizza (PDF)'
                        }}
                        <input
                            type="file"
                            accept=".pdf"
                            class="sr-only"
                            @change="handleInsuranceFile"
                        />
                    </label>
                </div>
            </div>
        </div>

        <datalist id="issuers-list">
            <option
                v-for="issuer in COMMON_ISSUERS"
                :key="issuer"
                :value="issuer"
            />
        </datalist>
    </div>
</template>
