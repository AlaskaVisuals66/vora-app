<script setup>
import { Head } from '@inertiajs/vue3';
import { Motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/vora/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { onMounted, ref, computed } from 'vue';
import axios from 'axios';
import { Plus, QrCode, RefreshCw, Trash2, Smartphone, CheckCircle2 } from 'lucide-vue-next';

const sessions = ref([]);
const loading = ref(true);
const newName = ref('');
const creating = ref(false);

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/v1/whatsapp/sessions');
        sessions.value = data.data || data;
    } finally {
        loading.value = false;
    }
}

async function create() {
    if (!newName.value.trim()) return;
    creating.value = true;
    try {
        await axios.post('/api/v1/whatsapp/sessions', { instance_name: newName.value, is_primary: !sessions.value.length });
        newName.value = '';
        await load();
    } finally {
        creating.value = false;
    }
}

async function showQr(id) {
    const { data } = await axios.get(`/api/v1/whatsapp/sessions/${id}/qr`);
    window.open(data.qr_url || `data:image/png;base64,${data.qr_code}`, '_blank');
}

async function reconnect(id) { await axios.post(`/api/v1/whatsapp/sessions/${id}/reconnect`); await load(); }
async function destroy(id)   { if (confirm('Remover esta sessão WhatsApp?')) { await axios.delete(`/api/v1/whatsapp/sessions/${id}`); await load(); } }

onMounted(load);

const stateVariant = (state) => ({
    open: 'default',
    connecting: 'secondary',
    close: 'outline',
    qr: 'default',
}[state] || 'outline');

const stateLabel = (state) => ({
    open: 'Conectado',
    connecting: 'Conectando',
    close: 'Desconectado',
    qr: 'Aguardando QR',
}[state] || state);
</script>

<template>
    <Head title="Configurações — Vora" />
    <AppLayout title="Configurações">
        <div class="px-8 py-8 space-y-6 max-w-[1400px]">

                <PageHeader title="Configurações" description="Sessões WhatsApp e parâmetros do tenant" />

                <Motion :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
                        :transition="{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }">
                    <Card>
                        <CardHeader>
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <CardTitle>Sessões WhatsApp</CardTitle>
                                    <CardDescription>
                                        Conecte uma ou mais instâncias do Evolution API à sua conta.
                                    </CardDescription>
                                </div>
                                <div class="flex items-center gap-2 text-[12px] text-muted-foreground tabular-nums">
                                    <Smartphone class="h-3.5 w-3.5" />
                                    {{ sessions.length }} sessã{{ sessions.length === 1 ? 'o' : 'ões' }}
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent>
                            <div class="flex gap-2 mb-5">
                                <Input v-model="newName" placeholder="Nome da instância (ex: principal)"
                                       class="flex-1" @keydown.enter.prevent="create" />
                                <Button variant="default" @click="create"
                                        :disabled="creating || !newName.trim()">
                                    <Plus class="h-4 w-4" />
                                    Conectar número
                                </Button>
                            </div>

                            <div class="border border-border rounded-lg overflow-hidden">
                                <table class="w-full text-[13px]">
                                    <thead>
                                        <tr class="bg-muted/40 border-b border-border">
                                            <th class="px-4 py-2.5 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Instância</th>
                                            <th class="px-4 py-2.5 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Estado</th>
                                            <th class="px-4 py-2.5 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Primária</th>
                                            <th class="px-4 py-2.5 text-right font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border bg-card">
                                        <tr v-for="s in sessions" :key="s.id"
                                            class="hover:bg-muted/30 transition-colors">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="h-7 w-7 rounded-md bg-muted flex items-center justify-center">
                                                        <Smartphone class="h-3.5 w-3.5 text-muted-foreground" />
                                                    </div>
                                                    <span class="font-mono text-[12.5px] text-foreground">{{ s.instance_name }}</span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <Badge :variant="stateVariant(s.state)">{{ stateLabel(s.state) }}</Badge>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span v-if="s.is_primary" class="inline-flex items-center gap-1 text-[12px] text-foreground font-medium">
                                                    <CheckCircle2 class="h-3.5 w-3.5" />
                                                    Sim
                                                </span>
                                                <span v-else class="text-muted-foreground">—</span>
                                            </td>
                                            <td class="px-4 py-2.5 text-right">
                                                <div class="inline-flex items-center gap-1">
                                                    <Button v-if="s.state !== 'open'" variant="ghost" size="sm"
                                                            @click="showQr(s.id)">
                                                        <QrCode class="h-3.5 w-3.5" />
                                                        QR
                                                    </Button>
                                                    <Button variant="ghost" size="sm" @click="reconnect(s.id)">
                                                        <RefreshCw class="h-3.5 w-3.5" />
                                                        Reconectar
                                                    </Button>
                                                    <Button variant="ghost" size="sm"
                                                            class="text-destructive hover:text-destructive"
                                                            @click="destroy(s.id)">
                                                        <Trash2 class="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!loading && !sessions.length">
                                            <td colspan="4" class="text-center py-12">
                                                <div class="flex flex-col items-center">
                                                    <div class="h-12 w-12 rounded-xl bg-muted flex items-center justify-center mb-3">
                                                        <Smartphone class="h-5 w-5 text-muted-foreground" />
                                                    </div>
                                                    <h3 class="text-[14px] font-semibold text-foreground">Nenhuma sessão configurada</h3>
                                                    <p class="text-[12.5px] text-muted-foreground mt-1">
                                                        Adicione uma instância acima para começar.
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </Motion>

        </div>
    </AppLayout>
</template>
