<script setup>
import { cn } from '@/lib/utils';
</script>

<template>
    <div :class="cn('flex flex-col gap-1.5 px-6 pt-6 pb-2', $attrs.class)"
         v-bind="{ ...$attrs, class: undefined }">
        <slot />
    </div>
</template>
