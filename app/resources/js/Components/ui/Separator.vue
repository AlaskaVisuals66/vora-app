<script setup>
import { cn } from '@/lib/utils';
defineProps({ orientation: { type: String, default: 'horizontal' } });
</script>

<template>
    <div role="separator"
         :class="cn(
             'bg-border shrink-0',
             orientation === 'horizontal' ? 'h-px w-full' : 'w-px h-full',
             $attrs.class,
         )"
         v-bind="{ ...$attrs, class: undefined }" />
</template>
