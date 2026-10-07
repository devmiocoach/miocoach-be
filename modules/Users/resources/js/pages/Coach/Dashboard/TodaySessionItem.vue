<script setup lang="ts">
import Avatar from '@/components/ui/avatar/Avatar.vue';
import AvatarFallback from '@/components/ui/avatar/AvatarFallback.vue';
import AvatarImage from '@/components/ui/avatar/AvatarImage.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import { router } from '@inertiajs/vue3';
import type { TodaySession } from '@modules/Users/resources/js/types/dashboard';
import { ref } from 'vue';

import IconCheck from '~icons/heroicons/check';
import IconPencil from '~icons/heroicons/pencil-square';

const props = defineProps<{
    session: TodaySession;
}>();

const emit = defineEmits<{
    openNotes: [clientId: number, clientName: string];
}>();

const completing = ref(false);

function markCompleted() {
    completing.value = true;
    router.patch(
        route('coach.bookings.complete', props.session.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (completing.value = false),
        },
    );
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('it-IT', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

const clientName =
    props.session.client?.name ?? props.session.guest_name ?? '—';
</script>

<template>
    <div
        class="flex items-center gap-4 rounded-lg border border-gray-200 p-4 dark:border-gray-800"
        :data-testid="`session-item-${session.id}`"
    >
        <!-- Time -->
        <div
            class="min-w-[52px] text-sm font-medium text-gray-900 dark:text-white"
        >
            {{ formatTime(session.starts_at) }}
        </div>

        <!-- Avatar -->
        <Avatar class="size-10">
            <AvatarImage
                v-if="session.client?.avatar"
                :src="session.client.avatar"
                :alt="clientName"
            />
            <AvatarFallback>{{ getInitials(clientName) }}</AvatarFallback>
        </Avatar>

        <!-- Info -->
        <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-gray-900 dark:text-white">
                {{ clientName }}
            </p>
            <Badge
                v-if="session.service_type_label"
                variant="secondary"
                class="mt-0.5"
            >
                {{ session.service_type_label }}
            </Badge>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
            <Button
                v-if="session.status === 'confirmed'"
                size="sm"
                variant="outline"
                :disabled="completing"
                :data-testid="`complete-btn-${session.id}`"
                @click="markCompleted"
            >
                <IconCheck class="size-4" />
                <span class="hidden sm:inline">{{ $t('Mark Completed') }}</span>
            </Button>

            <Badge v-else variant="default" class="text-xs">
                {{ $t('Completed') }}
            </Badge>

            <Button
                v-if="session.client"
                size="sm"
                variant="ghost"
                :data-testid="`notes-btn-${session.id}`"
                @click="emit('openNotes', session.client!.id, clientName)"
            >
                <IconPencil class="size-4" />
                <span class="hidden sm:inline">{{ $t('Notes') }}</span>
            </Button>
        </div>
    </div>
</template>
