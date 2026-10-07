<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import CoachLayout from '@/layouts/CoachLayout.vue';
import { Link } from '@inertiajs/vue3';

import IconClipboardDocumentList from '~icons/heroicons/clipboard-document-list';
import IconPlus from '~icons/heroicons/plus';
import IconRectangleStack from '~icons/heroicons/rectangle-stack';

type PlanListItem = {
    id: number;
    name: string;
    goal: string | null;
    isTemplate: boolean;
    daysCount: number;
    updatedAt: string | null;
};

defineProps<{
    plans: PlanListItem[];
    stats: { plans: number; exercises: number };
}>();

const title = 'Workout Plans';

const formatDate = (iso: string | null): string =>
    iso
        ? new Date(iso).toLocaleDateString('it-IT', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          })
        : '—';
</script>

<template>
    <CoachLayout :title="title" :breadcrumbs="[{ title }]">
        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div class="flex items-start justify-between">
                <div>
                    <h1
                        class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white"
                    >
                        {{ $t('Workout Plans') }}
                    </h1>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">
                        {{
                            $t(
                                'Build and assign training plans to your athletes.',
                            )
                        }}
                    </p>
                </div>
                <Link :href="route('coach.plans.create')">
                    <Button data-testid="new-plan-btn">
                        <IconPlus class="mr-2 h-4 w-4" />
                        {{ $t('New plan') }}
                    </Button>
                </Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <Card data-testid="stat-plans">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle class="text-sm font-medium">{{
                            $t('Plans')
                        }}</CardTitle>
                        <IconClipboardDocumentList
                            class="h-4 w-4 text-gray-400 dark:text-gray-500"
                        />
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-3xl font-bold text-gray-900 dark:text-white"
                        >
                            {{ stats.plans }}
                        </p>
                    </CardContent>
                </Card>
                <Link
                    :href="route('coach.exercises.index')"
                    data-testid="stat-exercises"
                >
                    <Card
                        class="group h-full transition hover:border-gray-300 dark:hover:border-gray-700"
                    >
                        <CardHeader
                            class="flex flex-row items-center justify-between pb-2"
                        >
                            <CardTitle class="text-sm font-medium">{{
                                $t('Exercises')
                            }}</CardTitle>
                            <IconRectangleStack
                                class="h-4 w-4 text-gray-400 dark:text-gray-500"
                            />
                        </CardHeader>
                        <CardContent
                            class="flex items-end justify-between gap-2"
                        >
                            <p
                                class="text-3xl font-bold text-gray-900 dark:text-white"
                            >
                                {{ stats.exercises }}
                            </p>
                            <span
                                class="text-primary/70 text-sm font-medium underline-offset-4 group-hover:underline"
                            >
                                {{ $t('Manage exercises') }}
                            </span>
                        </CardContent>
                    </Card>
                </Link>
            </div>

            <Card>
                <CardContent class="p-0">
                    <table class="w-full text-sm" data-testid="plans-table">
                        <thead
                            class="border-b border-gray-200 dark:border-gray-800"
                        >
                            <tr
                                class="text-left text-gray-600 dark:text-gray-400"
                            >
                                <th class="px-4 py-3 font-medium">
                                    {{ $t('Name') }}
                                </th>
                                <th
                                    class="hidden px-4 py-3 font-medium md:table-cell"
                                >
                                    {{ $t('Goal') }}
                                </th>
                                <th
                                    class="hidden px-4 py-3 font-medium md:table-cell"
                                >
                                    {{ $t('Days') }}
                                </th>
                                <th
                                    class="hidden px-4 py-3 font-medium lg:table-cell"
                                >
                                    {{ $t('Updated') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="plan in plans"
                                :key="plan.id"
                                class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50"
                                :data-testid="`plan-row-${plan.id}`"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="
                                            route('coach.plans.edit', plan.id)
                                        "
                                        class="text-primary/70 font-medium underline-offset-4 hover:underline"
                                        :data-testid="`open-plan-${plan.id}`"
                                    >
                                        {{ plan.name }}
                                    </Link>
                                    <Badge
                                        v-if="plan.isTemplate"
                                        variant="outline"
                                        class="ml-2"
                                    >
                                        {{ $t('Template') }}
                                    </Badge>
                                </td>
                                <td
                                    class="hidden px-4 py-3 text-gray-600 md:table-cell dark:text-gray-400"
                                >
                                    {{ plan.goal ?? '—' }}
                                </td>
                                <td
                                    class="hidden px-4 py-3 text-gray-600 md:table-cell dark:text-gray-400"
                                >
                                    {{ plan.daysCount }}
                                </td>
                                <td
                                    class="hidden px-4 py-3 text-gray-600 lg:table-cell dark:text-gray-400"
                                >
                                    {{ formatDate(plan.updatedAt) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p
                        v-if="plans.length === 0"
                        class="py-16 text-center text-gray-600 dark:text-gray-400"
                        data-testid="plans-empty"
                    >
                        {{ $t('No plans yet. Create your first one.') }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </CoachLayout>
</template>
