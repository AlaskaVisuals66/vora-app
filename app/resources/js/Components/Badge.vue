<script setup>
import { computed } from 'vue';
import UiBadge from '@/Components/ui/Badge.vue';

const props = defineProps({
    color:   { type: String, default: null },
    variant: { type: String, default: null },
});

const variantMap = {
    slate:   'muted',
    indigo:  'default',
    emerald: 'success',
    amber:   'warning',
    rose:    'destructive',
    sky:     'info',
    purple:  'default',
    accent:  'accent',
    primary: 'default',
    success: 'success',
    warning: 'warning',
    info:    'info',
    danger:  'destructive',
    outline: 'outline',
};

const resolvedVariant = computed(() => {
    if (props.variant) return props.variant;
    if (props.color)   return variantMap[props.color] || 'muted';
    return 'default';
});
</script>

<template>
    <UiBadge :variant="resolvedVariant"><slot /></UiBadge>
</template>
