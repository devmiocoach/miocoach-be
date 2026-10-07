<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldLabel } from '@/components/ui/field';
import InputField from '@/components/ui/input/InputField.vue';
import { Form, Link } from '@inertiajs/vue3';
import { Shield } from 'lucide-vue-next';
import { ref } from 'vue';
import SplitAuthLayout from '../layouts/SplitAuthLayout.vue';

const code = ref('');
const remember = ref(false);
</script>

<template>
    <SplitAuthLayout :title="$t('Verifica 2FA')">
        <div class="space-y-6">
            <div class="text-center">
                <div
                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30"
                >
                    <Shield
                        class="h-8 w-8 text-emerald-600 dark:text-emerald-400"
                    />
                </div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $t('Verifica la Tua Identità') }}
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{
                        $t(
                            'Inserisci il codice a 6 cifre dalla tua app authenticator o un codice di backup.',
                        )
                    }}
                </p>
            </div>

            <Form
                :action="route('two-factor.challenge')"
                method="post"
                class="space-y-4"
                data-testid="2fa-challenge-form"
                disable-while-processing
            >
                <InputField
                    name="code"
                    type="text"
                    :label="$t('CODICE DI VERIFICA')"
                    :placeholder="$t('123456')"
                    autocomplete="one-time-code"
                    required
                    maxlength="6"
                    v-model="code"
                    class="h-14 border-none bg-slate-50 text-center text-2xl tracking-widest dark:bg-slate-900"
                    data-testid="2fa-challenge-code-input"
                />

                <div class="pt-2">
                    <Field>
                        <Field orientation="horizontal">
                            <Checkbox
                                id="remember"
                                name="remember"
                                v-model:checked="remember"
                                data-testid="remember-me"
                            />
                            <FieldLabel
                                for="remember"
                                class="font-normal text-slate-700 dark:text-slate-300"
                            >
                                {{ $t('Resta collegato per 30 giorni') }}
                            </FieldLabel>
                        </Field>
                    </Field>
                </div>

                <Button
                    type="submit"
                    class="h-12 w-full rounded-xl bg-emerald-500 text-base font-semibold text-white hover:bg-emerald-600"
                    data-testid="2fa-challenge-submit"
                >
                    {{ $t('Verifica e Accedi') }}
                </Button>
            </Form>

            <div class="space-y-2 text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{
                        $t(
                            'Hai perso il dispositivo? Usa uno dei tuoi codici di backup.',
                        )
                    }}
                </p>
                <Link
                    :href="route('two-factor.recovery.show')"
                    class="text-xs text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                >
                    {{
                        $t(
                            'Non hai accesso né ai codici? Recupera il tuo account',
                        )
                    }}
                </Link>
            </div>
        </div>
    </SplitAuthLayout>
</template>
