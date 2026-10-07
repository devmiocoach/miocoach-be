<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { useAuth } from '@/composables/useAuth';
import ClientLayout from '@/layouts/ClientLayout.vue';

const { user } = useAuth();
</script>

<template>
    <ClientLayout
        :title="$t('My Dashboard')"
        :breadcrumbs="[{ title: $t('My Dashboard') }]"
    >
        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div>
                <h1
                    class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white"
                >
                    {{ $t('My Dashboard') }}
                </h1>
                <p class="text-muted-foreground mt-1">
                    {{ $t('Welcome back, :name!', { name: user?.name ?? '' }) }}
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ $t('Coming Soon') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground">
                        {{
                            $t(
                                'Your personal dashboard is being built. Stay tuned for training plans, progress tracking, and more.',
                            )
                        }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </ClientLayout>
</template>
