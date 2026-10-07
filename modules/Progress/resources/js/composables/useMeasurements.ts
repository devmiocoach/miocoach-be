import { useHttp } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import type { ChartData, ChartRange, MetricType } from '../types';

export function useMeasurements(clientId: number) {
    const chartData = ref<ChartData | null>(null);
    const loading = ref(false);
    const selectedMetric = ref<MetricType>('weight');
    const selectedRange = ref<ChartRange>('3M');

    async function fetchChart() {
        loading.value = true;
        try {
            const http = useHttp({});
            const response = await http.get(
                route('coach.clients.progress.measurements.chart', {
                    client: clientId,
                }) +
                    `?metric_type=${selectedMetric.value}&range=${selectedRange.value}`,
            );
            chartData.value = response?.data ?? null;
        } catch {
            chartData.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function storeMeasurement(data: {
        metric_type: MetricType;
        value: number;
        measured_at: string;
        notes?: string;
    }) {
        const http = useHttp(data);
        try {
            await http.post(
                route('coach.clients.progress.measurements.store', {
                    client: clientId,
                }),
            );
        } catch {
            if (http.hasErrors) {
                const msgs = Object.values(http.errors).flat();
                throw new Error(msgs.join(' '));
            }
            throw new Error('Errore durante il salvataggio.');
        }
        await fetchChart();
    }

    async function updateMeasurement(
        measurementId: number,
        data: Partial<{
            metric_type: MetricType;
            value: number;
            measured_at: string;
            notes: string;
        }>,
    ) {
        const http = useHttp(data);
        await http.put(
            route('coach.clients.progress.measurements.update', {
                client: clientId,
                measurement: measurementId,
            }),
        );
        await fetchChart();
    }

    async function deleteMeasurement(measurementId: number) {
        const http = useHttp({});
        await http.delete(
            route('coach.clients.progress.measurements.destroy', {
                client: clientId,
                measurement: measurementId,
            }),
        );
        await fetchChart();
    }

    watch([selectedMetric, selectedRange], () => fetchChart(), {
        immediate: true,
    });

    return {
        chartData,
        loading,
        selectedMetric,
        selectedRange,
        fetchChart,
        storeMeasurement,
        updateMeasurement,
        deleteMeasurement,
    };
}
