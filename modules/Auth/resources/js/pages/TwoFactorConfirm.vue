<script setup lang="ts">
import { Button } from '@/components/ui/button';
import InputField from '@/components/ui/input/InputField.vue';
import { Form } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { ref } from 'vue';
import SplitAuthLayout from '../layouts/SplitAuthLayout.vue';

defineProps<{
    two_factor_enabled?: boolean;
}>();

const code = ref('');
</script>

<template>
    <SplitAuthLayout :title="$t('Conferma 2FA')">
        <div class="space-y-6">
            <div class="text-center">
                <div
                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30"
                >
                    <ShieldCheck
                        class="h-8 w-8 text-indigo-600 dark:text-indigo-400"
                    />
                </div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $t('Conferma Autenticazione a Due Fattori') }}
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{
                        $t(
                            'Inserisci il codice a 6 cifre dalla tua app authenticator per verificare la configurazione.',
                        )
                    }}
                </p>
            </div>

            <Form
                :action="route('two-factor.confirm')"
                method="post"
                class="space-y-4"
                data-testid="2fa-confirm-form"
                disable-while-processing
            >
                <InputField
                    name="code"
                    type="text"
                    :label="$t('CODICE 2FA')"
                    :placeholder="$t('123456')"
                    autocomplete="one-time-code"
                    required
                    maxlength="6"
                    v-model="code"
                    class="h-14 border-none bg-slate-50 text-center text-2xl tracking-widest dark:bg-slate-900"
                    data-testid="2fa-code-input"
                />

                <Button
                    type="submit"
                    class="h-12 w-full rounded-xl bg-indigo-500 text-base font-semibold text-white hover:bg-indigo-600"
                    data-testid="2fa-confirm-button"
                >
                    {{ $t('Verifica e Attiva 2FA') }}
                </Button>
            </Form>

            <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                {{
                    $t(
                        'Il codice cambia ogni 30 secondi. Assicurati di inserire quello corrente.',
                    )
                }}
            </p>
        </div>
    </SplitAuthLayout>
</template>
