<script setup>
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import { useFormat } from '@/Composables/useFormat';

const props = defineProps({
    name:   { type: String, default: '' },
    src:    { type: String, default: null },
    size:   { type: String, default: 'md' },
    status: { type: String, default: null },
});

const { initials } = useFormat();

const sizeClasses = computed(() => ({
    xs: 'h-6 w-6 text-[10px]',
    sm: 'h-8 w-8 text-xs',
    md: 'h-10 w-10 text-[13px]',
    lg: 'h-14 w-14 text-base',
    xl: 'h-20 w-20 text-xl',
}[props.size] || 'h-10 w-10 text-[13px]'));

const statusColor = computed(() => ({
    online:  'bg-emerald-500',
    away:    'bg-amber-500',
    busy:    'bg-destructive',
    offline: 'bg-muted-foreground/40',
}[props.status]));

// Deterministic gradient per name — feels personal, not generic
const gradient = computed(() => {
    const palettes = [
        'from-[#00153D] to-[#1f2d6e]',
        'from-[#00153D] to-[#FF5A1F]',
        'from-[#1e293b] to-[#475569]',
        'from-[#0f172a] to-[#312e81]',
        'from-[#0c4a6e] to-[#0369a1]',
    ];
    let h = 0;
    for (let i = 0; i < (props.name || '').length; i++) h = (h * 31 + props.name.charCodeAt(i)) >>> 0;
    return palettes[h % palettes.length];
});
</script>

<template>
    <span class="relative inline-block shrink-0">
        <span :class="cn(
            'rounded-full bg-gradient-to-br text-white font-semibold flex items-center justify-center select-none ring-2 ring-card overflow-hidden',
            gradient,
            sizeClasses,
        )">
            <img v-if="src" :src="src" :alt="name" class="h-full w-full object-cover">
            <span v-else>{{ initials(name) }}</span>
        </span>
        <span v-if="status"
              :class="cn(
                  'absolute bottom-0 right-0 block rounded-full ring-2 ring-card',
                  size === 'xs' || size === 'sm' ? 'h-2 w-2' : 'h-2.5 w-2.5',
                  statusColor,
              )" />
    </span>
</template>
