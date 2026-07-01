<script setup>
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
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

const preview = computed(() => {
    const m = props.ticket.latest_message;
    if (!m) return props.ticket.subject || '';
    const prefix = m.direction === 'outbound' ? 'Você: ' : '';
    const mediaLabels = { image: '📷 Imagem', sticker: '📷 Figurinha', audio: '🎤 Áudio', video: '🎥 Vídeo', document: '📄 Documento' };
    const text = (m.type && m.type !== 'text') ? (mediaLabels[m.type] || '📎 Mídia') : (m.body || '');
    return prefix + text;
});

const unread = computed(() => (props.ticket.unread_count || 0) > 0);
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
        <Avatar><AvatarImage v-if="ticket.client?.avatar_url" :src="ticket.client.avatar_url" :alt="ticket.client?.name" /><AvatarFallback>{{ initials(ticket.client?.name || ticket.client?.phone) }}</AvatarFallback></Avatar>
        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
                <span :class="cn(
                    'text-[13.5px] font-medium truncate leading-tight',
                    active ? 'text-primary' : 'text-foreground',
                )">
                    {{ ticket.client?.name || ticket.client?.phone }}
                </span>
                <span :class="cn('text-[10.5px] whitespace-nowrap font-medium tabular-nums', unread ? 'text-orange-500 font-semibold' : 'text-muted-foreground')">
                    {{ fromNow(ticket.last_message_at || ticket.created_at) }}
                </span>
            </div>
            <div class="mt-1.5 flex items-center justify-between gap-2">
                <span class="truncate text-[12px]" :class="unread ? 'text-foreground font-medium' : 'text-muted-foreground'">{{ preview }}</span>
                <span v-if="unread"
                      class="flex h-[19px] min-w-[19px] shrink-0 items-center justify-center rounded-full bg-orange-500 px-1.5 text-[11px] font-bold text-white tabular-nums">
                    {{ ticket.unread_count > 99 ? '99+' : ticket.unread_count }}
                </span>
            </div>
        </div>
    </button>
</template>
