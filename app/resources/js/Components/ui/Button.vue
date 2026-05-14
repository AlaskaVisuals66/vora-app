<script setup>
import { computed } from 'vue';
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const props = defineProps({
    variant: { type: String, default: 'default' },
    size:    { type: String, default: 'default' },
    as:      { type: String, default: 'button' },
    type:    { type: String, default: 'button' },
    disabled:{ type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 select-none',
    {
        variants: {
            variant: {
                default:     'bg-primary text-primary-foreground shadow-card hover:bg-primary/90 active:translate-y-px',
                accent:      'bg-accent text-accent-foreground shadow-accent hover:bg-accent/90 active:translate-y-px',
                destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90 active:translate-y-px',
                outline:     'border border-border bg-card text-foreground hover:bg-muted hover:border-foreground/20',
                secondary:   'bg-secondary text-secondary-foreground hover:bg-secondary/70',
                ghost:       'text-foreground hover:bg-muted',
                link:        'text-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-9 px-4',
                sm:      'h-8 px-3 text-xs rounded-md',
                lg:      'h-11 px-6 text-base',
                icon:    'h-9 w-9',
            },
        },
        defaultVariants: { variant: 'default', size: 'default' },
    },
);

const classes = computed(() => buttonVariants({ variant: props.variant, size: props.size }));
</script>

<template>
    <component :is="as" :type="as === 'button' ? type : undefined"
               :class="cn(classes, $attrs.class)" :disabled="disabled || loading"
               v-bind="{ ...$attrs, class: undefined }">
        <svg v-if="loading" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/>
        </svg>
        <slot />
    </component>
</template>
