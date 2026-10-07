<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { Copy, Download, ShieldCheck } from 'lucide-vue-next';
import { ref } from 'vue';
import SplitAuthLayout from '../layouts/SplitAuthLayout.vue';

const props = defineProps<{
    backup_codes: string[];
}>();

const copied = ref(false);

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
</script>

<template>
    <SplitAuthLayout :title="$t('Nuovi Codici di Backup')">
        <div class="space-y-6">
            <div class="text-center">
                <div
                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30"
                >
                    <ShieldCheck
                        class="h-8 w-8 text-emerald-600 dark:text-emerald-400"
                    />
                </div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $t('Nuovi Codici di Backup Generati') }}
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{
                        $t(
                            'I vecchi codici sono stati invalidati. Conserva questi nuovi codici in un luogo sicuro.',
                        )
                    }}
                </p>
            </div>

            <!-- Backup Codes -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">{{
                        $t('I Tuoi Codici di Backup')
                    }}</CardTitle>
                    <CardDescription>
                        {{
                            $t(
                                'Ogni codice può essere usato una sola volta. Se perdi accesso al tuo dispositivo 2FA, usa uno di questi codici.',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
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
                    <div class="mt-4 flex gap-3">
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
                </CardContent>
            </Card>

            <Link :href="route('settings.security')">
                <Button
                    type="button"
                    class="h-12 w-full rounded-xl bg-indigo-500 text-base font-semibold text-white hover:bg-indigo-600"
                    data-testid="2fa-back-to-settings-button"
                >
                    {{ $t('Torna alle Impostazioni di Sicurezza') }}
                </Button>
            </Link>
        </div>
    </SplitAuthLayout>
</template>
