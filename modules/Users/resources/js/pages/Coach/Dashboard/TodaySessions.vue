<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import type { TodaySession } from '@modules/Users/resources/js/types/dashboard';
import { ref } from 'vue';

import EmptyState from './EmptyState.vue';
import SessionNoteDialog from './SessionNoteDialog.vue';
import TodaySessionItem from './TodaySessionItem.vue';

defineProps<{
    sessions: TodaySession[];
}>();

const noteDialogOpen = ref(false);
const noteClientId = ref<number | null>(null);
const noteClientName = ref('');

function openNotes(clientId: number, clientName: string) {
    noteClientId.value = clientId;
    noteClientName.value = clientName;
    noteDialogOpen.value = true;
}

function closeNotes() {
    noteDialogOpen.value = false;
    noteClientId.value = null;
    noteClientName.value = '';
}
</script>

<template>
    <Card class="h-full" data-testid="today-sessions">
        <CardHeader>
            <CardTitle>{{ $t("Today's Sessions") }}</CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="sessions.length > 0" class="space-y-3">
                <TodaySessionItem
                    v-for="session in sessions"
                    :key="session.id"
                    :session="session"
                    @open-notes="openNotes"
                />
            </div>

            <EmptyState v-else />
        </CardContent>

        <SessionNoteDialog
            :open="noteDialogOpen"
            :client-id="noteClientId"
            :client-name="noteClientName"
            @close="closeNotes"
        />
    </Card>
</template>
