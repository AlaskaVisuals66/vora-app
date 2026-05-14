<script setup>
import { cn } from '@/lib/utils';
defineProps({ modelValue: { type: [String, Number], default: '' } });
defineEmits(['update:modelValue']);
</script>

<template>
    <input
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        :class="cn(
            'flex h-10 w-full rounded-md border border-border bg-card px-3 py-2 text-sm text-foreground',
            'placeholder:text-muted-foreground',
            'transition-shadow duration-150',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:border-transparent',
            'disabled:cursor-not-allowed disabled:opacity-50',
            $attrs.class
        )"
        v-bind="{ ...$attrs, class: undefined }"
    />
</template>
