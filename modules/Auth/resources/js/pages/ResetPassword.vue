<script setup lang="ts">
import { Button } from '@/components/ui/button';
import InputField from '@/components/ui/input/InputField.vue';
import { Form } from '@inertiajs/vue3';
import { LockKeyhole, Mail } from 'lucide-vue-next';
import SplitAuthLayout from '../layouts/SplitAuthLayout.vue';

defineProps<{
    email: string;
    token: string;
}>();
</script>

<template>
    <SplitAuthLayout :title="$t('Reimposta Password')">
        <div class="mb-8">
            <h2 class="mb-2 text-4xl font-bold text-slate-900 dark:text-white">
                {{ $t('Reimposta Password') }}
            </h2>
            <p class="text-gray-500 dark:text-gray-400">
                {{ $t('Inserisci la tua nuova password qui sotto') }}
            </p>
        </div>

        <Form
            :action="route('password.store')"
            method="post"
            class="w-full space-y-5"
            data-testid="reset-password-form"
            disable-while-processing
            :reset-on-error="['password', 'password_confirmation']"
        >
            <input
                type="hidden"
                name="token"
                :value="token"
                data-testid="token"
            />

            <!-- Email -->
            <InputField
                name="email"
                type="email"
                :label="$t('Email')"
                :model-value="email"
                required
                readonly
                class="h-12 border-none bg-slate-50 dark:bg-slate-900"
            >
                <template #icon>
                    <Mail class="h-5 w-5" />
                </template>
            </InputField>

            <!-- Password -->
            <InputField
                name="password"
                type="password"
                :label="$t('Password')"
                :placeholder="$t('Inserisci la tua password')"
                autocomplete="new-password"
                required
                class="h-12 border-none bg-slate-50 dark:bg-slate-900"
            >
                <template #icon>
                    <LockKeyhole class="h-5 w-5" />
                </template>
            </InputField>

            <!-- Password Confirmation -->
            <InputField
                name="password_confirmation"
                type="password"
                :label="$t('Confirm Password')"
                :placeholder="$t('Conferma la tua nuova password')"
                autocomplete="new-password"
                required
                class="h-12 border-none bg-slate-50 dark:bg-slate-900"
            >
                <template #icon>
                    <LockKeyhole class="h-5 w-5" />
                </template>
            </InputField>

            <Button
                type="submit"
                class="mt-2 h-12 w-full rounded-xl bg-emerald-500 text-base font-semibold text-white hover:bg-emerald-600"
            >
                {{ $t('Reimposta Password') }}
            </Button>
        </Form>
    </SplitAuthLayout>
</template>
