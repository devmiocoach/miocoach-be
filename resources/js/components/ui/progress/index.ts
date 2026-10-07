import type { VariantProps } from 'class-variance-authority';
import { cva } from 'class-variance-authority';

export { default as Progress } from './Progress.vue';

export const progressVariants = cva('bg-primary transition-all', {
    variants: {
        variant: {
            default: 'bg-primary',
            secondary: 'bg-secondary',
            tertiary: 'bg-tertiary',
        },
    },
    defaultVariants: {
        variant: 'default',
    },
});

export type ProgressVariants = VariantProps<typeof progressVariants>;
