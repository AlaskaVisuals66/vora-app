<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex items-start justify-between gap-4 pb-5">
        <div>
            <h1 class="text-[20px] font-semibold tracking-tight text-foreground">{{ title }}</h1>
            <p v-if="description" class="mt-0.5 text-[13px] text-muted-foreground">{{ description }}</p>
        </div>
        <div class="flex items-center gap-2"><slot name="actions" /></div>
    </div>
</template>
