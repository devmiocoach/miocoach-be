<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import Separator from '@/components/ui/separator/Separator.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import CoachLayout from '@/layouts/CoachLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { formatRelativeTime } from '@modules/Users/resources/js/lib/relativeTime';
import { defineAsyncComponent, ref } from 'vue';

const ClientProgressTab = defineAsyncComponent(
    () =>
        import('@modules/Progress/resources/js/components/ClientProgressTab.vue'),
);

import IconCalendar from '~icons/heroicons/calendar-days';
import IconHeart from '~icons/heroicons/heart';
import IconShield from '~icons/heroicons/shield-exclamation';
import IconUser from '~icons/heroicons/user';

import type { ClientShowProps } from '@modules/Users/resources/js/types/clients';

const props = defineProps<ClientShowProps>();

const showAnamnesis = ref(false);

const noteForm = useForm({ content: '' });

function submitNote() {
    noteForm.post(route('coach.clients.notes.store', props.client.id), {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}
</script>

<template>
    <CoachLayout
        :title="client.name ?? $t('Client')"
        :breadcrumbs="[
            { title: $t('Dashboard'), url: route('coach.dashboard') },
            { title: $t('Clients'), url: route('coach.clients.index') },
            { title: client.name ?? $t('Client') },
        ]"
    >
        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <!-- Client Header -->
            <div class="flex items-center gap-4" data-testid="client-header">
                <div
                    class="flex size-16 shrink-0 items-center justify-center rounded-full bg-gray-200 text-xl font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                >
                    <img
                        v-if="client.avatar_url"
                        :src="client.avatar_url"
                        :alt="client.name ?? ''"
                        class="size-16 rounded-full object-cover"
                    />
                    <span v-else>{{
                        (client.name ?? '?').charAt(0).toUpperCase()
                    }}</span>
                </div>
                <div>
                    <h1
                        class="text-2xl font-bold text-gray-900 dark:text-white"
                    >
                        {{ client.name ?? '—' }}
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ client.email }}
                    </p>
                    <div class="mt-1 flex items-center gap-2">
                        <span
                            :class="[
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                client.status === 'active'
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                    : client.status === 'suspended'
                                      ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
                                      : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                            ]"
                        >
                            {{
                                $t(
                                    client.status.charAt(0).toUpperCase() +
                                        client.status.slice(1),
                                )
                            }}
                        </span>
                        <Badge
                            v-for="tag in client.tags"
                            :key="tag"
                            variant="secondary"
                            class="text-xs"
                        >
                            {{ tag }}
                        </Badge>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <Tabs default-value="overview" data-testid="client-tabs">
                <TabsList>
                    <TabsTrigger value="overview" data-testid="tab-overview">
                        {{ $t('Overview') }}
                    </TabsTrigger>
                    <TabsTrigger value="workouts" data-testid="tab-workouts">
                        {{ $t('Workouts') }}
                    </TabsTrigger>
                    <TabsTrigger value="progress" data-testid="tab-progress">
                        {{ $t('Progress') }}
                    </TabsTrigger>
                    <TabsTrigger value="bookings" data-testid="tab-bookings">
                        {{ $t('Bookings') }}
                    </TabsTrigger>
                    <TabsTrigger value="documents" data-testid="tab-documents">
                        {{ $t('Documents') }}
                    </TabsTrigger>
                </TabsList>

                <!-- Overview Tab -->
                <TabsContent value="overview" class="mt-6">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <!-- Profile Info -->
                        <Card data-testid="client-profile-card">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <IconUser class="size-5" />
                                    {{ $t('Profile') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div
                                    v-if="client.objective"
                                    class="flex justify-between"
                                >
                                    <span
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                        >{{ $t('Objective') }}</span
                                    >
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                        >{{ client.objective }}</span
                                    >
                                </div>
                                <div
                                    v-if="client.fitness_level"
                                    class="flex justify-between"
                                >
                                    <span
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                        >{{ $t('Fitness Level') }}</span
                                    >
                                    <Badge variant="outline">{{
                                        client.fitness_level
                                    }}</Badge>
                                </div>
                                <div
                                    v-if="client.birth_date"
                                    class="flex justify-between"
                                >
                                    <span
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                        >{{ $t('Birth Date') }}</span
                                    >
                                    <span
                                        class="text-sm text-gray-900 dark:text-white"
                                        >{{
                                            formatDate(client.birth_date)
                                        }}</span
                                    >
                                </div>
                                <div
                                    v-if="client.height_cm || client.weight_kg"
                                    class="flex justify-between"
                                >
                                    <span
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                        >{{ $t('Body') }}</span
                                    >
                                    <span
                                        class="text-sm text-gray-900 dark:text-white"
                                    >
                                        <template v-if="client.height_cm"
                                            >{{ client.height_cm }} cm</template
                                        >
                                        <template
                                            v-if="
                                                client.height_cm &&
                                                client.weight_kg
                                            "
                                        >
                                            ·
                                        </template>
                                        <template v-if="client.weight_kg"
                                            >{{ client.weight_kg }} kg</template
                                        >
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        client.goals && client.goals.length > 0
                                    "
                                >
                                    <span
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                        >{{ $t('Goals') }}</span
                                    >
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <Badge
                                            v-for="goal in client.goals"
                                            :key="goal"
                                            variant="outline"
                                            class="text-xs"
                                            >{{ goal }}</Badge
                                        >
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Subscription -->
                        <Card data-testid="client-subscription-card">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <IconCalendar class="size-5" />
                                    {{ $t('Subscription') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="flex justify-between">
                                    <span
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                        >{{ $t('Expiry') }}</span
                                    >
                                    <span
                                        :class="[
                                            'text-sm font-medium',
                                            client.subscription_expires_at &&
                                            new Date(
                                                client.subscription_expires_at,
                                            ) < new Date()
                                                ? 'text-red-600 dark:text-red-400'
                                                : 'text-gray-900 dark:text-white',
                                        ]"
                                    >
                                        {{
                                            formatDate(
                                                client.subscription_expires_at,
                                            )
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        client.subscription_sessions_remaining !==
                                        null
                                    "
                                    class="flex justify-between"
                                >
                                    <span
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                        >{{ $t('Sessions Remaining') }}</span
                                    >
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                        >{{
                                            client.subscription_sessions_remaining
                                        }}</span
                                    >
                                </div>
                                <div
                                    v-if="client.next_booking"
                                    class="flex justify-between"
                                >
                                    <span
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                        >{{ $t('Next Session') }}</span
                                    >
                                    <span
                                        class="text-sm text-gray-900 dark:text-white"
                                        >{{
                                            formatDate(
                                                client.next_booking.starts_at,
                                            )
                                        }}</span
                                    >
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Anamnesis -->
                        <Card data-testid="client-anamnesis-card">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <IconHeart class="size-5" />
                                    {{ $t('Anamnesis') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div v-if="client.anamnesis" class="space-y-2">
                                    <div
                                        class="flex items-center gap-2 rounded bg-amber-50 px-3 py-1.5 text-xs text-amber-700 dark:bg-amber-900/20 dark:text-amber-400"
                                    >
                                        <IconShield class="size-3.5" />
                                        {{
                                            $t('Sensitive data — access logged')
                                        }}
                                    </div>
                                    <Button
                                        v-if="!showAnamnesis"
                                        variant="outline"
                                        size="sm"
                                        @click="showAnamnesis = true"
                                    >
                                        {{ $t('Show') }}
                                    </Button>
                                    <p
                                        v-else
                                        class="text-sm whitespace-pre-wrap text-gray-700 dark:text-gray-300"
                                    >
                                        {{ client.anamnesis }}
                                    </p>
                                </div>
                                <p
                                    v-else
                                    class="text-sm text-gray-400 dark:text-gray-500"
                                >
                                    {{ $t('No anamnesis recorded') }}
                                </p>
                            </CardContent>
                        </Card>

                        <!-- Notes -->
                        <Card data-testid="client-notes-card">
                            <CardHeader>
                                <CardTitle>{{ $t('Notes') }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div
                                    v-if="notes.length > 0"
                                    class="max-h-64 space-y-3 overflow-y-auto"
                                >
                                    <div
                                        v-for="note in notes"
                                        :key="note.id"
                                        class="rounded-lg border border-gray-100 p-3 dark:border-gray-800"
                                    >
                                        <p
                                            class="text-sm text-gray-700 dark:text-gray-300"
                                        >
                                            {{ note.content }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                                        >
                                            {{ note.coach_name }} ·
                                            {{
                                                formatRelativeTime(
                                                    note.created_at,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <p
                                    v-else
                                    class="mb-3 text-sm text-gray-400 dark:text-gray-500"
                                >
                                    {{ $t('No notes yet') }}
                                </p>

                                <Separator class="my-3" />

                                <!-- Add note form -->
                                <form
                                    class="space-y-2"
                                    data-testid="add-note-form"
                                    @submit.prevent="submitNote"
                                >
                                    <Textarea
                                        v-model="noteForm.content"
                                        :placeholder="$t('Add a note...')"
                                        rows="2"
                                    />
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :disabled="
                                            !noteForm.content.trim() ||
                                            noteForm.processing
                                        "
                                    >
                                        {{ $t('Add Note') }}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Stub tabs -->
                <TabsContent value="workouts" class="mt-6">
                    <Card>
                        <CardContent class="py-16 text-center">
                            <p class="text-gray-400 dark:text-gray-500">
                                {{ $t('Coming soon') }}
                            </p>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="progress" class="mt-6">
                    <Suspense>
                        <ClientProgressTab :client-id="client.id" />
                        <template #fallback>
                            <div
                                class="h-96 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-800"
                            ></div>
                        </template>
                    </Suspense>
                </TabsContent>

                <TabsContent value="bookings" class="mt-6">
                    <Card>
                        <CardContent class="py-16 text-center">
                            <p class="text-gray-400 dark:text-gray-500">
                                {{ $t('Coming soon') }}
                            </p>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="documents" class="mt-6">
                    <Card>
                        <CardContent class="py-16 text-center">
                            <p class="text-gray-400 dark:text-gray-500">
                                {{ $t('Coming soon') }}
                            </p>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </CoachLayout>
</template>
