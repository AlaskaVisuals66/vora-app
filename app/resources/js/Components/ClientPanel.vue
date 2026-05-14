<script setup>
import { computed } from 'vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Separator from '@/Components/ui/Separator.vue';
import { useFormat } from '@/Composables/useFormat';
import { ArrowRightLeft, X, Phone, Hash, Calendar, Building2, UserCheck } from 'lucide-vue-next';

const props = defineProps({
    ticket: { type: Object, default: null },
});
defineEmits(['close', 'transfer']);

const { phone, dt } = useFormat();
const client = computed(() => props.ticket?.client || {});

const statusVariant = computed(() => ({
    queued: 'warning', open: 'success', pending: 'info',
    resolved: 'muted', closed: 'muted', menu: 'default',
}[props.ticket?.status] || 'muted'));
</script>

<template>
    <aside v-if="ticket"
           class="hidden xl:flex w-80 border-l border-border bg-card flex-col overflow-y-auto scrollbar-thin">
        <!-- Profile header -->
        <div class="px-6 pt-8 pb-6 text-center border-b border-border">
            <Avatar :name="client.name || client.phone" size="xl" class="mx-auto mb-4" />
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
                    <Avatar :name="ticket.assignee.name" size="xs" class="mt-0.5" />
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

        <!-- Actions -->
        <div class="mt-auto px-6 py-4 border-t border-border space-y-2 bg-muted/30">
            <Button variant="outline" class="w-full" @click="$emit('transfer')">
                <ArrowRightLeft class="h-3.5 w-3.5" />
                Transferir
            </Button>
            <Button variant="destructive" class="w-full"
                    @click="$emit('close')" :disabled="ticket.status === 'closed'">
                <X class="h-3.5 w-3.5" />
                Encerrar atendimento
            </Button>
        </div>
    </aside>
</template>
