<script setup lang="ts">
const props = defineProps<{
    modelValue: string[];
}>();

const emit = defineEmits<{
    'update:modelValue': [string[]];
}>();

const slots = [
    { label: 'Mattina', value: 'mattina' },
    { label: 'Pomeriggio', value: 'pomeriggio' },
    { label: 'Sera', value: 'sera' },
];

const toggle = (slot: string) => {
    const current = [...props.modelValue];
    const idx = current.indexOf(slot);
    if (idx >= 0) {
        current.splice(idx, 1);
    } else {
        current.push(slot);
    }
    emit('update:modelValue', current);
};
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="slot in slots"
            :key="slot.value"
            type="button"
            class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
            :class="
                modelValue.includes(slot.value)
                    ? 'bg-teal-500 text-white'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700'
            "
            @click="toggle(slot.value)"
        >
            {{ slot.label }}
        </button>
    </div>
</template>
