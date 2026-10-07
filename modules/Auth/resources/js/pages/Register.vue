<script setup lang="ts">
import { Button } from '@/components/ui/button';
import InputField from '@/components/ui/input/InputField.vue';
import { usePasswordStrength } from '@/composables/usePasswordStrength';
import { Form, Link } from '@inertiajs/vue3';
import { Dumbbell, LockKeyhole, Mail, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PasswordStrengthIndicator from '../components/PasswordStrengthIndicator.vue';
import SocialiteProviders from '../components/SocialiteProviders.vue';
import SplitAuthLayout from '../layouts/SplitAuthLayout.vue';

const role = ref<'atleta' | 'coach'>('atleta');

const urlParams = computed(() => {
    const searchParams = new URLSearchParams(window.location.search);
    return searchParams.get('type');
});

if (urlParams.value === 'coach') {
    role.value = 'coach';
}

const password = ref('');
const { strength } = usePasswordStrength(password);
</script>

<template>
    <SplitAuthLayout :title="$t('Registrati')">
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
                        ? $t('Benvenuto Atleta.')
                        : $t('Benvenuto Coach.')
                }}
            </h2>
            <p class="text-gray-500 dark:text-gray-400">
                {{
                    role === 'atleta'
                        ? $t(
                              'Crea il tuo profilo per ricevere schede e tracciare i tuoi progressi.',
                          )
                        : $t(
                              'Crea il tuo profilo per iniziare a seguire e gestire i tuoi atleti.',
                          )
                }}
            </p>
        </div>

        <Form
            :action="route('register')"
            method="post"
            class="space-y-4"
            data-testid="register-form"
            disable-while-processing
            :transform="
                (data) => ({
                    ...data,
                    type: role,
                })
            "
        >
            <InputField
                name="name"
                type="text"
                :label="$t('NOME COMPLETO')"
                :placeholder="$t('Mario Rossi')"
                autocomplete="name"
                class="h-12 border-none bg-slate-50 dark:bg-slate-900"
            >
                <template #icon>
                    <User class="h-5 w-5" />
                </template>
            </InputField>

            <InputField
                name="email"
                type="email"
                :label="$t('INDIRIZZO EMAIL')"
                :placeholder="$t('nome@esempio.it')"
                autocomplete="email"
                class="h-12 border-none bg-slate-50 dark:bg-slate-900"
            >
                <template #icon>
                    <Mail class="h-5 w-5" />
                </template>
            </InputField>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputField
                        v-model="password"
                        name="password"
                        type="password"
                        :label="$t('PASSWORD')"
                        :placeholder="$t('Inserisci')"
                        autocomplete="new-password"
                        required
                        class="h-12 border-none bg-slate-50 dark:bg-slate-900"
                    >
                        <template #icon>
                            <LockKeyhole class="h-5 w-5" />
                        </template>
                    </InputField>
                    <PasswordStrengthIndicator
                        v-if="password.length > 0"
                        :strength="strength"
                    />
                </div>

                <InputField
                    name="password_confirmation"
                    type="password"
                    :label="$t('CONFERMA PASSWORD')"
                    :placeholder="$t('Ripeti')"
                    autocomplete="new-password"
                    required
                    class="h-12 border-none bg-slate-50 dark:bg-slate-900"
                >
                    <template #icon>
                        <LockKeyhole class="h-5 w-5" />
                    </template>
                </InputField>
            </div>

            <Button
                type="submit"
                class="h-12 w-full rounded-xl text-base font-semibold text-white transition-colors"
                :class="
                    role === 'atleta'
                        ? 'bg-emerald-500 hover:bg-emerald-600'
                        : 'bg-indigo-500 hover:bg-indigo-600'
                "
                data-testid="register-button"
            >
                {{
                    role === 'atleta'
                        ? $t('Inizia ad allenarti')
                        : $t('Inizia a gestire')
                }}
            </Button>

            <SocialiteProviders />

            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                {{ $t('Hai già un account?') }}
                <Link
                    :href="route('login')"
                    class="font-semibold hover:underline"
                    :class="
                        role === 'atleta'
                            ? 'text-emerald-600'
                            : 'text-indigo-600'
                    "
                    data-testid="login-link"
                >
                    {{ $t('Accedi ora') }}
                </Link>
            </p>
        </Form>
    </SplitAuthLayout>
</template>
