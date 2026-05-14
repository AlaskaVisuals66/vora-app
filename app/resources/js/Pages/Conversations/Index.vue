<script setup>
import { onMounted, onBeforeUnmount, ref, computed, nextTick, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TicketListItem from '@/Components/TicketListItem.vue';
import MessageBubble from '@/Components/MessageBubble.vue';
import ClientPanel from '@/Components/ClientPanel.vue';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { useConversationsStore } from '@/Stores/conversations';
import { useAuth } from '@/Composables/useAuth';
import { getEcho } from '@/lib/echo';
import { Search, Send, MessagesSquare, Paperclip, Smile } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    ticketId:   { type: [String, Number], default: null },
    sectorSlug: { type: String, default: null },
});
const page = usePage();
const store = useConversationsStore();
const { user } = useAuth();
const currentSector = ref(null);

const draft = ref('');
const messageScroll = ref(null);
const tenantChannel = ref(null);
const ticketChannel = ref(null);
const typingTimer = ref(null);

const filtered = computed(() => {
    if (!store.filters.search) return store.tickets;
    const q = store.filters.search.toLowerCase();
    return store.tickets.filter(t =>
        (t.client?.name || '').toLowerCase().includes(q) ||
        (t.client?.phone || '').includes(q) ||
        (t.protocol || '').toLowerCase().includes(q),
    );
});

const typingNow = computed(() => Object.values(store.typingUsers).filter(t => Date.now() - t.at < 3000));

const statusOptions = [
    { value: 'open',    label: 'Abertos' },
    { value: 'queued',  label: 'Em fila' },
    { value: 'pending', label: 'Aguardando' },
    { value: 'closed',  label: 'Encerrados' },
];

const statusVariant = computed(() => ({
    queued: 'secondary', open: 'default', pending: 'default',
    resolved: 'outline', closed: 'outline', menu: 'default',
}[store.active?.status] || 'outline'));

const initials = (name) => (name || '?')
    .split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();

async function selectTicket(id) {
    await store.openTicket(id);
    const base = props.sectorSlug ? `/conversations/sector/${props.sectorSlug}` : '/conversations';
    history.replaceState(null, '', `${base}/${id}`);
    subscribeTicket(id);
    await scrollToBottom();
}

async function send() {
    if (!draft.value.trim()) return;
    const text = draft.value;
    draft.value = '';
    await store.sendMessage(text);
    await scrollToBottom();
}

async function scrollToBottom() {
    await nextTick();
    if (messageScroll.value) messageScroll.value.scrollTop = messageScroll.value.scrollHeight;
}

function emitTyping() {
    if (!store.active || typingTimer.value) return;
    axios.post('/api/v1/presence/typing', { ticket_id: store.active.id }).catch(() => {});
    typingTimer.value = setTimeout(() => { typingTimer.value = null; }, 2500);
}

function subscribeTenant() {
    const tenantId = user.value?.tenant_id;
    if (!tenantId) return;
    const echo = getEcho();
    tenantChannel.value = echo.private(`tenant.${tenantId}`)
        .listen('.message.received', (e) => {
            store.pushIncomingMessage(e.message);
            store.upsertTicket(e.ticket);
            scrollToBottom();
        })
        .listen('.message.sent', (e) => {
            store.pushIncomingMessage(e.message);
            scrollToBottom();
        })
        .listen('.ticket.assigned',    (e) => store.upsertTicket(e.ticket))
        .listen('.ticket.queued',      (e) => store.upsertTicket(e.ticket))
        .listen('.ticket.transferred', (e) => store.upsertTicket(e.ticket));
}

function subscribeTicket(id) {
    const tenantId = user.value?.tenant_id;
    if (!tenantId) return;
    const echo = getEcho();
    if (ticketChannel.value) echo.leave(ticketChannel.value);
    const name = `tenant.${tenantId}.ticket.${id}`;
    ticketChannel.value = name;
    echo.private(name).listen('.attendant.typing', (e) => {
        store.setTyping(id, e.user_id, e.user_name);
    });
}

watch(() => store.messages.length, () => scrollToBottom());

async function resolveSectorFromSlug() {
    if (!props.sectorSlug) {
        store.filters.sector_id = null;
        currentSector.value = null;
        return;
    }
    try {
        const { data } = await axios.get('/api/v1/sectors');
        const list = data.data || [];
        const match = list.find(s => s.slug === props.sectorSlug);
        currentSector.value = match || null;
        store.filters.sector_id = match ? match.id : null;
    } catch (_) {
        store.filters.sector_id = null;
    }
}

watch(() => props.sectorSlug, async () => {
    await resolveSectorFromSlug();
    await store.fetchTickets();
});

onMounted(async () => {
    subscribeTenant();
    await resolveSectorFromSlug();
    await store.fetchTickets();
    if (props.ticketId) await selectTicket(Number(props.ticketId));
});

onBeforeUnmount(() => {
    const echo = getEcho();
    const tenantId = user.value?.tenant_id;
    if (tenantId) echo.leave(`tenant.${tenantId}`);
    if (ticketChannel.value) echo.leave(ticketChannel.value);
});
</script>

<template>
    <Head :title="currentSector ? `Conversas — ${currentSector.name}` : 'Conversas — Vora'" />
    <AppLayout>
        <div class="flex h-full overflow-hidden">

            <!-- Lista de tickets -->
            <section class="w-[340px] border-r border-border bg-card flex flex-col">
                <div class="px-5 pt-5 pb-4 border-b border-border">
                    <div class="flex items-center gap-2 mb-1">
                        <span v-if="currentSector" class="h-2.5 w-2.5 rounded-full"
                              :style="{ backgroundColor: currentSector.color || '#94A3B8' }" />
                        <h2 class="text-[16px] font-semibold text-foreground tracking-tight">
                            {{ currentSector ? currentSector.name : 'Conversas' }}
                        </h2>
                    </div>
                    <p class="text-[12px] text-muted-foreground">
                        {{ currentSector ? `Tickets do setor ${currentSector.name}` : 'Todas as conversas ativas' }}
                    </p>

                    <div class="relative mt-4">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                        <Input v-model="store.filters.search" type="text"
                               placeholder="Buscar nome, telefone ou protocolo" class="pl-9 h-9 text-[13px]" />
                    </div>

                    <div class="flex flex-wrap gap-1.5 mt-3">
                        <button v-for="s in statusOptions" :key="s.value"
                                @click="store.filters.status = s.value; store.fetchTickets()"
                                :class="['text-[11.5px] px-2.5 py-1 rounded-full font-medium transition-colors',
                                         store.filters.status === s.value
                                             ? 'bg-foreground text-background'
                                             : 'bg-muted text-muted-foreground hover:bg-muted/70']">
                            {{ s.label }}
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-2 space-y-1 scrollbar-thin">
                    <div v-if="store.loading" class="p-4 text-[13px] text-muted-foreground text-center">
                        Carregando…
                    </div>
                    <TicketListItem v-for="t in filtered" :key="t.id" :ticket="t"
                                    :active="store.active?.id === t.id"
                                    @select="selectTicket" />
                    <div v-if="!store.loading && !filtered.length"
                         class="px-6 py-12 text-center">
                        <MessagesSquare class="h-8 w-8 text-muted-foreground/40 mx-auto mb-2" />
                        <p class="text-[13px] text-muted-foreground">Nenhuma conversa encontrada</p>
                    </div>
                </div>
            </section>

            <!-- Chat -->
            <section class="flex-1 flex flex-col bg-background min-w-0">
                <template v-if="store.active">
                    <header class="bg-card border-b border-border px-6 py-3 flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <Avatar><AvatarFallback>{{ initials(store.active.client?.name || store.active.client?.phone) }}</AvatarFallback></Avatar>
                            <div class="min-w-0">
                                <div class="font-semibold text-foreground text-[14px] truncate">
                                    {{ store.active.client?.name || store.active.client?.phone }}
                                </div>
                                <div class="text-[11.5px] text-muted-foreground tabular-nums truncate">
                                    #{{ store.active.protocol }} · {{ store.active.sector?.name || 'sem setor' }}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <Badge :variant="statusVariant">{{ store.active.status }}</Badge>
                        </div>
                    </header>

                    <div ref="messageScroll"
                         class="flex-1 overflow-y-auto px-6 py-6 scrollbar-thin bg-dots">
                        <div v-if="store.messagesLoading"
                             class="text-center text-[13px] text-muted-foreground">
                            Carregando mensagens…
                        </div>
                        <MessageBubble v-for="m in store.messages" :key="m.id" :message="m" />
                        <div v-if="typingNow.length"
                             class="text-[12px] text-muted-foreground italic mt-3 px-1">
                            {{ typingNow.map(t => t.name).join(', ') }} digitando…
                        </div>
                    </div>

                    <footer class="bg-card border-t border-border px-4 py-3 shrink-0">
                        <div class="flex items-end gap-2">
                            <Button type="button" variant="ghost" size="icon"
                                    class="shrink-0 text-muted-foreground" tabindex="-1">
                                <Paperclip class="h-4 w-4" />
                            </Button>
                            <Textarea v-model="draft" @input="emitTyping"
                                      @keydown.enter.exact.prevent="send"
                                      rows="1" placeholder="Digite sua mensagem…"
                                      class="resize-none flex-1 max-h-32 min-h-[40px] py-2.5" />
                            <Button type="button" variant="ghost" size="icon"
                                    class="shrink-0 text-muted-foreground" tabindex="-1">
                                <Smile class="h-4 w-4" />
                            </Button>
                            <Button variant="default" size="icon" @click="send"
                                    :disabled="!draft.trim()" class="shrink-0">
                                <Send class="h-4 w-4" />
                            </Button>
                        </div>
                    </footer>
                </template>

                <div v-else class="flex-1 flex flex-col items-center justify-center text-center p-8">
                    <div class="h-16 w-16 rounded-2xl bg-vora-mark flex items-center justify-center mb-5">
                        <MessagesSquare class="h-7 w-7" />
                    </div>
                    <h3 class="text-[16px] font-semibold text-foreground tracking-tight">
                        Selecione uma conversa
                    </h3>
                    <p class="text-[13px] text-muted-foreground mt-1.5 max-w-xs">
                        Escolha um ticket à esquerda para começar a atender
                    </p>
                </div>
            </section>

            <!-- Painel cliente -->
            <ClientPanel :ticket="store.active"
                         @close="store.closeActive()"
                         @transfer="$emit('open-transfer')" />
        </div>
    </AppLayout>
</template>
