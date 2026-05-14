<script setup>
defineProps({
    icon: { type: [Object, Function], default: null },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <div v-if="icon" class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-muted">
            <component :is="icon" class="h-5 w-5 text-muted-foreground" />
        </div>
        <h3 class="text-[15px] font-semibold tracking-tight text-foreground">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-xs text-[13px] text-muted-foreground">{{ description }}</p>
        <div class="mt-4"><slot /></div>
    </div>
</template>
