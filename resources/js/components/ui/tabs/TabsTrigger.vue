<script setup lang="ts">
import { cn } from '@/lib/utils';
import { reactiveOmit } from '@vueuse/core';
import type { TabsTriggerProps } from 'reka-ui';
import { TabsTrigger, useForwardProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';

const props = defineProps<
    TabsTriggerProps & { class?: HTMLAttributes['class'] }
>();

const delegatedProps = reactiveOmit(props, 'class');
const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
    <TabsTrigger
        data-slot="tabs-trigger"
        v-bind="forwardedProps"
        :class="
            cn(
                'focus-visible:ring-ring inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-all focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50',
                'data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-sm',
                'data-[state=inactive]:text-foreground/60 data-[state=inactive]:hover:text-foreground',
                props.class,
            )
        "
    >
        <slot />
    </TabsTrigger>
</template>
