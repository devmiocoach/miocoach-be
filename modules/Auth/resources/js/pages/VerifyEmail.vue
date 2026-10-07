<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Form, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import SplitAuthLayout from '../layouts/SplitAuthLayout.vue';

const page = usePage();
const role = computed(() => page.props.auth?.user?.type || 'atleta');
</script>

<template>
    <SplitAuthLayout :title="$t('Verifica Email')">
        <div class="mb-8">
            <h2 class="mb-2 text-4xl font-bold text-slate-900 dark:text-white">
                {{ $t('Verifica la tua Email') }}
            </h2>
            <p class="text-gray-500 dark:text-gray-400">
                {{
                    $t(
                        "Grazie per esserti registrato! Prima di iniziare, potresti verificare il tuo indirizzo email cliccando sul link che ti abbiamo appena inviato? Se non hai ricevuto l'email, te ne invieremo volentieri un'altra.",
                    )
                }}
            </p>
        </div>

        <Form
            :action="route('verification.send')"
            method="post"
            class="w-full space-y-5"
            data-testid="verify-email-form"
            disable-while-processing
        >
            <Button
                type="submit"
                class="mt-2 h-12 w-full rounded-xl text-base font-semibold text-white transition-colors"
                :class="
                    role === 'atleta'
                        ? 'bg-emerald-500 hover:bg-emerald-600'
                        : 'bg-indigo-500 hover:bg-indigo-600'
                "
            >
                {{ $t('Reinvia Email di Verifica') }}
            </Button>

            <p
                class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400"
            >
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="font-semibold hover:underline"
                    :class="
                        role === 'atleta'
                            ? 'text-emerald-600'
                            : 'text-indigo-600'
                    "
                    data-testid="logout-link"
                >
                    {{ $t('Esci') }}
                </Link>
            </p>
        </Form>
    </SplitAuthLayout>
</template>
