<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import { Link } from '@inertiajs/vue3';
import IconArrowLeft from '~icons/heroicons/arrow-left';
import IconDuplicate from '~icons/heroicons/document-duplicate';

defineProps<{
    isDirty: boolean;
    saveState: 'idle' | 'saving' | 'error';
    lastSavedAt: Date | null;
    canDuplicate: boolean;
}>();

defineEmits<{ duplicate: [] }>();

const formatTime = (date: Date | null): string =>
    date
        ? date.toLocaleTimeString('it-IT', {
              hour: '2-digit',
              minute: '2-digit',
          })
        : '';
</script>

<template>
    <div
        class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900"
    >
        <Link
            :href="route('coach.plans.index')"
            class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            data-testid="back-to-plans"
        >
            <IconArrowLeft class="h-4 w-4" />
            {{ $t('Back to plans') }}
        </Link>

        <div class="flex items-center gap-3">
            <Badge
                v-if="saveState === 'error'"
                variant="destructive"
                data-testid="save-badge"
            >
                {{ $t('Error, retrying') }}
            </Badge>
            <Badge
                v-else-if="saveState === 'saving'"
                variant="secondary"
                data-testid="save-badge"
            >
                {{ $t('Saving…') }}
            </Badge>
            <Badge
                v-else-if="isDirty"
                variant="outline"
                data-testid="save-badge"
            >
                {{ $t('Draft') }}
            </Badge>
            <Badge
                v-else-if="lastSavedAt"
                variant="secondary"
                data-testid="save-badge"
            >
                {{ $t('Saved') }} {{ formatTime(lastSavedAt) }}
            </Badge>

            <Button
                v-if="canDuplicate"
                variant="outline"
                size="sm"
                data-testid="duplicate-plan-btn"
                @click="$emit('duplicate')"
            >
                <IconDuplicate class="mr-2 h-4 w-4" />
                {{ $t('Duplicate') }}
            </Button>
        </div>
    </div>
</template>
