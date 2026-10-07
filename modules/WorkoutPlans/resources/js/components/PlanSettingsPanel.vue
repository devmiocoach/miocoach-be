<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import type { BuilderPlan } from '../composables/usePlanBuilder';

const props = defineProps<{
    plan: BuilderPlan;
    clients: Array<{ id: number; name: string }>;
    selectedClientIds: number[];
}>();

const emit = defineEmits<{
    'update:selectedClientIds': [ids: number[]];
    save: [];
    saveAndAssign: [];
    commit: [];
}>();

function toggleClient(id: number): void {
    const next = props.selectedClientIds.includes(id)
        ? props.selectedClientIds.filter((clientId) => clientId !== id)
        : [...props.selectedClientIds, id];

    emit('update:selectedClientIds', next);
}
</script>

<template>
    <div class="flex h-full flex-col gap-4">
        <label class="block text-sm text-gray-600 dark:text-gray-400">
            {{ $t('Plan name') }}
            <Input
                v-model="plan.name"
                data-testid="field-plan-name"
                @blur="emit('commit')"
            />
        </label>

        <label class="block text-sm text-gray-600 dark:text-gray-400">
            {{ $t('Description') }}
            <textarea
                v-model="plan.description"
                rows="4"
                class="mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                data-testid="field-plan-description"
                @blur="emit('commit')"
            />
        </label>

        <label
            class="flex items-center gap-2 text-sm text-gray-900 dark:text-white"
        >
            <input
                v-model="plan.isTemplate"
                type="checkbox"
                data-testid="field-is-template"
                @change="emit('commit')"
            />
            {{ $t('Save as template') }}
        </label>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">
                {{ $t('Assign to athletes') }}
            </p>
            <label
                v-for="client in clients"
                :key="client.id"
                class="flex items-center gap-2 py-1 text-sm text-gray-900 dark:text-white"
            >
                <input
                    type="checkbox"
                    :checked="selectedClientIds.includes(client.id)"
                    :data-testid="`assign-client-${client.id}`"
                    @change="toggleClient(client.id)"
                />
                {{ client.name }}
            </label>
            <p
                v-if="clients.length === 0"
                class="text-sm text-gray-600 dark:text-gray-400"
            >
                {{ $t('No athletes yet.') }}
            </p>
        </div>

        <div class="flex flex-col gap-2">
            <Button data-testid="save-plan-btn" @click="emit('save')">{{
                $t('Save')
            }}</Button>
            <Button
                variant="outline"
                :disabled="selectedClientIds.length === 0"
                data-testid="save-assign-btn"
                @click="emit('saveAndAssign')"
            >
                {{ $t('Save and assign') }}
            </Button>
        </div>
    </div>
</template>
