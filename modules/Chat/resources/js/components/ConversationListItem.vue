<script setup lang="ts">
import { useEcho } from '@laravel/echo-vue';

import type { IncomingMessage } from '../composables/useChatChannel';

type Conversation = {
    id: number;
    coach: { id: number; name: string; avatar_url: string | null };
    client: { id: number; name: string; avatar_url: string | null };
    last_message: {
        body: string | null;
        type?: string;
        created_at: string;
    } | null;
    last_message_at: string | null;
    unread_count: number;
};

type ReadReceipt = {
    conversation_id: number;
    reader_user_id: number;
    message_ids: number[];
};

const props = defineProps<{
    conversation: Conversation;
    activeId: number | null;
    currentUserId: number;
}>();

const emit = defineEmits<{
    select: [id: number];
}>();

function counterpartName(c: Conversation): string {
    // Coach viewing → show client name.
    return c.client.name ?? c.coach.name ?? '—';
}

// Text messages preview their body; non-text messages (which have no body)
// show a type label so the sidebar never falls back to "Nessun messaggio".
function preview(c: Conversation): string {
    const lm = c.last_message;
    if (!lm) {
        return 'Nessun messaggio';
    }
    if (lm.body) {
        return lm.body;
    }
    switch (lm.type) {
        case 'workout_plan':
            return '📋 Scheda di allenamento';
        case 'image':
            return '📷 Immagine';
        case 'file':
            return '📎 Allegato';
        default:
            return 'Nessun messaggio';
    }
}

// Every listed conversation keeps a light subscription (independent of
// whichever conversation is currently open in ChatWindow) so the sidebar
// preview, ordering, and unread badge stay in sync in realtime.
const channelName = `chat.conversation.${props.conversation.id}`;

useEcho<IncomingMessage>(channelName, '.message.sent', (e) => {
    props.conversation.last_message = {
        body: e.body,
        type: e.type,
        created_at: e.created_at,
    };
    props.conversation.last_message_at = e.created_at;

    if (
        e.sender_user_id !== props.currentUserId &&
        props.conversation.id !== props.activeId
    ) {
        props.conversation.unread_count += 1;
    }
});

useEcho<ReadReceipt>(channelName, '.message.read', (e) => {
    if (e.reader_user_id === props.currentUserId) {
        props.conversation.unread_count = 0;
    }
});
</script>

<template>
    <button
        type="button"
        :data-testid="`conversation-${conversation.id}`"
        class="flex w-full items-center gap-3 border-b border-gray-100 p-3 text-left hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
        :class="{
            'bg-gray-100 dark:bg-gray-800': conversation.id === activeId,
        }"
        @click="emit('select', conversation.id)"
    >
        <img
            v-if="conversation.client.avatar_url"
            :src="conversation.client.avatar_url"
            class="h-10 w-10 rounded-full object-cover"
            alt=""
        />
        <div
            v-else
            class="h-10 w-10 rounded-full bg-gray-300 dark:bg-gray-700"
        ></div>
        <div class="min-w-0 flex-1">
            <p
                class="truncate text-sm font-medium text-gray-900 dark:text-white"
            >
                {{ counterpartName(conversation) }}
            </p>
            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                {{ preview(conversation) }}
            </p>
        </div>
        <span
            v-if="conversation.unread_count > 0"
            :data-testid="`unread-badge-${conversation.id}`"
            class="bg-primary rounded-full px-2 py-0.5 text-xs text-white"
        >
            {{ conversation.unread_count }}
        </span>
    </button>
</template>
