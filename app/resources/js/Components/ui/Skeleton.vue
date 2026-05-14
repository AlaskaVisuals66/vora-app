<script setup>
import { cn } from '@/lib/utils';
</script>

<template>
    <div :class="cn('animate-pulse rounded-md bg-muted', $attrs.class)"
         v-bind="{ ...$attrs, class: undefined }" />
</template>
