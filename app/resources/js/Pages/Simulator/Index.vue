<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, nextTick, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { RotateCcw, Send, Bot, User, ChevronDown } from 'lucide-vue-next';

const ticket    = ref(null);
const messages  = ref([]);
const input     = ref('');
const sending   = ref(false);
const resetting = ref(false);
const atBottom  = ref(true);
const chatEl    = ref(null);
let   pollTimer = null;

const statusLabels = { menu:'No menu', queued:'Na fila', open:'Em atendimento', pending:'Aguardando', closed:'Encerrado' };
const statusColors = { menu:'secondary', queued:'secondary', open:'default', pending:'secondary', closed:'outline' };

async function load() {
    try {
        const { data } = await axios.get('/api/v1/dev/simulate');
        applyData(data.data);
    } catch (_) {}
}

async function send() {
    const text = input.value.trim();
    if (!text || sending.value) return;
    sending.value = true;
    input.value   = '';
    try {
        const { data } = await axios.post('/api/v1/dev/simulate/send', { message: text });
        applyData(data.data);
    } catch (_) {
        await load();
    } finally {
        sending.value = false;
    }
}

async function reset() {
    resetting.value = true;
    try {
        const { data } = await axios.post('/api/v1/dev/simulate/reset');
        applyData(data.data);
    } finally {
        resetting.value = false;
    }
}

function applyData(d) {
    ticket.value   = d.ticket   ?? null;
    messages.value = d.messages ?? [];
    if (atBottom.value) scrollBottom();
}

function scrollBottom(smooth = false) {
    nextTick(() => {
        if (!chatEl.value) return;
        chatEl.value.scrollTo({ top: chatEl.value.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
    });
}

function onScroll() {
    if (!chatEl.value) return;
    const { scrollTop, scrollHeight, clientHeight } = chatEl.value;
    atBottom.value = scrollHeight - scrollTop - clientHeight < 60;
}

function onEnter(e) {
    if (!e.shiftKey) { e.preventDefault(); send(); }
}

function formatTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

onMounted(() => { load(); pollTimer = setInterval(load, 2000); });
onBeforeUnmount(() => clearInterval(pollTimer));
</script>

<template>
    <Head title="Simulador — Vora" />
    <AppLayout>
        <div class="flex h-[calc(100vh-56px)] flex-col bg-background relative">

            <!-- Header -->
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-border bg-card px-5 py-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-muted">
                        <User class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div>
                        <p class="text-[13px] font-semibold text-foreground">Cliente Teste</p>
                        <p class="text-[11px] text-muted-foreground font-mono">+55 00 000-000-0000</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <template v-if="ticket">
                        <span class="text-[11px] font-mono text-muted-foreground">#{{ ticket.protocol }}</span>
                        <Badge :variant="statusColors[ticket.status] || 'outline'" class="text-[11px]">
                            {{ statusLabels[ticket.status] || ticket.status }}
                        </Badge>
                        <span v-if="ticket.sector" class="hidden sm:inline text-[11px] text-muted-foreground">{{ ticket.sector }}</span>
                    </template>
                    <span v-else class="text-[11px] italic text-muted-foreground">Sem conversa ativa</span>

                    <Button variant="ghost" size="sm" :disabled="resetting" @click="reset" class="gap-1.5 text-[12px]">
                        <RotateCcw class="h-3.5 w-3.5" :class="resetting && 'animate-spin'" />
                        Resetar
                    </Button>
                </div>
            </div>

            <!-- Messages -->
            <div ref="chatEl"
                 class="flex-1 overflow-y-auto space-y-1.5 px-4 py-4"
                 @scroll="onScroll">

                <!-- Empty state -->
                <div v-if="!messages.length && !sending"
                     class="flex h-full flex-col items-center justify-center gap-3 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted">
                        <Bot class="h-6 w-6 text-muted-foreground" />
                    </div>
                    <div>
                        <p class="text-[13px] font-semibold text-foreground">Nenhuma conversa ainda</p>
                        <p class="mt-1 text-[12px] text-muted-foreground">
                            Digite uma mensagem para iniciar o fluxo real do bot.
                        </p>
                    </div>
                </div>

                <template v-for="(msg, i) in messages" :key="msg.id">
                    <!-- Day separator -->
                    <div v-if="i === 0 || formatDate(messages[i - 1].created_at) !== formatDate(msg.created_at)"
                         class="flex items-center justify-center py-2">
                        <span class="rounded-full bg-muted px-3 py-0.5 text-[10.5px] text-muted-foreground">
                            {{ formatDate(msg.created_at) }}
                        </span>
                    </div>

                    <!-- Bot / outbound (left) -->
                    <div v-if="msg.direction === 'outbound'" class="flex items-end gap-2">
                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-muted mb-0.5">
                            <Bot class="h-3 w-3 text-muted-foreground" />
                        </div>
                        <div class="max-w-[72%]">
                            <div class="rounded-2xl rounded-bl-sm bg-card border border-border px-3.5 py-2 shadow-sm">
                                <p class="whitespace-pre-wrap text-[13px] leading-relaxed text-foreground">{{ msg.body }}</p>
                            </div>
                            <p class="mt-0.5 pl-1 text-[10.5px] text-muted-foreground">{{ formatTime(msg.created_at) }}</p>
                        </div>
                    </div>

                    <!-- Client / inbound (right) -->
                    <div v-else class="flex items-end justify-end gap-2">
                        <div class="max-w-[72%]">
                            <div class="rounded-2xl rounded-br-sm bg-primary px-3.5 py-2 shadow-sm">
                                <p class="whitespace-pre-wrap text-[13px] leading-relaxed text-primary-foreground">{{ msg.body }}</p>
                            </div>
                            <p class="mt-0.5 pr-1 text-right text-[10.5px] text-muted-foreground">{{ formatTime(msg.created_at) }}</p>
                        </div>
                    </div>
                </template>

                <!-- Typing indicator while sending -->
                <div v-if="sending" class="flex items-end justify-end gap-2">
                    <div class="max-w-[72%]">
                        <div class="rounded-2xl rounded-br-sm bg-primary/50 px-4 py-3">
                            <div class="flex gap-1 items-center">
                                <span v-for="n in 3" :key="n"
                                      class="h-1.5 w-1.5 rounded-full bg-primary-foreground animate-bounce"
                                      :style="{ animationDelay: `${(n - 1) * 0.18}s` }" />
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Scroll to bottom -->
            <Transition enter-from-class="opacity-0 translate-y-2" enter-active-class="transition duration-150"
                        leave-to-class="opacity-0 translate-y-2"   leave-active-class="transition duration-150">
                <button v-if="!atBottom" @click="scrollBottom(true)"
                        class="absolute bottom-[76px] right-5 flex h-8 w-8 items-center justify-center rounded-full bg-card border border-border shadow text-muted-foreground hover:text-foreground transition-colors">
                    <ChevronDown class="h-4 w-4" />
                </button>
            </Transition>

            <!-- Input bar -->
            <div class="shrink-0 border-t border-border bg-card px-4 py-3">
                <div class="flex items-end gap-2">
                    <textarea
                        v-model="input"
                        rows="1"
                        placeholder="Digite como se fosse o cliente…"
                        class="flex-1 resize-none rounded-xl border border-border bg-background px-3.5 py-2.5 text-[13px] text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring min-h-[42px] max-h-[120px] overflow-auto"
                        @keydown.enter="onEnter"
                        :disabled="sending"
                    />
                    <Button @click="send" :disabled="!input.trim() || sending"
                            size="icon" class="h-[42px] w-[42px] shrink-0 rounded-xl">
                        <Send class="h-4 w-4" />
                    </Button>
                </div>
                <p class="mt-1.5 text-[11px] text-muted-foreground">
                    Enter envia · Shift+Enter nova linha · Testa banco, tickets e menu engine real
                </p>
            </div>

        </div>
    </AppLayout>
</template>
