<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import Input from '@/components/ui/input/Input.vue';
import CoachLayout from '@/layouts/CoachLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import IconEllipsis from '~icons/heroicons/ellipsis-vertical';
import IconFilter from '~icons/heroicons/funnel';
import IconSearch from '~icons/heroicons/magnifying-glass';
import IconUsers from '~icons/heroicons/user-group';

import type { ClientListItem } from '@modules/Users/resources/js/types/clients';

const props = defineProps<{
    clients: ClientListItem[];
    availableTags: string[];
}>();

const search = ref('');
const status = ref('all');
const selectedTags = ref<string[]>([]);
const expiresWithin = ref('');
const showFilters = ref(false);
const showActionsFor = ref<number | null>(null);

const filteredClients = computed(() => {
    let result = props.clients;

    if (search.value) {
        const q = search.value.toLowerCase();
        result = result.filter(
            (c) =>
                c.name?.toLowerCase().includes(q) ||
                c.email?.toLowerCase().includes(q),
        );
    }

    if (status.value !== 'all') {
        result = result.filter((c) => c.status === status.value);
    }

    if (selectedTags.value.length > 0) {
        result = result.filter((c) =>
            selectedTags.value.every((tag) => (c.tags || []).includes(tag)),
        );
    }

    if (expiresWithin.value) {
        const now = new Date();
        result = result.filter((c) => {
            if (!c.subscription_expires_at) return false;
            const exp = new Date(c.subscription_expires_at);
            if (expiresWithin.value === 'expired') return exp <= now;
            const days = parseInt(expiresWithin.value);
            const limit = new Date(now.getTime() + days * 86400000);
            return exp > now && exp <= limit;
        });
    }

    return result;
});

function toggleTag(tag: string) {
    const idx = selectedTags.value.indexOf(tag);
    if (idx >= 0) {
        selectedTags.value.splice(idx, 1);
    } else {
        selectedTags.value.push(tag);
    }
}

function statusColor(s: string): string {
    switch (s) {
        case 'active':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        case 'suspended':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
        case 'inactive':
            return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
        default:
            return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
    }
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function isExpired(date: string | null): boolean {
    if (!date) return false;
    return new Date(date) < new Date();
}
</script>

<template>
    <CoachLayout
        :title="$t('Clients')"
        :breadcrumbs="[
            { title: $t('Dashboard'), url: route('coach.dashboard') },
            { title: $t('Clients') },
        ]"
    >
        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white"
                    >
                        {{ $t('Clients') }}
                    </h1>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">
                        {{ $t('Manage your athletes and their information') }}
                    </p>
                </div>
            </div>

            <!-- Toolbar -->
            <Card data-testid="clients-toolbar">
                <CardContent class="p-4">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center"
                    >
                        <!-- Search -->
                        <div class="relative flex-1">
                            <IconSearch
                                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400"
                            />
                            <Input
                                v-model="search"
                                :placeholder="$t('Search clients...')"
                                class="pl-9"
                                data-testid="clients-search"
                            />
                        </div>

                        <!-- Filter toggle (mobile) -->
                        <Button
                            variant="outline"
                            size="sm"
                            class="sm:hidden"
                            @click="showFilters = !showFilters"
                        >
                            <IconFilter class="mr-1 size-4" />
                            {{ $t('Filters') }}
                        </Button>

                        <!-- Filters (always visible on desktop) -->
                        <div
                            :class="[
                                'flex flex-col gap-2 sm:flex-row sm:items-center',
                                showFilters ? '' : 'hidden sm:flex',
                            ]"
                        >
                            <!-- Status -->
                            <select
                                v-model="status"
                                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                data-testid="clients-filter-status"
                            >
                                <option value="all">
                                    {{ $t('All statuses') }}
                                </option>
                                <option value="active">
                                    {{ $t('Active') }}
                                </option>
                                <option value="suspended">
                                    {{ $t('Paused') }}
                                </option>
                                <option value="inactive">
                                    {{ $t('Inactive') }}
                                </option>
                            </select>

                            <!-- Expiry -->
                            <select
                                v-model="expiresWithin"
                                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                data-testid="clients-filter-expiry"
                            >
                                <option value="">
                                    {{ $t('All expiry') }}
                                </option>
                                <option value="7">
                                    {{ $t('Expires this week') }}
                                </option>
                                <option value="30">
                                    {{ $t('Expires this month') }}
                                </option>
                                <option value="expired">
                                    {{ $t('Already expired') }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Tag filters -->
                    <div
                        v-if="availableTags.length > 0"
                        class="mt-3 flex flex-wrap gap-1.5"
                    >
                        <button
                            v-for="tag in availableTags"
                            :key="tag"
                            :class="[
                                'rounded-full px-2.5 py-0.5 text-xs font-medium transition-colors',
                                selectedTags.includes(tag)
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',
                            ]"
                            :data-testid="`tag-filter-${tag}`"
                            @click="toggleTag(tag)"
                        >
                            {{ tag }}
                        </button>
                    </div>
                </CardContent>
            </Card>

            <!-- Table -->
            <Card class="flex-1" data-testid="clients-table">
                <div>
                    <table
                        v-if="filteredClients.length > 0"
                        class="w-full text-sm"
                    >
                        <thead>
                            <tr
                                class="border-b border-gray-200 dark:border-gray-800"
                            >
                                <th
                                    class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t('Client') }}
                                </th>
                                <th
                                    class="hidden px-4 py-3 text-left font-medium text-gray-500 md:table-cell dark:text-gray-400"
                                >
                                    {{ $t('Email') }}
                                </th>
                                <th
                                    class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t('Status') }}
                                </th>
                                <th
                                    class="hidden px-4 py-3 text-left font-medium text-gray-500 lg:table-cell dark:text-gray-400"
                                >
                                    {{ $t('Tags') }}
                                </th>
                                <th
                                    class="hidden px-4 py-3 text-left font-medium text-gray-500 lg:table-cell dark:text-gray-400"
                                >
                                    {{ $t('Next Session') }}
                                </th>
                                <th
                                    class="hidden px-4 py-3 text-left font-medium text-gray-500 md:table-cell dark:text-gray-400"
                                >
                                    {{ $t('Expiry') }}
                                </th>
                                <th class="px-4 py-3 text-right">
                                    <span class="sr-only">{{
                                        $t('Actions')
                                    }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="client in filteredClients"
                                :key="client.id"
                                class="border-b border-gray-100 transition-colors hover:bg-gray-50 dark:border-gray-800/50 dark:hover:bg-gray-900/50"
                                :data-testid="`client-row-${client.id}`"
                            >
                                <!-- Name + Avatar -->
                                <td class="px-4 py-3">
                                    <Link
                                        :href="
                                            route(
                                                'coach.clients.show',
                                                client.id,
                                            )
                                        "
                                        class="flex items-center gap-3"
                                    >
                                        <div
                                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-gray-200 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                        >
                                            <img
                                                v-if="client.avatar_url"
                                                :src="client.avatar_url"
                                                :alt="client.name ?? ''"
                                                class="size-9 rounded-full object-cover"
                                            />
                                            <span v-else>{{
                                                (client.name ?? '?')
                                                    .charAt(0)
                                                    .toUpperCase()
                                            }}</span>
                                        </div>
                                        <span
                                            class="font-medium text-gray-900 dark:text-white"
                                        >
                                            {{ client.name ?? '—' }}
                                        </span>
                                    </Link>
                                </td>

                                <!-- Email -->
                                <td
                                    class="hidden px-4 py-3 text-gray-600 md:table-cell dark:text-gray-400"
                                >
                                    {{ client.email ?? '—' }}
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                            statusColor(client.status),
                                        ]"
                                    >
                                        {{
                                            $t(
                                                client.status
                                                    .charAt(0)
                                                    .toUpperCase() +
                                                    client.status.slice(1),
                                            )
                                        }}
                                    </span>
                                </td>

                                <!-- Tags -->
                                <td class="hidden px-4 py-3 lg:table-cell">
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="tag in (
                                                client.tags || []
                                            ).slice(0, 3)"
                                            :key="tag"
                                            variant="secondary"
                                            class="text-xs"
                                        >
                                            {{ tag }}
                                        </Badge>
                                        <Badge
                                            v-if="
                                                (client.tags || []).length > 3
                                            "
                                            variant="outline"
                                            class="text-xs"
                                        >
                                            +{{
                                                (client.tags || []).length - 3
                                            }}
                                        </Badge>
                                    </div>
                                </td>

                                <!-- Next Session -->
                                <td
                                    class="hidden px-4 py-3 text-gray-600 lg:table-cell dark:text-gray-400"
                                >
                                    {{
                                        client.next_booking
                                            ? formatDate(
                                                  client.next_booking.starts_at,
                                              )
                                            : '—'
                                    }}
                                </td>

                                <!-- Expiry -->
                                <td class="hidden px-4 py-3 md:table-cell">
                                    <span
                                        v-if="client.subscription_expires_at"
                                        :class="
                                            isExpired(
                                                client.subscription_expires_at,
                                            )
                                                ? 'text-red-600 dark:text-red-400'
                                                : 'text-gray-600 dark:text-gray-400'
                                        "
                                    >
                                        {{
                                            formatDate(
                                                client.subscription_expires_at,
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-gray-400 dark:text-gray-600"
                                        >—</span
                                    >
                                </td>

                                <!-- Actions -->
                                <td class="px-4 py-3 text-right">
                                    <div class="relative">
                                        <button
                                            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                                            :data-testid="`client-actions-${client.id}`"
                                            @click="
                                                showActionsFor =
                                                    showActionsFor === client.id
                                                        ? null
                                                        : client.id
                                            "
                                        >
                                            <IconEllipsis class="size-5" />
                                        </button>
                                        <div
                                            v-if="showActionsFor === client.id"
                                            class="absolute right-0 z-10 mt-1 w-44 rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                                            @mouseleave="showActionsFor = null"
                                        >
                                            <Link
                                                :href="
                                                    route(
                                                        'coach.clients.show',
                                                        client.id,
                                                    )
                                                "
                                                class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                            >
                                                {{ $t('Open profile') }}
                                            </Link>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty state -->
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-16"
                        data-testid="clients-empty"
                    >
                        <IconUsers
                            class="mb-4 size-12 text-gray-300 dark:text-gray-600"
                        />
                        <p
                            class="text-lg font-medium text-gray-900 dark:text-white"
                        >
                            {{ $t('No clients found') }}
                        </p>
                        <p class="mt-1 text-gray-500 dark:text-gray-400">
                            {{ $t('Try adjusting your search or filters') }}
                        </p>
                    </div>
                </div>

                <!-- Results count -->
                <div
                    v-if="filteredClients.length > 0"
                    class="border-t border-gray-200 px-4 py-3 dark:border-gray-800"
                >
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ filteredClients.length }}
                        {{ $t('of') }} {{ clients.length }}
                        {{ $t('clients') }}
                    </p>
                </div>
            </Card>
        </div>
    </CoachLayout>
</template>
