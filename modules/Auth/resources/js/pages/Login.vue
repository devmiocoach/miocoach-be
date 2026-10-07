<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldLabel } from '@/components/ui/field';
import InputField from '@/components/ui/input/InputField.vue';
import { Form, Link } from '@inertiajs/vue3';
import { Dumbbell, LockKeyhole, Mail, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SocialiteProviders from '../components/SocialiteProviders.vue';
import SplitAuthLayout from '../layouts/SplitAuthLayout.vue';

const emailRef = ref('');
const role = ref<'atleta' | 'coach'>('atleta');

const forgotUrl = computed(() =>
    route('password.request', { email: emailRef.value }),
);
</script>

<template>
    <SplitAuthLayout :title="$t('Accedi')">
        <div class="mb-6 flex rounded-full bg-slate-50 p-1 dark:bg-slate-900">
            <button
                type="button"
                @click="role = 'atleta'"
                class="flex flex-1 items-center justify-center gap-2 rounded-full py-3 text-sm font-medium transition-all"
                :class="
                    role === 'atleta'
                        ? 'bg-white text-emerald-600 shadow-sm dark:bg-slate-800'
                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                "
            >
                <User class="h-4 w-4" />
                {{ $t('Sono un Atleta') }}
            </button>
            <button
                type="button"
                @click="role = 'coach'"
                class="flex flex-1 items-center justify-center gap-2 rounded-full py-3 text-sm font-medium transition-all"
                :class="
                    role === 'coach'
                        ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-800'
                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                "
            >
                <Dumbbell class="h-4 w-4" />
                {{ $t('Sono un Coach') }}
            </button>
        </div>

        <div class="mb-6">
            <h2 class="mb-1 text-3xl font-bold text-slate-900 dark:text-white">
                {{
                    role === 'atleta'
                        ? $t('Bentornato Atleta.')
                        : $t('Bentornato Coach.')
                }}
            </h2>
            <p class="text-gray-500 dark:text-gray-400">
                {{
                    role === 'atleta'
                        ? $t(
                              'Accedi per visualizzare le tue schede e tracciare i tuoi progressi.',
                          )
                        : $t(
                              'Accedi alla dashboard per gestire i tuoi atleti e le programmazioni.',
                          )
                }}
            </p>
        </div>

        <Form
            :action="route('login')"
            method="post"
            :transform="
                (data: Record<string, unknown>) => ({ ...data, type: role })
            "
            class="space-y-4"
            data-testid="login-form"
            disable-while-processing
            :reset-on-error="['password']"
        >
            <InputField
                name="email"
                type="email"
                :label="$t('INDIRIZZO EMAIL')"
                :placeholder="$t('nome@esempio.it')"
                autocomplete="email"
                required
                v-model="emailRef"
                class="h-12 border-none bg-slate-50 dark:bg-slate-900"
            >
                <template #icon>
                    <Mail class="h-5 w-5" />
                </template>
            </InputField>

            <div class="relative">
                <InputField
                    name="password"
                    type="password"
                    :label="$t('PASSWORD')"
                    :placeholder="$t('Inserisci la tua password')"
                    autocomplete="current-password"
                    required
                    class="h-12 border-none bg-slate-50 dark:bg-slate-900"
                >
                    <template #icon>
                        <LockKeyhole class="h-5 w-5" />
                    </template>
                </InputField>

                <Link
                    v-if="route().has('password.request')"
                    :href="forgotUrl"
                    class="absolute top-0 right-0 text-xs font-semibold hover:underline"
                    :class="
                        role === 'atleta'
                            ? 'text-emerald-600'
                            : 'text-indigo-600'
                    "
                    data-testid="forgot-password-link"
                >
                    {{ $t('Dimenticata?') }}
                </Link>
            </div>

            <div class="pt-2">
                <Field>
                    <Field orientation="horizontal">
                        <Checkbox
                            id="remember"
                            name="remember"
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
                class="h-12 w-full rounded-xl text-base font-semibold text-white transition-colors"
                :class="
                    role === 'atleta'
                        ? 'bg-emerald-500 hover:bg-emerald-600'
                        : 'bg-indigo-500 hover:bg-indigo-600'
                "
                data-testid="login-button"
            >
                {{
                    role === 'atleta'
                        ? $t('Accedi ai tuoi allenamenti')
                        : $t('Accedi al pannello Coach')
                }}
            </Button>

            <SocialiteProviders />

            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                {{ $t('Non hai ancora un account?') }}
                <Link
                    :href="route('register')"
                    class="font-semibold hover:underline"
                    :class="
                        role === 'atleta'
                            ? 'text-emerald-600'
                            : 'text-indigo-600'
                    "
                    data-testid="sign-up-link"
                >
                    {{ $t('Registrati ora') }}
                </Link>
            </p>
        </Form>
    </SplitAuthLayout>
</template>
