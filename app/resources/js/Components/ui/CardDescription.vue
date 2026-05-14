<script setup>
import { cn } from '@/lib/utils';
</script>

<template>
    <p :class="cn('text-sm text-muted-foreground', $attrs.class)"
       v-bind="{ ...$attrs, class: undefined }">
        <slot />
    </p>
</template>
