<script setup>
import { Skeleton } from '@/Components/ui/skeleton';

defineProps({
    variant: { type: String, default: 'list' }, // 'list' | 'cards' | 'table'
    rows: { type: Number, default: 5 },
});
</script>

<template>
    <div v-if="variant === 'cards'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Skeleton v-for="i in 4" :key="i" class="h-28 rounded-[var(--radius)]" />
    </div>
    <div v-else-if="variant === 'table'" class="space-y-2">
        <Skeleton class="h-9 w-full" />
        <Skeleton v-for="i in rows" :key="i" class="h-12 w-full" />
    </div>
    <div v-else class="space-y-3">
        <div v-for="i in rows" :key="i" class="flex items-center gap-3">
            <Skeleton class="h-9 w-9 rounded-full" />
            <div class="flex-1 space-y-2">
                <Skeleton class="h-3.5 w-1/3" />
                <Skeleton class="h-3 w-2/3" />
            </div>
        </div>
    </div>
</template>
