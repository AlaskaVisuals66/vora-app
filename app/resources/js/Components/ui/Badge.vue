<script setup>
import { computed } from 'vue';
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const props = defineProps({
    variant: { type: String, default: 'default' },
});

const badgeVariants = cva(
    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium leading-none transition-colors whitespace-nowrap',
    {
        variants: {
            variant: {
                default:     'bg-primary/10 text-primary',
                accent:      'bg-accent/10 text-accent',
                destructive: 'bg-destructive/10 text-destructive',
                success:     'bg-emerald-500/10 text-emerald-700',
                warning:     'bg-amber-500/10 text-amber-700',
                info:        'bg-sky-500/10 text-sky-700',
                muted:       'bg-muted text-muted-foreground',
                outline:     'border border-border text-foreground bg-card',
                solid:       'bg-primary text-primary-foreground',
            },
        },
        defaultVariants: { variant: 'default' },
    },
);

const classes = computed(() => badgeVariants({ variant: props.variant }));
</script>

<template>
    <span :class="cn(classes, $attrs.class)" v-bind="{ ...$attrs, class: undefined }">
        <slot />
    </span>
</template>
