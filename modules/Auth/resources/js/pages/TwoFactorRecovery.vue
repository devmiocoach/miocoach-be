<script setup lang="ts">
import { Button } from '@/components/ui/button';
import InputField from '@/components/ui/input/InputField.vue';
import { Form, Link } from '@inertiajs/vue3';
import { KeyRound } from 'lucide-vue-next';
import SplitAuthLayout from '../layouts/SplitAuthLayout.vue';
</script>

<template>
    <SplitAuthLayout :title="$t('Recupero 2FA')">
        <div class="space-y-6">
            <div class="text-center">
                <div
                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30"
                >
                    <KeyRound
                        class="h-8 w-8 text-amber-600 dark:text-amber-400"
                    />
                </div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $t('Recupero Autenticazione a Due Fattori') }}
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{
                        $t(
                            'Non hai accesso al tuo dispositivo 2FA o ai codici di backup? Inserisci la tua email per ricevere un link di recupero.',
                        )
                    }}
                </p>
            </div>

            <div
                class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20"
            >
                <p class="text-sm text-amber-800 dark:text-amber-300">
                    {{
                        $t(
                            'Attenzione: il link di recupero disabiliterà completamente il 2FA sul tuo account. Dovrai riattivarlo manualmente dopo il login.',
                        )
                    }}
                </p>
            </div>

            <Form
                :action="route('two-factor.recovery.send')"
                method="post"
                class="space-y-4"
                disable-while-processing
            >
                <InputField
                    name="email"
                    type="email"
                    :label="$t('Email')"
                    :placeholder="$t('La tua email')"
                    autocomplete="email"
                    required
                    class="h-12 border-none bg-slate-50 dark:bg-slate-900"
                />

                <Button
                    type="submit"
                    class="h-12 w-full rounded-xl bg-indigo-500 text-base font-semibold text-white hover:bg-indigo-600"
                    data-testid="2fa-recovery-submit"
                >
                    {{ $t('Invia Link di Recupero') }}
                </Button>
            </Form>

            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                <Link
                    :href="route('login')"
                    class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                >
                    {{ $t('Torna al login') }}
                </Link>
            </p>
        </div>
    </SplitAuthLayout>
</template>
