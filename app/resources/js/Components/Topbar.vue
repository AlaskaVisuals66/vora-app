<script setup>
defineProps({
    title:    { type: String, default: '' },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <header class="bg-card border-b border-border px-8 py-5 flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-[20px] font-semibold text-foreground tracking-tight leading-tight">{{ title }}</h1>
            <p v-if="subtitle" class="text-[13px] text-muted-foreground mt-1 leading-snug">{{ subtitle }}</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <slot name="actions" />
        </div>
    </header>
</template>
