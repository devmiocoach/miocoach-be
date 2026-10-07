<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { Link } from '@inertiajs/vue3';

import IconCalendar from '~icons/heroicons/calendar-days';
import IconCurrency from '~icons/heroicons/currency-euro';
import IconExclamation from '~icons/heroicons/exclamation-triangle';
import IconUsers from '~icons/heroicons/user-group';

defineProps<{
    activeClientsCount: number;
    sessionsThisWeek: number;
    revenueThisMonth: number;
    expiringClientsCount: number;
}>();
</script>

<template>
    <div
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
        data-testid="kpi-cards"
    >
        <!-- Active Clients -->
        <Link
            :href="route('coach.dashboard') + '?clients_status=active'"
            class="block"
            data-testid="kpi-active-clients"
        >
            <Card
                class="hover:border-primary/50 cursor-pointer transition-colors"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium">
                        {{ $t('Active Clients') }}
                    </CardTitle>
                    <IconUsers class="text-muted-foreground size-5" />
                </CardHeader>
                <CardContent>
                    <div
                        class="text-3xl font-bold"
                        data-testid="kpi-active-clients-value"
                    >
                        {{ activeClientsCount }}
                    </div>
                </CardContent>
            </Card>
        </Link>

        <!-- Sessions This Week -->
        <Card data-testid="kpi-sessions-week">
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 pb-2"
            >
                <CardTitle class="text-sm font-medium">
                    {{ $t('Sessions This Week') }}
                </CardTitle>
                <IconCalendar class="text-muted-foreground size-5" />
            </CardHeader>
            <CardContent>
                <div
                    class="text-3xl font-bold"
                    data-testid="kpi-sessions-week-value"
                >
                    {{ sessionsThisWeek }}
                </div>
            </CardContent>
        </Card>

        <!-- Revenue This Month -->
        <Card data-testid="kpi-revenue-month">
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 pb-2"
            >
                <CardTitle class="text-sm font-medium">
                    {{ $t('Revenue This Month') }}
                </CardTitle>
                <IconCurrency class="text-muted-foreground size-5" />
            </CardHeader>
            <CardContent>
                <div
                    class="text-3xl font-bold"
                    data-testid="kpi-revenue-month-value"
                >
                    €{{
                        revenueThisMonth.toLocaleString('it-IT', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        })
                    }}
                </div>
            </CardContent>
        </Card>

        <!-- Expiring Clients -->
        <Link
            :href="route('coach.clients.index') + '?expires_within=7'"
            class="block"
            data-testid="kpi-expiring-clients"
        >
            <Card
                :class="[
                    'hover:border-primary/50 cursor-pointer transition-colors',
                    expiringClientsCount > 0 &&
                        'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30',
                ]"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle
                        :class="[
                            'text-sm font-medium',
                            expiringClientsCount > 0
                                ? 'text-amber-900 dark:text-amber-200'
                                : '',
                        ]"
                    >
                        {{ $t('Expiring Within 7 Days') }}
                    </CardTitle>
                    <IconExclamation
                        :class="[
                            'size-5',
                            expiringClientsCount > 0
                                ? 'text-amber-600 dark:text-amber-400'
                                : 'text-muted-foreground',
                        ]"
                    />
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-2">
                        <span
                            :class="[
                                'text-3xl font-bold',
                                expiringClientsCount > 0
                                    ? 'text-amber-900 dark:text-amber-200'
                                    : '',
                            ]"
                            data-testid="kpi-expiring-clients-value"
                        >
                            {{ expiringClientsCount }}
                        </span>
                        <Badge
                            v-if="expiringClientsCount > 0"
                            class="border-amber-400 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-900/50 dark:text-amber-200"
                            data-testid="kpi-expiring-badge"
                        >
                            {{ $t('Attention') }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>
        </Link>
    </div>
</template>
