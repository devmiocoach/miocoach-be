<script setup lang="ts">
import { cn } from '@/lib/utils';
import { reactiveOmit } from '@vueuse/core';
import type { ProgressRootEmits, ProgressRootProps } from 'reka-ui';
import { ProgressIndicator, ProgressRoot, useForwardPropsEmits } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import type { ProgressVariants } from '.';
import { progressVariants } from '.';

const props = withDefaults(
    defineProps<
        ProgressRootProps & {
            class?: HTMLAttributes['class'];
            variant?: ProgressVariants['variant'];
        }
    >(),
    {
        modelValue: 0,
        variant: 'default',
    },
);

const emits = defineEmits<ProgressRootEmits>();

const delegatedProps = reactiveOmit(props, 'class', 'variant');
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <ProgressRoot
        v-bind="forwarded"
        data-slot="progress"
        :class="
            cn('relative h-2 w-full overflow-hidden rounded-full', props.class)
        "
    >
        <ProgressIndicator
            data-slot="progress-indicator"
            :class="
                cn(
                    'h-full w-full flex-1 transition-all',
                    progressVariants({ variant }),
                )
            "
        />
    </ProgressRoot>
</template>
