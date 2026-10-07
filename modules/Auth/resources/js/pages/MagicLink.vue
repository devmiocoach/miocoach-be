<script setup lang="ts">
import { Button } from '@/components/ui/button';
import InputField from '@/components/ui/input/InputField.vue';
import { Form, Link } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import SplitAuthLayout from '../layouts/SplitAuthLayout.vue';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <SplitAuthLayout :title="$t('Accesso con Link Magico')">
        <div class="mb-8">
            <h2 class="mb-2 text-4xl font-bold text-slate-900 dark:text-white">
                {{ $t('Accesso con Link Magico') }}
            </h2>
            <p class="text-gray-500 dark:text-gray-400">
                {{
                    $t(
                        'Inserisci la tua email per ricevere un link di accesso sicuro e monouso.',
                    )
                }}
            </p>
        </div>

        <Form
            :action="route('magic-link.store')"
            method="post"
            class="w-full space-y-5"
            data-testid="magic-link-form"
            disable-while-processing
            :reset-on-success="['email']"
        >
            <InputField
                name="email"
                type="email"
                :label="$t('Email')"
                :placeholder="$t('Inserisci la tua email')"
                required
                autocomplete="email"
                data-testid="magic-link-email"
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
                    data-testid="magic-link-submit"
                >
                    {{ $t('Invia Link Magico') }}
                </Button>
            </div>
        </Form>
    </SplitAuthLayout>
</template>
