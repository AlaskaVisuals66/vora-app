<script setup>
import { cn } from '@/lib/utils';
</script>

<template>
    <h3 :class="cn('text-base font-semibold leading-none tracking-tight', $attrs.class)"
        v-bind="{ ...$attrs, class: undefined }">
        <slot />
    </h3>
</template>
