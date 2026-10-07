<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Form, Link } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';
import SplitAuthLayout from '../layouts/SplitAuthLayout.vue';

defineProps<{
    token: string;
}>();
</script>

<template>
    <SplitAuthLayout :title="$t('Conferma Recupero 2FA')">
        <div class="space-y-6">
            <div class="text-center">
                <div
                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30"
                >
                    <ShieldAlert
                        class="h-8 w-8 text-red-600 dark:text-red-400"
                    />
                </div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $t('Conferma Disabilitazione 2FA') }}
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{
                        $t(
                            "Stai per disabilitare l'autenticazione a due fattori sul tuo account. Questa azione non è reversibile.",
                        )
                    }}
                </p>
            </div>

            <div
                class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
            >
                <p class="text-sm text-red-800 dark:text-red-300">
                    {{
                        $t(
                            'Dopo la conferma, chiunque con la tua email e password potrà accedere al tuo account senza verifica aggiuntiva.',
                        )
                    }}
                </p>
            </div>

            <Form
                :action="route('two-factor.recover.confirm', { token })"
                method="post"
                class="space-y-4"
                disable-while-processing
            >
                <Button
                    type="submit"
                    class="h-12 w-full rounded-xl bg-red-500 text-base font-semibold text-white hover:bg-red-600"
                    data-testid="2fa-recover-confirm"
                >
                    {{ $t('Conferma Disabilitazione 2FA') }}
                </Button>
            </Form>

            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                <Link
                    :href="route('login')"
                    class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                >
                    {{ $t('Annulla e torna al login') }}
                </Link>
            </p>
        </div>
    </SplitAuthLayout>
</template>
