<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';

import {
    useChatChannel,
    type IncomingMessage,
} from '../composables/useChatChannel';
import ChatComposer from './ChatComposer.vue';
import MessageBubble from './MessageBubble.vue';

type Plan = { id: number; name: string };
const props = defineProps<{
    conversationId: number;
    currentUserId: number;
    plans?: Plan[];
}>();

type Attachment = {
    id: number;
    original_name: string;
    mime: string;
    size_bytes: number;
    download_url: string;
};
type Msg = IncomingMessage & { read: boolean; attachments?: Attachment[] };
const messages = ref<Msg[]>([]);
const typing = ref(false);
const uploadError = ref<string | null>(null);
const scroller = ref<HTMLElement | null>(null);
let typingTimeout: ReturnType<typeof setTimeout> | null = null;

function attachmentsFromMeta(m: IncomingMessage): Attachment[] {
    const meta = m.meta as Record<string, unknown> | null;
    if (!meta || typeof meta.attachment_id !== 'number') {
        return [];
    }
    return [
        {
            id: meta.attachment_id,
            original_name: (meta.original_name as string) ?? '',
            mime: (meta.mime as string) ?? '',
            size_bytes: (meta.size_bytes as number) ?? 0,
            download_url: route('coach.chat.attachments.download', [
                props.conversationId,
                meta.attachment_id,
            ]),
        },
    ];
}

function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );
}

async function load() {
    const response = await fetch(
        route('coach.chat.messages.index', props.conversationId),
        {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
        },
    );
    const data = await response.json();
    messages.value = data.data.map(
        (m: IncomingMessage & { is_read: boolean }) => ({
            ...m,
            read: m.is_read,
        }),
    );
    await scrollToBottom();
    await markRead();
}

async function markRead() {
    await fetch(route('coach.chat.messages.read', props.conversationId), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({}),
    });
}

async function scrollToBottom() {
    await nextTick();
    if (scroller.value) {
        scroller.value.scrollTop = scroller.value.scrollHeight;
    }
}

async function send(body: string) {
    uploadError.value = null;
    try {
        const response = await fetch(
            route('coach.chat.messages.store', props.conversationId),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ body }),
            },
        );

        const data = await response.json();

        if (!response.ok) {
            uploadError.value =
                data?.error?.message ??
                data?.message ??
                'Impossibile inviare il messaggio.';
            return;
        }

        // Server broadcast echoes to others; append locally now for the sender.
        if (!messages.value.find((m) => m.id === data.data.id)) {
            messages.value.push({ ...data.data, read: false });
            await scrollToBottom();
        }
    } catch {
        uploadError.value = 'Impossibile inviare il messaggio.';
    }
}

async function sendWorkoutPlan(planId: number) {
    uploadError.value = null;
    try {
        const response = await fetch(
            route('coach.chat.workout-plan.store', props.conversationId),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ workout_plan_id: planId }),
            },
        );

        const data = await response.json();

        if (!response.ok) {
            uploadError.value =
                data?.error?.message ??
                data?.message ??
                'Impossibile inviare la scheda.';
            return;
        }

        if (!messages.value.find((m) => m.id === data.data.id)) {
            messages.value.push({ ...data.data, read: false });
            await scrollToBottom();
        }
    } catch {
        uploadError.value = 'Impossibile inviare la scheda.';
    }
}

async function uploadAttachment(file: File) {
    uploadError.value = null;
    const form = new FormData();
    form.append('file', file);

    try {
        const response = await fetch(
            route('coach.chat.attachments.store', props.conversationId),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                credentials: 'same-origin',
                body: form,
            },
        );

        const data = await response.json();

        if (!response.ok) {
            uploadError.value =
                data?.error?.message ??
                data?.message ??
                "Impossibile caricare l'allegato.";
            return;
        }

        if (!messages.value.find((m) => m.id === data.data.id)) {
            messages.value.push({ ...data.data, read: false });
            await scrollToBottom();
        }
    } catch {
        uploadError.value = "Impossibile caricare l'allegato.";
    }
}

const { whisperTyping } = useChatChannel(
    props.conversationId,
    (m) => {
        if (m.sender_user_id === props.currentUserId) {
            return;
        }
        messages.value.push({
            ...m,
            read: true,
            attachments: attachmentsFromMeta(m),
        });
        scrollToBottom();
        markRead();
    },
    (payload) => {
        if (payload.reader_user_id !== props.currentUserId) {
            messages.value.forEach((m) => {
                if (payload.message_ids.includes(m.id)) {
                    m.read = true;
                }
            });
        }
    },
    (payload) => {
        if (payload.user_id === props.currentUserId) {
            return;
        }
        typing.value = true;
        if (typingTimeout) {
            clearTimeout(typingTimeout);
        }
        typingTimeout = setTimeout(() => (typing.value = false), 2500);
    },
);

let lastWhisper = 0;
function onTyping() {
    const now = Date.now();
    if (now - lastWhisper > 1500) {
        whisperTyping(props.currentUserId);
        lastWhisper = now;
    }
}

onMounted(load);
watch(() => props.conversationId, load);
</script>

<template>
    <div
        class="flex h-full flex-col bg-white dark:bg-gray-900"
        data-testid="chat-window"
    >
        <div ref="scroller" class="flex-1 space-y-3 overflow-y-auto p-4">
            <MessageBubble
                v-for="m in messages"
                :key="m.id"
                :type="m.type"
                :body="m.body"
                :attachments="m.attachments"
                :meta="m.meta"
                :mine="m.sender_user_id === currentUserId"
                :created-at="m.created_at"
                :read="m.read"
            />
        </div>
        <div
            v-if="typing"
            data-testid="typing-indicator"
            class="px-4 py-1 text-xs text-gray-500 dark:text-gray-400"
        >
            sta scrivendo…
        </div>
        <div
            v-if="uploadError"
            data-testid="chat-attach-error"
            class="px-4 py-1 text-xs text-red-600 dark:text-red-400"
        >
            {{ uploadError }}
        </div>
        <ChatComposer
            :plans="plans"
            @send="send"
            @typing="onTyping"
            @attach="uploadAttachment"
            @plan="sendWorkoutPlan"
        />
    </div>
</template>
