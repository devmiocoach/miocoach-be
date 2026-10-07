<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{
    min?: number;
    max?: number;
    modelValue: [number | null, number | null];
}>();

const emit = defineEmits<{
    'update:modelValue': [[number | null, number | null]];
}>();

const rangeMin = props.min ?? 0;
const rangeMax = props.max ?? 200;

const localMin = ref(props.modelValue[0] ?? rangeMin);
const localMax = ref(props.modelValue[1] ?? rangeMax);

watch(
    () => props.modelValue,
    (val) => {
        localMin.value = val[0] ?? rangeMin;
        localMax.value = val[1] ?? rangeMax;
    },
);

const onMinInput = (e: Event) => {
    const val = Number((e.target as HTMLInputElement).value);
    localMin.value = Math.min(val, localMax.value - 5);
    emit('update:modelValue', [
        localMin.value === rangeMin ? null : localMin.value,
        localMax.value === rangeMax ? null : localMax.value,
    ]);
};

const onMaxInput = (e: Event) => {
    const val = Number((e.target as HTMLInputElement).value);
    localMax.value = Math.max(val, localMin.value + 5);
    emit('update:modelValue', [
        localMin.value === rangeMin ? null : localMin.value,
        localMax.value === rangeMax ? null : localMax.value,
    ]);
};
</script>

<template>
    <div class="space-y-2">
        <div class="relative h-2 rounded-full bg-gray-200 dark:bg-gray-700">
            <div
                class="absolute h-full rounded-full bg-teal-500"
                :style="{
                    left: `${((localMin - rangeMin) / (rangeMax - rangeMin)) * 100}%`,
                    right: `${100 - ((localMax - rangeMin) / (rangeMax - rangeMin)) * 100}%`,
                }"
            />
        </div>
        <div class="relative">
            <input
                type="range"
                :min="rangeMin"
                :max="rangeMax"
                :value="localMin"
                class="pointer-events-none absolute h-2 w-full appearance-none bg-transparent [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-teal-500"
                @input="onMinInput"
            />
            <input
                type="range"
                :min="rangeMin"
                :max="rangeMax"
                :value="localMax"
                class="pointer-events-none absolute h-2 w-full appearance-none bg-transparent [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-teal-500"
                @input="onMaxInput"
            />
        </div>
        <div
            class="flex justify-between px-1 pt-3 text-xs text-gray-500 dark:text-gray-400"
        >
            <template
                v-if="
                    props.modelValue[0] === null && props.modelValue[1] === null
                "
            >
                <span
                    class="w-full text-center font-semibold text-gray-700 dark:text-gray-300"
                >
                    Qualsiasi
                </span>
            </template>
            <template v-else>
                <span>{{ localMin }}€</span>
                <span>{{ localMax }}€</span>
            </template>
        </div>
    </div>
</template>
