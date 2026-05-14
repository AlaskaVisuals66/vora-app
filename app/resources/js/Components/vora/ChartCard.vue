<script setup>
import { Card } from '@/Components/ui/card';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <Card class="p-5">
        <div class="mb-4 flex items-start justify-between">
            <div>
                <h3 class="text-[14px] font-semibold tracking-tight text-foreground">{{ title }}</h3>
                <p v-if="subtitle" class="text-[12px] text-muted-foreground">{{ subtitle }}</p>
            </div>
            <slot name="actions" />
        </div>
        <div class="min-h-[180px]">
            <slot />
        </div>
    </Card>
</template>
