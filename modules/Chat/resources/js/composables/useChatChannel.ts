import { useEcho } from '@laravel/echo-vue';

export type IncomingMessage = {
    id: number;
    conversation_id: number;
    sender_user_id: number;
    type: string;
    body: string | null;
    meta: Record<string, unknown> | null;
    created_at: string;
};

export function useChatChannel(
    conversationId: number,
    onMessage: (m: IncomingMessage) => void,
    onRead: (payload: {
        reader_user_id: number;
        message_ids: number[];
    }) => void,
    onTyping: (payload: { user_id: number }) => void,
) {
    const channelName = `chat.conversation.${conversationId}`;

    const echo = useEcho<IncomingMessage>(channelName, '.message.sent', (e) =>
        onMessage(e),
    );
    useEcho<{ reader_user_id: number; message_ids: number[] }>(
        channelName,
        '.message.read',
        (e) => onRead(e),
    );

    // Typing via whisper (client-to-client, no server event).
    const whisperTyping = (userId: number) => {
        echo.channel().whisper('typing', { user_id: userId });
    };
    echo.channel().listenForWhisper('typing', (e: { user_id: number }) =>
        onTyping(e),
    );

    return { whisperTyping };
}
