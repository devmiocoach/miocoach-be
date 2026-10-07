<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { CheckCircle, Copy, Download, Shield } from 'lucide-vue-next';
import { ref } from 'vue';
import SplitAuthLayout from '../layouts/SplitAuthLayout.vue';

const props = defineProps<{
    qr_code_svg: string;
    backup_codes: string[];
}>();

const copied = ref(false);
const confirmed = ref(false);

async function copyBackupCodes() {
    const text = props.backup_codes.join('\n');
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        copied.value = false;
    }
}

function downloadBackupCodes() {
    const text = `MioCoach - Backup Codes 2FA\nGenerati il: ${new Date().toLocaleDateString('it-IT')}\n\n${props.backup_codes.join('\n')}\n\nConserva questi codici in un luogo sicuro. Ogni codice può essere utilizzato una sola volta.`;
    const blob = new Blob([text], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'miocoach-2fa-backup-codes.txt';
    a.click();
    URL.revokeObjectURL(url);
}

function showBackupCodes() {
    confirmed.value = true;
}

function proceedToConfirm() {
    router.visit(route('two-factor.confirm.show'));
}
</script>

<template>
    <SplitAuthLayout :title="$t('Configura 2FA')">
        <div v-if="!confirmed" class="space-y-6">
            <div class="text-center">
                <div
                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30"
                >
                    <Shield
                        class="h-8 w-8 text-indigo-600 dark:text-indigo-400"
                    />
                </div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $t('Configura Autenticazione a Due Fattori') }}
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{
                        $t(
                            'Scansiona il QR code con la tua app authenticator (Google Authenticator, Authy, ecc.)',
                        )
                    }}
                </p>
            </div>

            <!-- QR Code -->
            <div
                class="flex justify-center rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
            >
                <div v-html="qr_code_svg" class="max-w-[200px]" />
            </div>

            <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                {{
                    $t(
                        'Dopo la scansione, clicca "Avanti" per confermare il codice.',
                    )
                }}
            </p>

            <Button
                type="button"
                @click="showBackupCodes"
                class="h-12 w-full rounded-xl bg-indigo-500 text-base font-semibold text-white hover:bg-indigo-600"
                data-testid="2fa-next-button"
            >
                {{ $t('Avanti') }}
            </Button>
        </div>

        <div v-else class="space-y-6">
            <div class="text-center">
                <div
                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30"
                >
                    <CheckCircle
                        class="h-8 w-8 text-emerald-600 dark:text-emerald-400"
                    />
                </div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $t('Salva i Codici di Backup') }}
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{
                        $t(
                            'Conserva questi codici in un luogo sicuro. Potrai usarli se perdi accesso al tuo dispositivo 2FA.',
                        )
                    }}
                </p>
            </div>

            <!-- Backup Codes -->
            <div
                class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900"
            >
                <div class="grid grid-cols-2 gap-2">
                    <code
                        v-for="code in backup_codes"
                        :key="code"
                        class="rounded-lg bg-white px-3 py-2 text-center font-mono text-sm font-semibold text-gray-800 shadow-sm dark:bg-gray-800 dark:text-gray-200"
                    >
                        {{ code }}
                    </code>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-3">
                <Button
                    type="button"
                    @click="copyBackupCodes"
                    variant="outline"
                    class="h-12 flex-1 rounded-xl"
                    data-testid="2fa-copy-button"
                >
                    <Copy class="mr-2 h-4 w-4" />
                    {{ copied ? $t('Copiati!') : $t('Copia') }}
                </Button>
                <Button
                    type="button"
                    @click="downloadBackupCodes"
                    variant="outline"
                    class="h-12 flex-1 rounded-xl"
                    data-testid="2fa-download-button"
                >
                    <Download class="mr-2 h-4 w-4" />
                    {{ $t('Scarica') }}
                </Button>
            </div>

            <Button
                type="button"
                @click="proceedToConfirm"
                class="h-12 w-full rounded-xl bg-indigo-500 text-base font-semibold text-white hover:bg-indigo-600"
                data-testid="2fa-confirm-setup-button"
            >
                {{ $t('Ho salvato i codici, continua') }}
            </Button>
        </div>
    </SplitAuthLayout>
</template>
