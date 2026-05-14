<script setup>
import { cn } from '@/lib/utils';
</script>

<template>
    <div :class="cn('flex items-center px-6 py-4 border-t border-border', $attrs.class)"
         v-bind="{ ...$attrs, class: undefined }">
        <slot />
    </div>
</template>
