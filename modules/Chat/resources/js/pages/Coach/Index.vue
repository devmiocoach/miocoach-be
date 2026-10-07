<script setup lang="ts">
import CoachLayout from '@/layouts/CoachLayout.vue';
import { ref } from 'vue';

import ChatWindow from '../../components/ChatWindow.vue';
import ConversationList from '../../components/ConversationList.vue';

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

type Plan = {
    id: number;
    name: string;
};

const props = defineProps<{
    conversations: { data: Conversation[] };
    currentUserId: number;
    clients: Client[];
    plans: Plan[];
}>();

const items = ref(props.conversations.data);
const activeId = ref<number | null>(items.value[0]?.id ?? null);

function onStarted(conversation: Conversation) {
    const existing = items.value.find((c) => c.id === conversation.id);
    if (!existing) {
        items.value.unshift(conversation);
    }
    activeId.value = conversation.id;
}
</script>

<template>
    <CoachLayout :title="$t('Chat')" :breadcrumbs="[{ title: $t('Chat') }]">
        <div
            class="flex h-[calc(100vh-4rem)] bg-white dark:bg-gray-900"
            data-testid="chat-page"
        >
            <div class="w-80 shrink-0">
                <ConversationList
                    :conversations="items"
                    :active-id="activeId"
                    :current-user-id="currentUserId"
                    :clients="clients"
                    @select="activeId = $event"
                    @started="onStarted"
                />
            </div>
            <div class="flex-1">
                <ChatWindow
                    v-if="activeId"
                    :key="activeId"
                    :conversation-id="activeId"
                    :current-user-id="currentUserId"
                    :plans="plans"
                />
                <div
                    v-else
                    class="flex h-full items-center justify-center text-gray-500 dark:text-gray-400"
                >
                    Seleziona una conversazione
                </div>
            </div>
        </div>
    </CoachLayout>
</template>
