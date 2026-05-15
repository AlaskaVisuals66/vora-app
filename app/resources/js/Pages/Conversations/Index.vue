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
import { Search, Send, MessagesSquare, Paperclip, Smile, Inbox, SlidersHorizontal, Check } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    ticketId:   { type: [String, Number], default: null },
    sectorSlug: { type: String, default: null },
});
const page = usePage();
const store = useConversationsStore();
const { user } = useAuth();
const currentSector = ref(null);
const sectors = ref([]);

const draft = ref('');
const messageScroll = ref(null);
const tenantChannel = ref(null);
const ticketChannel = ref(null);
const typingTimer = ref(null);
const filterOpen = ref(false);

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

const currentStatusLabel = computed(() =>
    statusOptions.find(s => s.value === store.filters.status)?.label ?? 'Todos',
);

function setStatus(value) {
    store.filters.status = value;
    filterOpen.value = false;
    store.fetchTickets();
}

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

async function loadSectors() {
    try {
        const { data } = await axios.get('/api/v1/sectors');
        sectors.value = data.data || [];
    } catch (_) {}
}

function selectSector(sector) {
    currentSector.value = sector || null;
    store.filters.sector_id = sector ? sector.id : null;
    const path = sector ? `/conversations/sector/${sector.slug}` : '/conversations';
    history.replaceState(null, '', path);
    store.fetchTickets();
}

async function resolveSectorFromSlug() {
    if (!props.sectorSlug) {
        store.filters.sector_id = null;
        currentSector.value = null;
        return;
    }
    const match = sectors.value.find(s => s.slug === props.sectorSlug);
    currentSector.value = match || null;
    store.filters.sector_id = match ? match.id : null;
}

watch(() => props.sectorSlug, async () => {
    await resolveSectorFromSlug();
    await store.fetchTickets();
});

onMounted(async () => {
    subscribeTenant();
    await loadSectors();
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

            <!-- Coluna de setores -->
            <aside class="flex w-[200px] shrink-0 flex-col border-r border-border bg-card">
                <div class="flex h-14 shrink-0 items-center border-b border-border px-4">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Setores</span>
                </div>
                <nav class="flex-1 overflow-y-auto p-2 space-y-0.5">
                    <!-- Todos -->
                    <button
                        @click="selectSector(null)"
                        class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-[13px] font-medium transition-colors"
                        :class="!currentSector
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted/70 hover:text-foreground'"
                    >
                        <Inbox class="h-4 w-4 shrink-0" />
                        <span class="truncate">Todos</span>
                    </button>

                    <!-- Setores -->
                    <button
                        v-for="s in sectors"
                        :key="s.id"
                        @click="selectSector(s)"
                        class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-[13px] font-medium transition-colors"
                        :class="currentSector?.id === s.id
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted/70 hover:text-foreground'"
                    >
                        <span class="h-2 w-2 shrink-0 rounded-full"
                              :style="{ backgroundColor: s.color || '#64748b' }" />
                        <span class="flex-1 truncate">{{ s.name }}</span>
                        <span v-if="s.open_tickets > 0"
                              class="ml-auto shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums leading-none"
                              :class="currentSector?.id === s.id ? 'bg-white/20 text-white' : 'bg-muted text-muted-foreground'">
                            {{ s.open_tickets }}
                        </span>
                    </button>

                    <div v-if="!sectors.length"
                         class="px-3 py-4 text-[12px] text-muted-foreground text-center">
                        Nenhum setor
                    </div>
                </nav>
            </aside>

            <!-- Lista de tickets -->
            <section class="w-[300px] shrink-0 border-r border-border bg-card flex flex-col">
                <div class="px-4 pt-4 pb-3 border-b border-border">
                    <h2 class="text-[14px] font-semibold text-foreground mb-3">
                        {{ currentSector ? currentSector.name : 'Todas as conversas' }}
                    </h2>

                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
                        <Input v-model="store.filters.search" type="text"
                               placeholder="Nome, telefone ou protocolo" class="pl-9 h-8 text-[12.5px]" />
                    </div>

                    <!-- Status filter -->
                    <div class="relative mt-2.5">
                        <button
                            @click="filterOpen = !filterOpen"
                            class="flex h-8 w-full items-center justify-between gap-2 rounded-md border border-border bg-background px-3 text-[12.5px] font-medium text-foreground transition-colors hover:bg-muted/60"
                        >
                            <div class="flex items-center gap-1.5">
                                <SlidersHorizontal class="h-3.5 w-3.5 text-muted-foreground" />
                                <span>{{ currentStatusLabel }}</span>
                            </div>
                            <svg class="h-3.5 w-3.5 text-muted-foreground transition-transform" :class="filterOpen && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                        </button>

                        <div v-if="filterOpen" class="fixed inset-0 z-40" @click="filterOpen = false" />

                        <Transition
                            enter-from-class="opacity-0 -translate-y-1"
                            enter-active-class="transition duration-100"
                            leave-to-class="opacity-0 -translate-y-1"
                            leave-active-class="transition duration-100"
                        >
                            <div v-if="filterOpen"
                                 class="absolute left-0 right-0 top-full z-50 mt-1 overflow-hidden rounded-md border border-border bg-card shadow-md"
                            >
                                <button
                                    v-for="s in statusOptions"
                                    :key="s.value"
                                    @click="setStatus(s.value)"
                                    class="flex w-full items-center justify-between px-3 py-2 text-left text-[12.5px] transition-colors hover:bg-muted/60"
                                    :class="store.filters.status === s.value ? 'font-semibold text-foreground' : 'text-muted-foreground'"
                                >
                                    {{ s.label }}
                                    <Check v-if="store.filters.status === s.value" class="h-3.5 w-3.5 text-primary" />
                                </button>
                            </div>
                        </Transition>
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
                         @close="store.closeActive()" />
        </div>
    </AppLayout>
</template>
