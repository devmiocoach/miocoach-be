<script setup lang="ts">
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartCrosshair,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
} from '@/components/ui/chart';
import { CurveType } from '@unovis/ts';
import { VisArea, VisAxis, VisPlotline, VisXYContainer } from '@unovis/vue';
import { computed } from 'vue';

import type { ChartPoint, ChartRange, ChartSummary } from '../types';
import { RANGES } from '../types';

const props = defineProps<{
    points: ChartPoint[];
    unit: string;
    goalValue?: number;
    loading: boolean;
    selectedRange: ChartRange;
    summary: ChartSummary | null;
}>();

const emit = defineEmits<{
    'range-change': [range: ChartRange];
}>();

interface DataPoint {
    date: Date;
    value: number;
}

const chartConfig = computed<ChartConfig>(() => ({
    value: {
        label: props.unit || 'Valore',
        color: 'var(--chart-1)',
    },
}));

const chartData = computed<DataPoint[]>(() =>
    props.points.map((p) => ({
        date: new Date(p.date),
        value: p.value,
    })),
);

const tickValues = computed(() => {
    const data = chartData.value;
    if (data.length <= 6) return data.map((d) => d.date);
    const step = Math.ceil(data.length / 6);
    return data.filter((_, i) => i % step === 0).map((d) => d.date);
});

const yDomain = computed<[number, number | undefined]>(() => {
    if (chartData.value.length === 0) return [0, undefined];
    const values = chartData.value.map((d) => d.value);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const padding = (max - min) * 0.15 || 1;
    return [Math.max(0, min - padding), max + padding];
});

const deltaClass = computed(() => {
    if (!props.summary) return '';
    if (props.summary.delta < 0)
        return 'text-emerald-600 dark:text-emerald-400';
    if (props.summary.delta > 0) return 'text-rose-600 dark:text-rose-400';
    return 'text-muted-foreground';
});

const deltaDisplay = computed(() => {
    if (!props.summary) return '';
    const sign = props.summary.delta > 0 ? '+' : '';
    return `${sign}${props.summary.delta} ${props.unit} (${sign}${props.summary.delta_percent}%)`;
});
</script>

<template>
    <Card data-testid="measurement-chart">
        <CardHeader
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="space-y-1">
                <CardTitle class="text-balance">
                    {{ $t('Progress') }}
                </CardTitle>
                <p
                    v-if="summary"
                    class="text-2xl font-semibold tracking-tight tabular-nums"
                >
                    {{ summary.last_value }} {{ unit }}
                </p>
            </div>
            <div
                class="border-border bg-muted/50 inline-flex rounded-lg border p-0.5"
                data-testid="range-selector"
            >
                <button
                    v-for="r in RANGES"
                    :key="r.value"
                    :data-testid="`range-${r.value}`"
                    class="rounded-md px-3 py-1 text-xs font-medium transition-colors"
                    :class="
                        selectedRange === r.value
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="emit('range-change', r.value)"
                >
                    {{ r.label }}
                </button>
            </div>
        </CardHeader>

        <CardContent>
            <div class="h-60 w-full md:h-72">
                <div
                    v-if="loading"
                    class="flex h-full items-center justify-center"
                >
                    <div
                        class="border-muted-foreground/30 border-t-primary h-6 w-6 animate-spin rounded-full border-2"
                    ></div>
                </div>
                <div
                    v-else-if="points.length === 0"
                    class="flex h-full items-center justify-center"
                >
                    <p class="text-muted-foreground text-sm">
                        {{ $t('No measurements available') }}
                    </p>
                </div>
                <ChartContainer
                    v-else
                    :config="chartConfig"
                    class="h-full w-full"
                >
                    <VisXYContainer
                        :data="chartData"
                        :margin="{ left: 0, top: 12, right: 12, bottom: 0 }"
                        :y-domain="yDomain"
                        :svg-defs="`
                            <linearGradient id='area-gradient' x1='0' y1='0' x2='0' y2='1'>
                                <stop offset='0%' stop-color='var(--color-value)' stop-opacity='0.3' />
                                <stop offset='95%' stop-color='var(--color-value)' stop-opacity='0.02' />
                            </linearGradient>
                        `"
                    >
                        <VisArea
                            :x="(d: DataPoint) => d.date"
                            :y="(d: DataPoint) => d.value"
                            color="url(#area-gradient)"
                            :line="true"
                            line-color="var(--color-value)"
                            :line-width="2.5"
                            :curve-type="CurveType.MonotoneX"
                            :opacity="1"
                        />
                        <VisAxis
                            type="x"
                            :x="(d: DataPoint) => d.date"
                            :tick-line="false"
                            :domain-line="false"
                            :grid-line="false"
                            :num-ticks="6"
                            :tick-format="
                                (d: number) => {
                                    const date = new Date(d);
                                    return date.toLocaleDateString('it-IT', {
                                        day: '2-digit',
                                        month: 'short',
                                    });
                                }
                            "
                            :tick-values="tickValues"
                        />
                        <VisAxis
                            type="y"
                            :num-ticks="4"
                            :tick-line="false"
                            :domain-line="false"
                            :grid-line="true"
                            :tick-format="(d: number) => `${d.toFixed(1)}`"
                        />
                        <VisPlotline
                            v-if="goalValue"
                            :value="goalValue"
                            color="#10b981"
                            :line-style="[6, 4]"
                            :label-text="`Goal: ${goalValue} ${unit}`"
                            label-position="end"
                            label-color="#10b981"
                        />
                        <ChartTooltip />
                        <ChartCrosshair
                            :template="
                                componentToString(
                                    chartConfig,
                                    ChartTooltipContent,
                                    { hideLabel: true },
                                )
                            "
                            :color="chartConfig.value.color"
                        />
                    </VisXYContainer>
                </ChartContainer>
            </div>
        </CardContent>

        <CardFooter
            v-if="summary"
            class="text-muted-foreground flex items-center justify-between text-xs"
        >
            <div class="flex items-center gap-1">
                <svg
                    v-if="summary.delta !== 0"
                    class="h-3 w-3"
                    :class="deltaClass"
                    viewBox="0 0 12 12"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path v-if="summary.delta < 0" d="M6 9V3M6 3L3 6M6 3L9 6" />
                    <path v-else d="M6 3V9M6 9L3 6M6 9L9 6" />
                </svg>
                <span :class="deltaClass" class="font-medium tabular-nums">
                    {{ deltaDisplay }}
                </span>
                <span>{{ $t('in this period') }}</span>
            </div>
            <div class="flex gap-3 tabular-nums">
                <span
                    >Min:
                    <strong class="text-foreground">{{
                        summary.min
                    }}</strong></span
                >
                <span
                    >Max:
                    <strong class="text-foreground">{{
                        summary.max
                    }}</strong></span
                >
            </div>
        </CardFooter>
    </Card>
</template>
