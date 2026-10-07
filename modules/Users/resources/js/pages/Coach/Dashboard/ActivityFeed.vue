<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import Separator from '@/components/ui/separator/Separator.vue';
import { formatRelativeTime } from '@modules/Users/resources/js/lib/relativeTime';
import type { Activity } from '@modules/Users/resources/js/types/dashboard';
import { type Component } from 'vue';

import IconCalendar from '~icons/heroicons/calendar-days';
import IconCheck from '~icons/heroicons/check-circle';
import IconClipboard from '~icons/heroicons/clipboard-document-check';
import IconCurrency from '~icons/heroicons/currency-euro';
import IconUser from '~icons/heroicons/user-plus';

defineProps<{
    activities: Activity[];
}>();

const eventConfig: Record<
    string,
    { icon: Component; label: string; colorClass: string }
> = {
    new_booking: {
        icon: IconCalendar,
        label: 'New booking',
        colorClass:
            'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
    },
    session_completed: {
        icon: IconCheck,
        label: 'Session completed',
        colorClass:
            'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400',
    },
    payment_received: {
        icon: IconCurrency,
        label: 'Payment received',
        colorClass:
            'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
    },
    new_client: {
        icon: IconUser,
        label: 'New client',
        colorClass:
            'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
    },
    checkin_completed: {
        icon: IconClipboard,
        label: 'Check-in completed',
        colorClass:
            'bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400',
    },
};

function getConfig(eventType: string) {
    return (
        eventConfig[eventType] ?? {
            icon: IconCheck,
            label: eventType,
            colorClass:
                'bg-gray-100 text-gray-600 dark:bg-gray-900/30 dark:text-gray-400',
        }
    );
}

function getDescription(activity: Activity): string {
    const meta = activity.metadata;
    if (!meta) return '';

    const name = (meta.client_name as string) ?? '';

    switch (activity.event_type) {
        case 'new_booking':
            return name;
        case 'session_completed':
            return name;
        case 'payment_received':
            return meta.amount ? `${name} — €${meta.amount}` : name;
        case 'new_client':
            return name;
        case 'checkin_completed':
            return name;
        default:
            return name;
    }
}
</script>

<template>
    <Card class="h-full" data-testid="activity-feed">
        <CardHeader>
            <CardTitle>{{ $t('Recent Activity') }}</CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="activities.length > 0" class="space-y-0">
                <div
                    v-for="(activity, index) in activities"
                    :key="activity.id"
                    :data-testid="`activity-item-${activity.id}`"
                >
                    <div class="flex items-start gap-3 py-3">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full"
                            :class="getConfig(activity.event_type).colorClass"
                        >
                            <component
                                :is="getConfig(activity.event_type).icon"
                                class="size-4"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-sm font-medium text-gray-900 dark:text-white"
                            >
                                {{ $t(getConfig(activity.event_type).label) }}
                            </p>
                            <p
                                v-if="getDescription(activity)"
                                class="truncate text-xs text-gray-600 dark:text-gray-400"
                            >
                                {{ getDescription(activity) }}
                            </p>
                            <p
                                class="mt-0.5 text-xs text-gray-500 dark:text-gray-500"
                            >
                                {{ formatRelativeTime(activity.created_at) }}
                            </p>
                        </div>
                    </div>
                    <Separator v-if="index < activities.length - 1" />
                </div>
            </div>

            <p
                v-else
                class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
            >
                {{ $t('No recent activity') }}
            </p>
        </CardContent>
    </Card>
</template>
