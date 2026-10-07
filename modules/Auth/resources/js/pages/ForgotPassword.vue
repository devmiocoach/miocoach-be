<script setup lang="ts">
import { Button } from '@/components/ui/button';
import InputField from '@/components/ui/input/InputField.vue';
import { Form, Link, usePage } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import { computed } from 'vue';
import SplitAuthLayout from '../layouts/SplitAuthLayout.vue';

const page = usePage();
const email = computed(() =>
    page.props.email ? String(page.props.email) : undefined,
);
</script>

<template>
    <SplitAuthLayout :title="$t('Password Dimenticata')">
        <div class="mb-8">
            <h2 class="mb-2 text-4xl font-bold text-slate-900 dark:text-white">
                {{ $t('Password Dimenticata') }}
            </h2>
            <p class="text-gray-500 dark:text-gray-400">
                {{
                    $t(
                        'Hai dimenticato la password? Nessun problema. Comunicaci il tuo indirizzo email e ti invieremo un link per reimpostarla.',
                    )
                }}
            </p>
        </div>

        <Form
            :action="route('password.email')"
            method="post"
            class="w-full space-y-5"
            data-testid="forgot-password-form"
            disable-while-processing
            :reset-on-success="['email']"
        >
            <InputField
                name="email"
                type="email"
                :label="$t('Email')"
                :placeholder="$t('Inserisci la tua email')"
                :model-value="email"
                required
                autocomplete="email"
                class="h-12 border-none bg-slate-50 dark:bg-slate-900"
            >
                <template #icon>
                    <Mail class="h-5 w-5" />
                </template>
            </InputField>

            <div
                class="flex flex-col-reverse gap-4 pt-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <Link
                    :href="route('login')"
                    class="text-center text-sm font-semibold text-emerald-600 hover:underline sm:text-left"
                    data-testid="back-to-login-link"
                >
                    {{ $t('Torna al login') }}
                </Link>
                <Button
                    type="submit"
                    class="h-12 rounded-xl bg-emerald-500 px-8 text-base font-semibold text-white hover:bg-emerald-600"
                    data-testid="reset-button"
                >
                    {{ $t('Invia link di reset') }}
                </Button>
            </div>
        </Form>
    </SplitAuthLayout>
</template>
