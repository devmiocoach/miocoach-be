<script setup lang="ts">
const props = defineProps<{
    modelValue: number[];
}>();

const emit = defineEmits<{
    'update:modelValue': [number[]];
}>();

const days = [
    { label: 'L', value: 1 },
    { label: 'M', value: 2 },
    { label: 'M', value: 3 },
    { label: 'G', value: 4 },
    { label: 'V', value: 5 },
    { label: 'S', value: 6 },
    { label: 'D', value: 0 },
];

const toggle = (day: number) => {
    const current = [...props.modelValue];
    const idx = current.indexOf(day);
    if (idx >= 0) {
        current.splice(idx, 1);
    } else {
        current.push(day);
    }
    emit('update:modelValue', current);
};
</script>

<template>
    <div class="flex gap-1.5">
        <button
            v-for="day in days"
            :key="day.value"
            type="button"
            class="h-8 w-8 rounded-lg text-xs font-medium transition-colors"
            :class="
                modelValue.includes(day.value)
                    ? 'bg-teal-500 text-white'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700'
            "
            @click="toggle(day.value)"
        >
            {{ day.label }}
        </button>
    </div>
</template>
