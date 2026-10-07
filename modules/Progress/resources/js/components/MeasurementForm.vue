<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { ref, watch } from 'vue';

import { METRICS, type MetricType } from '../types';

const props = defineProps<{
    open: boolean;
    clientId: number;
    defaultMetric?: MetricType;
    submitting?: boolean;
    serverError?: string | null;
}>();

const emit = defineEmits<{
    close: [];
    saved: [
        data: {
            metric_type: MetricType;
            value: number;
            measured_at: string;
            notes?: string;
        },
    ];
}>();

const metricType = ref<MetricType>(props.defaultMetric ?? 'weight');
const value = ref<string>('');
const measuredAt = ref(new Date().toISOString().split('T')[0]);
const notes = ref('');
const error = ref<string | null>(null);

watch(
    () => props.defaultMetric,
    (newMetric) => {
        if (newMetric) metricType.value = newMetric;
    },
);

function handleSubmit() {
    if (!value.value || !measuredAt.value) return;
    error.value = null;
    emit('saved', {
        metric_type: metricType.value,
        value: parseFloat(value.value),
        measured_at: measuredAt.value,
        notes: notes.value || undefined,
    });
}

function resetForm() {
    value.value = '';
    notes.value = '';
    measuredAt.value = new Date().toISOString().split('T')[0];
    error.value = null;
}

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) resetForm();
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="(v) => !v && emit('close')">
        <DialogContent
            class="sm:max-w-md"
            data-testid="measurement-form-dialog"
        >
            <DialogHeader>
                <DialogTitle>{{ $t('Add measurement') }}</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="handleSubmit">
                <div
                    v-if="error || serverError"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"
                >
                    {{ error || serverError }}
                </div>

                <div>
                    <Label for="metric_type">{{ $t('Metric') }}</Label>
                    <select
                        id="metric_type"
                        v-model="metricType"
                        data-testid="measurement-metric-select"
                        class="mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option
                            v-for="m in METRICS"
                            :key="m.value"
                            :value="m.value"
                        >
                            {{ m.label }} ({{ m.unit }})
                        </option>
                    </select>
                </div>

                <div>
                    <Label for="value">{{ $t('Value') }}</Label>
                    <Input
                        id="value"
                        v-model="value"
                        type="number"
                        step="0.1"
                        min="0"
                        required
                        data-testid="measurement-value-input"
                        class="mt-1"
                    />
                </div>

                <div>
                    <Label for="measured_at">{{ $t('Date') }}</Label>
                    <Input
                        id="measured_at"
                        v-model="measuredAt"
                        type="date"
                        required
                        :max="new Date().toISOString().split('T')[0]"
                        data-testid="measurement-date-input"
                        class="mt-1"
                    />
                </div>

                <div>
                    <Label for="notes">{{ $t('Notes (optional)') }}</Label>
                    <Textarea
                        id="notes"
                        v-model="notes"
                        data-testid="measurement-notes-input"
                        class="mt-1"
                        rows="2"
                    />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('close')"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="submitting || !value"
                        data-testid="measurement-submit-btn"
                    >
                        <span v-if="submitting">...</span>
                        <span v-else>{{ $t('Save') }}</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
