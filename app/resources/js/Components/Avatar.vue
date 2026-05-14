<script setup>
import UiAvatar from '@/Components/ui/Avatar.vue';
defineProps({
    name:   { type: String, default: '' },
    src:    { type: String, default: null },
    size:   { type: String, default: 'md' },
    status: { type: String, default: null },
});
</script>

<template>
    <UiAvatar :name="name" :src="src" :size="size" :status="status" />
</template>
