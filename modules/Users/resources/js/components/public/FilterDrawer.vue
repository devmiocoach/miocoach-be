<script setup lang="ts">
import { X } from 'lucide-vue-next';
import type { SearchFilters } from '../../types/public';
import FilterSidebar from './FilterSidebar.vue';

defineProps<{
    open: boolean;
    filters: SearchFilters;
}>();

const emit = defineEmits<{
    'update:open': [boolean];
    'update:filters': [SearchFilters];
}>();
</script>

<template>
    <Teleport to="body">
        <Transition name="drawer">
            <div v-if="open" class="fixed inset-0 z-50 flex">
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/50"
                    @click="emit('update:open', false)"
                />
                <!-- Panel -->
                <div
                    class="relative ml-auto h-full w-80 max-w-[85vw] overflow-y-auto bg-white p-6 dark:bg-gray-900"
                >
                    <div class="mb-6 flex items-center justify-between">
                        <h2
                            class="text-lg font-bold text-gray-900 dark:text-white"
                        >
                            Filtri
                        </h2>
                        <button
                            type="button"
                            class="rounded-lg p-2 hover:bg-gray-100 dark:hover:bg-gray-800"
                            @click="emit('update:open', false)"
                        >
                            <X
                                :size="20"
                                class="text-gray-600 dark:text-gray-400"
                            />
                        </button>
                    </div>
                    <FilterSidebar
                        :filters="filters"
                        @update:filters="emit('update:filters', $event)"
                    />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 0.2s ease;
}
.drawer-enter-active > div:last-child,
.drawer-leave-active > div:last-child {
    transition: transform 0.3s ease;
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
}
.drawer-enter-from > div:last-child,
.drawer-leave-to > div:last-child {
    transform: translateX(100%);
}
</style>
