<script setup lang="ts">
import Alert from '@/components/ui/alert/Alert.vue';
import AlertDescription from '@/components/ui/alert/AlertDescription.vue';
import AlertTitle from '@/components/ui/alert/AlertTitle.vue';
import Button from '@/components/ui/button/Button.vue';
import { useDismissedAlerts } from '@modules/Users/resources/js/composables/useDismissedAlerts';

import IconExclamation from '~icons/heroicons/exclamation-triangle';
import IconX from '~icons/heroicons/x-mark';

defineProps<{
    expiringThisWeekCount: number;
    inactiveClientsCount: number;
}>();

const {
    isExpiringDismissed,
    isInactiveDismissed,
    dismissExpiring,
    dismissInactive,
} = useDismissedAlerts();
</script>

<template>
    <div class="space-y-3" data-testid="alert-banners">
        <!-- Expiring clients alert -->
        <Alert
            v-if="expiringThisWeekCount > 0 && !isExpiringDismissed"
            class="border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/50"
            data-testid="alert-expiring"
        >
            <IconExclamation class="text-amber-600 dark:text-amber-400" />
            <div class="flex items-start justify-between gap-2">
                <div>
                    <AlertTitle class="text-amber-900 dark:text-amber-200">
                        {{
                            $t(
                                ':count client subscriptions expiring this week',
                                {
                                    count: expiringThisWeekCount,
                                },
                            )
                        }}
                    </AlertTitle>
                    <AlertDescription
                        class="text-amber-800 dark:text-amber-300"
                    >
                        <a
                            :href="
                                route('coach.clients.index') +
                                '?expires_within=7'
                            "
                            class="font-medium underline underline-offset-4"
                        >
                            {{ $t('View clients') }}
                        </a>
                    </AlertDescription>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-7 shrink-0 text-amber-700 hover:text-amber-900 dark:text-amber-400 dark:hover:text-amber-200"
                    data-testid="dismiss-expiring"
                    @click="dismissExpiring"
                >
                    <IconX class="size-4" />
                </Button>
            </div>
        </Alert>

        <!-- Inactive clients alert -->
        <Alert
            v-if="inactiveClientsCount > 0 && !isInactiveDismissed"
            class="border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/50"
            data-testid="alert-inactive"
        >
            <IconExclamation class="text-red-600 dark:text-red-400" />
            <div class="flex items-start justify-between gap-2">
                <div>
                    <AlertTitle class="text-red-900 dark:text-red-200">
                        {{
                            $t(
                                ':count clients with expired or suspended subscription',
                                {
                                    count: inactiveClientsCount,
                                },
                            )
                        }}
                    </AlertTitle>
                    <AlertDescription class="text-red-800 dark:text-red-300">
                        <a
                            :href="route('coach.clients.index')"
                            class="font-medium underline underline-offset-4"
                        >
                            {{ $t('View clients') }}
                        </a>
                    </AlertDescription>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-7 shrink-0 text-red-700 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200"
                    data-testid="dismiss-inactive"
                    @click="dismissInactive"
                >
                    <IconX class="size-4" />
                </Button>
            </div>
        </Alert>
    </div>
</template>
