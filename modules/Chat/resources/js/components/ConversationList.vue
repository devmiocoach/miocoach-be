<script setup lang="ts">
import { computed, ref } from 'vue';

import ConversationListItem from './ConversationListItem.vue';

type Conversation = {
    id: number;
    coach: { id: number; name: string; avatar_url: string | null };
    client: { id: number; name: string; avatar_url: string | null };
    last_message: { body: string | null; created_at: string } | null;
    last_message_at: string | null;
    unread_count: number;
};

type Client = {
    id: number;
    name: string;
    avatar: string | null;
};

const props = defineProps<{
    conversations: Conversation[];
    activeId: number | null;
    currentUserId: number;
    clients: Client[];
}>();
const emit = defineEmits<{
    select: [id: number];
    started: [conversation: Conversation];
}>();

const pickerOpen = ref(false);
const starting = ref<number | null>(null);

// Conversations are mutated in place (by ConversationListItem's realtime
// subscription) rather than replaced, so re-sorting here reactively keeps
// the list ordered by last_message_at desc without any manual splicing.
const sortedConversations = computed(() =>
    [...props.conversations].sort((a, b) =>
        (b.last_message_at ?? '').localeCompare(a.last_message_at ?? ''),
    ),
);

function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );
}

function togglePicker() {
    pickerOpen.value = !pickerOpen.value;
}

async function startConversation(clientId: number) {
    starting.value = clientId;
    try {
        const response = await fetch(route('coach.chat.conversations.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ client_id: clientId }),
        });
        const { data } = await response.json();
        emit('started', data as Conversation);
        pickerOpen.value = false;
    } finally {
        starting.value = null;
    }
}
</script>

<template>
    <div
        class="flex h-full flex-col overflow-y-auto border-r border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="border-b border-gray-100 p-3 dark:border-gray-800">
            <button
                type="button"
                data-testid="new-conversation-btn"
                class="bg-primary hover:bg-primary/90 w-full rounded-md px-3 py-2 text-sm font-medium text-white transition-colors"
                @click="togglePicker"
            >
                {{ pickerOpen ? $t('Close') : $t('New conversation') }}
            </button>

            <div
                v-if="pickerOpen"
                data-testid="client-picker"
                class="mt-2 max-h-64 overflow-y-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
            >
                <p
                    v-if="clients.length === 0"
                    class="p-3 text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ $t('You have no clients yet.') }}
                </p>
                <button
                    v-for="client in clients"
                    :key="client.id"
                    type="button"
                    :data-testid="`start-conversation-${client.id}`"
                    :disabled="starting === client.id"
                    class="flex w-full items-center gap-3 border-b border-gray-100 p-2 text-left last:border-b-0 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:hover:bg-gray-700"
                    @click="startConversation(client.id)"
                >
                    <img
                        v-if="client.avatar"
                        :src="client.avatar"
                        class="h-8 w-8 rounded-full object-cover"
                        alt=""
                    />
                    <div
                        v-else
                        class="h-8 w-8 rounded-full bg-gray-300 dark:bg-gray-700"
                    ></div>
                    <span
                        class="truncate text-sm font-medium text-gray-900 dark:text-white"
                    >
                        {{ client.name }}
                    </span>
                </button>
            </div>
        </div>

        <div
            v-if="conversations.length === 0"
            data-testid="empty-conversations"
            class="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center"
        >
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $t('No conversations yet.') }}
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500">
                {{
                    $t(
                        'Tap "New conversation" above to message one of your clients.',
                    )
                }}
            </p>
        </div>

        <ConversationListItem
            v-for="c in sortedConversations"
            :key="c.id"
            :conversation="c"
            :active-id="activeId"
            :current-user-id="currentUserId"
            @select="emit('select', $event)"
        />
    </div>
</template>
