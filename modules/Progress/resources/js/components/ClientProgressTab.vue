<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Separator from '@/components/ui/separator/Separator.vue';
import { computed, ref } from 'vue';

import { useMeasurements } from '../composables/useMeasurements';
import { useProgressPhotos } from '../composables/useProgressPhotos';
import type { ChartRange, MetricType, ProgressPhoto } from '../types';

import MeasurementChart from './MeasurementChart.vue';
import MeasurementForm from './MeasurementForm.vue';
import MetricPills from './MetricPills.vue';
import ProgressPhotoGrid from './ProgressPhotoGrid.vue';
import ProgressPhotoLightbox from './ProgressPhotoLightbox.vue';

const props = defineProps<{
    clientId: number;
}>();

const {
    chartData,
    loading: chartLoading,
    selectedMetric,
    selectedRange,
    storeMeasurement,
} = useMeasurements(props.clientId);

const { photos, loading: photosLoading } = useProgressPhotos(props.clientId);

const showForm = ref(false);
const formSubmitting = ref(false);
const lightboxPhoto = ref<ProgressPhoto | null>(null);
const showLightbox = ref(false);

const chartPoints = computed(() => chartData.value?.points ?? []);
const chartUnit = computed(() => chartData.value?.unit ?? '');
const chartGoal = computed(() => chartData.value?.goal?.target_value);
const chartSummary = computed(() => chartData.value?.summary ?? null);

function handleMetricSelect(metric: MetricType) {
    selectedMetric.value = metric;
}

function handleRangeChange(range: ChartRange) {
    selectedRange.value = range;
}

const formError = ref<string | null>(null);

async function handleMeasurementSaved(data: {
    metric_type: MetricType;
    value: number;
    measured_at: string;
    notes?: string;
}) {
    formSubmitting.value = true;
    formError.value = null;
    try {
        await storeMeasurement(data);
        showForm.value = false;
    } catch (e: unknown) {
        formError.value =
            e instanceof Error ? e.message : 'Errore durante il salvataggio.';
    } finally {
        formSubmitting.value = false;
    }
}

function handlePhotoView(photo: ProgressPhoto) {
    lightboxPhoto.value = photo;
    showLightbox.value = true;
}
</script>

<template>
    <div class="space-y-6" data-testid="progress-tab">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $t('Progress') }}
            </h3>
            <Button
                size="sm"
                data-testid="add-measurement-btn"
                @click="showForm = true"
            >
                + {{ $t('Measurement') }}
            </Button>
        </div>

        <!-- Metric pills -->
        <MetricPills :selected="selectedMetric" @select="handleMetricSelect" />

        <!-- Unified chart card -->
        <MeasurementChart
            :points="chartPoints"
            :unit="chartUnit"
            :goal-value="chartGoal"
            :loading="chartLoading"
            :selected-range="selectedRange"
            :summary="chartSummary"
            @range-change="handleRangeChange"
        />

        <!-- Photo gallery -->
        <Separator />

        <div class="space-y-4">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                {{ $t('Progress photos') }}
            </h4>
            <ProgressPhotoGrid
                :photos="photos"
                :loading="photosLoading"
                @view="handlePhotoView"
            />
        </div>

        <!-- Dialogs -->
        <MeasurementForm
            :open="showForm"
            :client-id="clientId"
            :default-metric="selectedMetric"
            :submitting="formSubmitting"
            :server-error="formError"
            @close="showForm = false"
            @saved="handleMeasurementSaved"
        />

        <ProgressPhotoLightbox
            :photo="lightboxPhoto"
            :open="showLightbox"
            @close="showLightbox = false"
        />
    </div>
</template>
