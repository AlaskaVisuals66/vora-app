<script setup>
import { cn } from '@/lib/utils';
</script>

<template>
    <div :class="cn('px-6 pb-6 pt-2', $attrs.class)"
         v-bind="{ ...$attrs, class: undefined }">
        <slot />
    </div>
</template>
