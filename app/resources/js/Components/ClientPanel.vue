<script setup>
import { computed } from 'vue';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';
import { useFormat } from '@/Composables/useFormat';
import { Hash, Calendar, Building2, UserCheck } from 'lucide-vue-next';

const props = defineProps({
    ticket: { type: Object, default: null },
});
defineEmits(['close']);

const { phone, dt } = useFormat();
const client = computed(() => props.ticket?.client || {});

const initials = (name) => (name || '?')
    .split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();

const statusVariant = computed(() => ({
    queued: 'secondary', open: 'default', pending: 'default',
    resolved: 'outline', closed: 'outline', menu: 'default',
}[props.ticket?.status] || 'outline'));
</script>

<template>
    <aside v-if="ticket"
           class="hidden xl:flex w-80 border-l border-border bg-card flex-col overflow-y-auto scrollbar-thin">
        <!-- Profile header -->
        <div class="px-6 pt-8 pb-6 text-center border-b border-border">
            <Avatar class="mx-auto mb-4"><AvatarFallback>{{ initials(client.name || client.phone) }}</AvatarFallback></Avatar>
            <h3 class="font-semibold text-foreground text-[15px] tracking-tight leading-tight">
                {{ client.name || 'Sem nome' }}
            </h3>
            <p class="text-[13px] text-muted-foreground mt-1 tabular-nums">
                {{ phone(client.phone) }}
            </p>
        </div>

        <!-- Info grid -->
        <div class="px-6 py-5 space-y-4">
            <div class="grid grid-cols-1 gap-4 text-[13px]">
                <div class="flex items-start gap-3">
                    <Hash class="h-4 w-4 text-muted-foreground shrink-0 mt-0.5" />
                    <div class="min-w-0">
                        <div class="text-[10.5px] uppercase tracking-[0.12em] text-muted-foreground font-medium mb-0.5">Protocolo</div>
                        <div class="font-mono text-[12.5px] text-foreground">#{{ ticket.protocol }}</div>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <UserCheck class="h-4 w-4 text-muted-foreground shrink-0 mt-0.5" />
                    <div class="min-w-0">
                        <div class="text-[10.5px] uppercase tracking-[0.12em] text-muted-foreground font-medium mb-1">Status</div>
                        <Badge :variant="statusVariant">{{ ticket.status }}</Badge>
                    </div>
                </div>

                <div v-if="ticket.sector" class="flex items-start gap-3">
                    <Building2 class="h-4 w-4 text-muted-foreground shrink-0 mt-0.5" />
                    <div class="min-w-0">
                        <div class="text-[10.5px] uppercase tracking-[0.12em] text-muted-foreground font-medium mb-0.5">Setor</div>
                        <div class="text-foreground">{{ ticket.sector.name }}</div>
                    </div>
                </div>

                <div v-if="ticket.assignee" class="flex items-start gap-3">
                    <Avatar class="mt-0.5"><AvatarFallback>{{ initials(ticket.assignee.name) }}</AvatarFallback></Avatar>
                    <div class="min-w-0">
                        <div class="text-[10.5px] uppercase tracking-[0.12em] text-muted-foreground font-medium mb-0.5">Atendente</div>
                        <div class="text-foreground truncate">{{ ticket.assignee.name }}</div>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <Calendar class="h-4 w-4 text-muted-foreground shrink-0 mt-0.5" />
                    <div class="min-w-0">
                        <div class="text-[10.5px] uppercase tracking-[0.12em] text-muted-foreground font-medium mb-0.5">Aberto em</div>
                        <div class="text-foreground tabular-nums">{{ dt(ticket.created_at) }}</div>
                    </div>
                </div>
            </div>

            <Separator v-if="(ticket.tags || []).length" class="my-2" />

            <div v-if="(ticket.tags || []).length">
                <div class="text-[10.5px] uppercase tracking-[0.12em] text-muted-foreground font-medium mb-2">Tags</div>
                <div class="flex flex-wrap gap-1.5">
                    <Badge v-for="tag in ticket.tags" :key="tag.id" variant="outline">{{ tag.name }}</Badge>
                </div>
            </div>
        </div>

    </aside>
</template>
