<script setup>
import { computed } from 'vue';
import { Card } from '@/Components/ui/card';
import { TrendingUp, TrendingDown } from 'lucide-vue-next';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    delta: { type: Number, default: null },
    icon: { type: [Object, Function], default: null },
});

const deltaPositive = computed(() => (props.delta ?? 0) >= 0);
</script>

<template>
    <Card class="p-5">
        <div class="flex items-start justify-between">
            <p class="text-[13px] font-medium text-muted-foreground">{{ label }}</p>
            <component :is="icon" v-if="icon" class="h-4 w-4 text-muted-foreground" />
        </div>
        <p class="mt-2 text-2xl font-semibold tracking-tight text-foreground tabular-nums">
            {{ value }}
        </p>
        <div v-if="delta !== null" class="mt-1 flex items-center gap-1 text-[12px] font-medium"
             :class="deltaPositive ? 'text-emerald-600' : 'text-destructive'">
            <component :is="deltaPositive ? TrendingUp : TrendingDown" class="h-3.5 w-3.5" />
            {{ Math.abs(delta) }}%
        </div>
    </Card>
</template>
