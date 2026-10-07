<script setup lang="ts">
import CoachLayout from '@/layouts/CoachLayout.vue';
import { useDashboardPolling } from '@modules/Users/resources/js/composables/useDashboardPolling';
import type { DashboardProps } from '@modules/Users/resources/js/types/dashboard';

import ActivityFeed from './Dashboard/ActivityFeed.vue';
import AlertBanners from './Dashboard/AlertBanners.vue';
import KpiCards from './Dashboard/KpiCards.vue';
import TodaySessions from './Dashboard/TodaySessions.vue';

defineProps<DashboardProps>();

useDashboardPolling();
</script>

<template>
    <CoachLayout
        :title="$t('Dashboard')"
        :breadcrumbs="[{ title: $t('Dashboard') }]"
    >
        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <!-- Header -->
            <div>
                <h1
                    class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white"
                >
                    {{ $t('Dashboard') }}
                </h1>
                <p class="mt-1 text-gray-600 dark:text-gray-400">
                    {{ $t('Overview of your coaching activity') }}
                </p>
            </div>

            <!-- Alert Banners -->
            <AlertBanners
                :expiring-this-week-count="expiringThisWeekCount"
                :inactive-clients-count="inactiveClientsCount"
            />

            <!-- KPI Cards -->
            <KpiCards
                :active-clients-count="activeClientsCount"
                :sessions-this-week="sessionsThisWeek"
                :revenue-this-month="revenueThisMonth"
                :expiring-clients-count="expiringClientsCount"
            />

            <!-- Main Content Grid -->
            <div class="grid gap-6 lg:grid-cols-7">
                <!-- Today's Sessions -->
                <div class="lg:col-span-4">
                    <TodaySessions :sessions="todaySessions" />
                </div>

                <!-- Activity Feed -->
                <div class="lg:col-span-3">
                    <ActivityFeed :activities="recentActivities" />
                </div>
            </div>
        </div>
    </CoachLayout>
</template>
