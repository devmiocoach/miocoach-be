<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useColorMode } from '@vueuse/core';
import IconMoon from '~icons/heroicons/moon';
import IconSun from '~icons/heroicons/sun';

const mode = useColorMode();

function toggleMode() {
    mode.value = mode.value === 'dark' ? 'light' : 'dark';
}
</script>

<template>
    <Button
        variant="ghost"
        size="icon-sm"
        :aria-label="$t('Toggle dark mode')"
        @click="toggleMode"
    >
        <IconSun v-if="mode === 'dark'" class="size-4" />
        <IconMoon v-else class="size-4" />
    </Button>
</template>
