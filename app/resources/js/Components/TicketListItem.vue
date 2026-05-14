<script setup>
import { computed } from 'vue';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { useFormat } from '@/Composables/useFormat';
import { cn } from '@/lib/utils';

const props = defineProps({
    ticket: { type: Object, required: true },
    active: { type: Boolean, default: false },
});
defineEmits(['select']);

const { fromNow } = useFormat();

const initials = (name) => (name || '?')
    .split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();

const statusVariant = computed(() => ({
    queued:   'secondary',
    open:     'default',
    pending:  'default',
    resolved: 'outline',
    closed:   'outline',
    menu:     'default',
}[props.ticket.status] || 'outline'));

const statusLabel = computed(() => ({
    queued: 'Em fila', open: 'Atendendo', pending: 'Aguardando',
    resolved: 'Resolvido', closed: 'Encerrado', menu: 'Menu',
}[props.ticket.status] || props.ticket.status));
</script>

<template>
    <button
        @click="$emit('select', ticket.id)"
        :class="cn(
            'group w-full flex items-start gap-3 p-3 rounded-lg text-left transition-all duration-150 border',
            active
                ? 'bg-primary/[0.04] border-primary/20'
                : 'border-transparent hover:bg-muted/60 hover:border-border'
        )">
        <Avatar><AvatarFallback>{{ initials(ticket.client?.name || ticket.client?.phone) }}</AvatarFallback></Avatar>
        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
                <span :class="cn(
                    'text-[13.5px] font-medium truncate leading-tight',
                    active ? 'text-primary' : 'text-foreground',
                )">
                    {{ ticket.client?.name || ticket.client?.phone }}
                </span>
                <span class="text-[10.5px] text-muted-foreground whitespace-nowrap font-medium tabular-nums">
                    {{ fromNow(ticket.last_message_at || ticket.created_at) }}
                </span>
            </div>
            <div class="flex items-center gap-1.5 mt-1.5">
                <span class="text-[11px] text-muted-foreground font-mono">#{{ ticket.protocol }}</span>
                <Badge :variant="statusVariant" class="text-[10px] py-0 h-4">{{ statusLabel }}</Badge>
            </div>
            <div v-if="ticket.subject" class="text-[11.5px] text-muted-foreground truncate mt-1">{{ ticket.subject }}</div>
        </div>
    </button>
</template>
