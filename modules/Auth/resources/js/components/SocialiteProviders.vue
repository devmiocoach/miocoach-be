<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import IconApple from '~icons/simple-icons/apple';
import IconGoogle from '~icons/simple-icons/google';

type Provider = { name: string; icon: any; label: string };

const providers: Provider[] = [
    { name: 'google', icon: IconGoogle, label: 'Google' },
    { name: 'apple', icon: IconApple, label: 'Apple' },
];

const lastUsed = computed(() => usePage().props.auth?.last_social_provider);
</script>

<template>
    <div
        v-if="route().has('auth.socialite.redirect') && providers.length"
        class="mt-6 space-y-6"
    >
        <div
            class="after:border-border relative text-center text-sm after:absolute after:inset-0 after:top-1/2 after:z-0 after:flex after:items-center after:border-t"
        >
            <span
                class="text-muted-foreground relative z-10 bg-white px-2 dark:bg-gray-950"
            >
                Oppure continua con
            </span>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div
                v-for="{ name, icon, label } in providers"
                :key="name"
                class="relative"
            >
                <Button
                    as-child
                    variant="outline"
                    class="h-12 w-full rounded-xl text-base font-medium"
                >
                    <a
                        :href="
                            route('auth.socialite.redirect', { provider: name })
                        "
                    >
                        <component
                            :is="icon"
                            class="mr-2 h-5 w-5"
                            :class="
                                name === 'apple'
                                    ? 'mb-[2px] text-black dark:text-white'
                                    : ''
                            "
                        />
                        <span>
                            {{ label }}
                        </span>
                    </a>
                </Button>
                <span
                    v-if="lastUsed === name"
                    :data-testid="`last-used-badge-${name}`"
                    class="bg-muted/80 text-muted-foreground absolute -top-2 -right-2 rounded-xl border px-2 py-0.5 text-xs drop-shadow-lg"
                >
                    {{ $t('Last used') }}
                </span>
            </div>
        </div>
    </div>
</template>
