<script setup>
import { Head } from '@inertiajs/vue3';
import { Motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/vora/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/Components/ui/tabs';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog';
import { Avatar, AvatarImage, AvatarFallback } from '@/Components/ui/avatar';
import { onBeforeUnmount, onMounted, ref, computed } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Plus, QrCode, RefreshCw, Trash2, Smartphone, CheckCircle2, Building2 } from 'lucide-vue-next';
import { Switch } from '@/Components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';

/* ---------- WhatsApp sessions ---------- */
const sessions = ref([]);
const loading = ref(true);
const newName = ref('');
const creating = ref(false);
const qrDialogOpen = ref(false);
const qrSession = ref(null);
let qrPollTimer = null;

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
    if (!newName.value.trim() || sessions.value.length >= 1) return;
    creating.value = true;
    try {
        const { data } = await axios.post('/api/v1/whatsapp/sessions', {
            instance_name: newName.value,
            is_primary: true,
        });
        newName.value = '';
        await load();
        openQr(data.data);
    } finally {
        creating.value = false;
    }
}

async function openQr(session) {
    qrSession.value = session;
    qrDialogOpen.value = true;
    await refreshQr();
    startQrPolling();
}

async function showQr(id) {
    const session = sessions.value.find((s) => s.id === id);
    await openQr(session || { id });
}

async function refreshQr() {
    if (!qrSession.value?.id) return;
    const { data } = await axios.get(`/api/v1/whatsapp/sessions/${qrSession.value.id}/qr`);
    qrSession.value = { ...qrSession.value, ...data.data };
    if (qrSession.value.state === 'connected') {
        await load();
        stopQrPolling();
    }
}

function startQrPolling() {
    stopQrPolling();
    qrPollTimer = window.setInterval(refreshQr, 5000);
}

function stopQrPolling() {
    if (qrPollTimer) {
        window.clearInterval(qrPollTimer);
        qrPollTimer = null;
    }
}

function onQrDialogOpen(open) {
    qrDialogOpen.value = open;
    if (!open) stopQrPolling();
}

const qrSrc = computed(() => {
    const raw = qrSession.value?.qr_code;
    if (!raw) return '';
    return raw.startsWith('data:image') ? raw : `data:image/png;base64,${raw}`;
});

async function reconnect(id) { await axios.post(`/api/v1/whatsapp/sessions/${id}/reconnect`); await load(); }
async function reconnectQr() {
    if (!qrSession.value?.id) return;
    await axios.post(`/api/v1/whatsapp/sessions/${qrSession.value.id}/reconnect`);
    await refreshQr();
    startQrPolling();
}
async function destroy(id)   { if (confirm('Remover esta sessão WhatsApp?')) { await axios.delete(`/api/v1/whatsapp/sessions/${id}`); await load(); } }

const stateVariant = (state) => ({
    connected: 'default',
    open: 'default',
    connecting: 'secondary',
    disconnected: 'outline',
    close: 'outline',
    qr_pending: 'secondary',
    qr: 'secondary',
}[state] || 'outline');

const stateLabel = (state) => ({
    connected: 'Conectado',
    open: 'Conectado',
    connecting: 'Conectando',
    disconnected: 'Desconectado',
    close: 'Desconectado',
    qr_pending: 'Aguardando QR',
    qr: 'Aguardando QR',
}[state] || state);

/* ---------- Tenant + integrations ---------- */
const emptyAddress = () => ({
    zip: '', street: '', number: '', complement: '', district: '', city: '', state: '',
});
const tenantForm = ref({
    name: '', document: '', whatsapp: '', email: '',
    address: emptyAddress(), logo_url: null,
});
const integrations = ref(null);
const gateway = ref({ type: 'evolution', config: {} });
const gatewaySaving = ref(false);
const tenantSaving = ref(false);
const tenantError = ref(null);
const logoUploading = ref(false);
const logoInput = ref(null);

const tenantInitials = computed(() => (tenantForm.value.name || '?')
    .split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase());

const whatsappModel = computed({
    get: () => tenantForm.value.whatsapp,
    set: (v) => { tenantForm.value.whatsapp = String(v ?? '').replace(/\D/g, ''); },
});

const defaultEvolutionEvents = 'MESSAGES_UPSERT, MESSAGES_UPDATE, CONNECTION_UPDATE, QRCODE_UPDATED';

function normalizeGateway(value = null) {
    const type = ['evolution', 'webhook'].includes(value?.type) ? value.type : 'evolution';
    const config = value?.config || {};

    if (type === 'evolution') {
        return {
            type,
            config: {
                base_url: config.base_url || integrations.value?.evolution?.url || '',
                api_key: '',
                webhook_url: config.webhook_url || integrations.value?.evolution?.webhook_url || '',
                webhook_events: config.webhook_events || defaultEvolutionEvents,
            },
        };
    }

    return {
        type,
        config: {
            url: config.url || '',
            secret_header: config.secret_header || '',
            secret_value: config.secret_value || '',
            event_mapping: config.event_mapping || '',
        },
    };
}

function onGatewayTypeChange(type) {
    gateway.value = normalizeGateway({ type, config: gateway.value.config });
}

function applyTenant(t) {
    tenantForm.value = {
        name: t.name || '',
        document: t.document || '',
        whatsapp: t.whatsapp || '',
        email: t.email || '',
        address: { ...emptyAddress(), ...(t.address || {}) },
        logo_url: t.logo_url || null,
    };
}

async function loadTenant() {
    try {
        const { data } = await axios.get('/api/v1/tenant');
        applyTenant(data.data.tenant);
        integrations.value = data.data.integrations;
        if (data.data.integrations?.gateway) {
            gateway.value = normalizeGateway(data.data.integrations.gateway);
        }
    } catch (_) {}
}

async function saveTenant() {
    tenantSaving.value = true;
    tenantError.value = null;
    try {
        const { data } = await axios.put('/api/v1/tenant', {
            name: tenantForm.value.name,
            document: tenantForm.value.document,
            whatsapp: tenantForm.value.whatsapp,
            email: tenantForm.value.email,
            address: tenantForm.value.address,
        });
        applyTenant(data.data.tenant);
        toast.success('Dados da empresa atualizados');
    } catch (e) {
        tenantError.value = e.response?.data?.message || 'Falha ao salvar os dados da empresa.';
    } finally {
        tenantSaving.value = false;
    }
}

async function saveGateway() {
    gatewaySaving.value = true;
    try {
        const { data } = await axios.put('/api/v1/tenant/gateway', gateway.value);
        integrations.value = data.data.integrations;
        if (data.data.integrations?.gateway) gateway.value = normalizeGateway(data.data.integrations.gateway);
        toast.success('Gateway atualizado');
    } catch {
        toast.error('Falha ao salvar gateway');
    } finally {
        gatewaySaving.value = false;
    }
}

async function onLogoChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    logoUploading.value = true;
    tenantError.value = null;
    try {
        const fd = new FormData();
        fd.append('logo', file);
        const { data } = await axios.post('/api/v1/tenant/logo', fd);
        applyTenant(data.data.tenant);
        toast.success('Logo atualizada');
    } catch (e) {
        tenantError.value = e.response?.data?.message || 'Falha ao enviar a imagem.';
    } finally {
        logoUploading.value = false;
        if (logoInput.value) logoInput.value.value = '';
    }
}

/* ---------- Bot config ---------- */
const defaultBotForm = () => ({
    enabled: false,
    menu_message: '',
    confirm_message: '',
    invalid_message: '',
    delay_seconds: 1,
    sectors: [],
});
const botForm = ref(defaultBotForm());
const botSaving = ref(false);
const botLoading = ref(false);

function applyBot(b) {
    botForm.value = {
        enabled: !!b.enabled,
        menu_message: b.menu_message || '',
        confirm_message: b.confirm_message || '',
        invalid_message: b.invalid_message || '',
        delay_seconds: b.delay_seconds ?? 1,
        sectors: (b.sectors || []).map(s => ({ ...s })),
    };
}

async function loadBot() {
    botLoading.value = true;
    try {
        const { data } = await axios.get('/api/v1/tenant/bot');
        applyBot(data.data);
    } catch (_) {}
    finally { botLoading.value = false; }
}

async function saveBot() {
    botSaving.value = true;
    try {
        const { data } = await axios.put('/api/v1/tenant/bot', botForm.value);
        applyBot(data.data);
        toast.success('Configurações do bot salvas');
    } catch {
        toast.error('Falha ao salvar configurações do bot');
    } finally {
        botSaving.value = false;
    }
}

function addSector() {
    botForm.value.sectors.push({ key: String(botForm.value.sectors.length + 1), label: '', emoji: '', state: '' });
}

function removeSector(i) {
    botForm.value.sectors.splice(i, 1);
}

onMounted(() => { load(); loadTenant(); loadBot(); });
onBeforeUnmount(stopQrPolling);
</script>

<template>
    <Head title="Configurações — Vora" />
    <AppLayout>
        <div class="mx-auto max-w-5xl px-8 py-8 space-y-6">

            <PageHeader title="Configurações" description="Empresa, sessões WhatsApp e integrações" />

            <Motion :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
                    :transition="{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }">
                <Tabs default-value="company">
                    <TabsList>
                        <TabsTrigger value="company">Empresa</TabsTrigger>
                        <TabsTrigger value="whatsapp">WhatsApp</TabsTrigger>
                        <TabsTrigger value="integrations">Integrações</TabsTrigger>
                        <TabsTrigger value="bot">Bot</TabsTrigger>
                    </TabsList>

                    <!-- ===== Empresa ===== -->
                    <TabsContent value="company">
                        <Card>
                            <CardHeader>
                                <CardTitle>Dados da empresa</CardTitle>
                                <CardDescription>Identidade, contato e endereço da empresa.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form @submit.prevent="saveTenant" class="mx-auto max-w-xl space-y-5">
                                    <!-- Logo -->
                                    <div class="flex items-center gap-4">
                                        <Avatar class="h-16 w-16 rounded-xl">
                                            <AvatarImage v-if="tenantForm.logo_url" :src="tenantForm.logo_url" alt="Logo" />
                                            <AvatarFallback class="rounded-xl text-[16px]">{{ tenantInitials }}</AvatarFallback>
                                        </Avatar>
                                        <div class="space-y-1">
                                            <p class="text-[12.5px] font-medium text-foreground">Foto de perfil</p>
                                            <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp"
                                                   class="hidden" @change="onLogoChange" />
                                            <Button type="button" variant="outline" size="sm" class="mt-1"
                                                    :disabled="logoUploading" @click="logoInput?.click()">
                                                {{ logoUploading ? 'Enviando…' : 'Alterar imagem' }}
                                            </Button>
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-[12px] font-medium text-foreground">Nome da empresa</label>
                                        <Input v-model="tenantForm.name" required placeholder="Minha Empresa Ltda" />
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="space-y-1.5">
                                            <label class="text-[12px] font-medium text-foreground">CNPJ / Documento</label>
                                            <Input v-model="tenantForm.document" placeholder="00.000.000/0001-00" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[12px] font-medium text-foreground">WhatsApp</label>
                                            <Input v-model="whatsappModel" inputmode="numeric" placeholder="11900000000" />
                                        </div>
                                        <div class="space-y-1.5 sm:col-span-2">
                                            <label class="text-[12px] font-medium text-foreground">E-mail de contato</label>
                                            <Input v-model="tenantForm.email" type="email" placeholder="contato@empresa.com" />
                                        </div>
                                    </div>

                                    <div class="border-t border-border pt-5">
                                        <p class="text-[12.5px] font-medium text-foreground mb-3">Endereço</p>
                                        <div class="grid grid-cols-1 sm:grid-cols-6 gap-4">
                                            <div class="space-y-1.5 sm:col-span-2">
                                                <label class="text-[12px] font-medium text-foreground">CEP</label>
                                                <Input v-model="tenantForm.address.zip" placeholder="00000-000" />
                                            </div>
                                            <div class="space-y-1.5 sm:col-span-4">
                                                <label class="text-[12px] font-medium text-foreground">Logradouro</label>
                                                <Input v-model="tenantForm.address.street" placeholder="Rua / Avenida" />
                                            </div>
                                            <div class="space-y-1.5 sm:col-span-2">
                                                <label class="text-[12px] font-medium text-foreground">Número</label>
                                                <Input v-model="tenantForm.address.number" placeholder="123" />
                                            </div>
                                            <div class="space-y-1.5 sm:col-span-4">
                                                <label class="text-[12px] font-medium text-foreground">Complemento</label>
                                                <Input v-model="tenantForm.address.complement" placeholder="Sala, andar, bloco" />
                                            </div>
                                            <div class="space-y-1.5 sm:col-span-3">
                                                <label class="text-[12px] font-medium text-foreground">Bairro</label>
                                                <Input v-model="tenantForm.address.district" placeholder="Bairro" />
                                            </div>
                                            <div class="space-y-1.5 sm:col-span-2">
                                                <label class="text-[12px] font-medium text-foreground">Cidade</label>
                                                <Input v-model="tenantForm.address.city" placeholder="Cidade" />
                                            </div>
                                            <div class="space-y-1.5 sm:col-span-1">
                                                <label class="text-[12px] font-medium text-foreground">UF</label>
                                                <Input v-model="tenantForm.address.state" maxlength="2" placeholder="MT" />
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="tenantError"
                                         class="text-[12.5px] text-destructive bg-destructive/8 border border-destructive/20 rounded-md px-3 py-2">
                                        {{ tenantError }}
                                    </div>

                                    <div class="flex justify-end pt-1">
                                        <Button type="submit" variant="default" :disabled="tenantSaving">
                                            {{ tenantSaving ? 'Salvando…' : 'Salvar' }}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                    </TabsContent>

                    <!-- ===== WhatsApp ===== -->
                    <TabsContent value="whatsapp">
                        <Card>
                            <CardHeader>
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <CardTitle>Sessões WhatsApp</CardTitle>
                                        <CardDescription>
                                            Conecte o número fixo da empresa ao Evolution API.
                                        </CardDescription>
                                    </div>
                                    <div class="flex items-center gap-2 text-[12px] text-muted-foreground tabular-nums">
                                        <Smartphone class="h-3.5 w-3.5" />
                                        {{ sessions.length ? '1 número' : 'Nenhum número' }}
                                    </div>
                                </div>
                            </CardHeader>

                            <CardContent>
                                <div class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-2 mb-5">
                                    <Input v-model="newName" placeholder="Nome da instância (ex: principal)"
                                           :disabled="sessions.length >= 1"
                                           @keydown.enter.prevent="create" />
                                    <Button variant="default" @click="create"
                                            :disabled="creating || !newName.trim() || sessions.length >= 1">
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
                                                <th class="px-4 py-2.5 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Principal</th>
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
                                                        <Button v-if="s.state !== 'connected'" variant="ghost" size="sm"
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
                                                            Conecte o número fixo da empresa para começar.
                                                        </p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                        <Dialog :open="qrDialogOpen" @update:open="onQrDialogOpen">
                            <DialogContent class="max-w-md">
                                <DialogHeader>
                                    <DialogTitle>Conectar WhatsApp</DialogTitle>
                                    <DialogDescription>
                                        Escaneie o QR Code no WhatsApp para concluir a conexão.
                                    </DialogDescription>
                                </DialogHeader>

                                <div class="flex flex-col items-center gap-5 py-2">
                                    <div v-if="qrSession?.state === 'connected'"
                                         class="flex h-52 w-52 items-center justify-center rounded-2xl border border-border bg-muted/30">
                                        <div class="flex flex-col items-center gap-3 text-center">
                                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400 animate-pulse">
                                                <CheckCircle2 class="h-9 w-9" />
                                            </div>
                                            <div>
                                                <p class="text-[14px] font-semibold text-foreground">WhatsApp conectado</p>
                                                <p class="mt-1 text-[12.5px] text-muted-foreground">Sessão pronta para atendimento.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-else-if="qrSrc"
                                         class="rounded-2xl border border-border bg-white p-4 shadow-sm">
                                        <img :src="qrSrc" alt="QR Code do WhatsApp" class="h-56 w-56 object-contain" />
                                    </div>

                                    <div v-else
                                         class="flex h-52 w-52 items-center justify-center rounded-2xl border border-border bg-muted/30">
                                        <div class="flex flex-col items-center gap-3 text-center">
                                            <RefreshCw class="h-8 w-8 animate-spin text-muted-foreground" />
                                        </div>
                                    </div>

                                    <div class="flex w-full justify-end">
                                        <Button variant="outline" size="sm" @click="reconnectQr">
                                            <RefreshCw class="h-3.5 w-3.5" />
                                            Gerar novamente
                                        </Button>
                                    </div>
                                </div>
                            </DialogContent>
                        </Dialog>
                    </TabsContent>

                    <!-- ===== Bot ===== -->
                    <TabsContent value="bot" class="space-y-4">
                        <Card>
                            <CardHeader>
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <CardTitle>Bot de Atendimento</CardTitle>
                                        <CardDescription>Menu automático enviado para novos contatos antes de um atendente assumir.</CardDescription>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[12px] text-muted-foreground">{{ botForm.enabled ? 'Ativo' : 'Inativo' }}</span>
                                        <Switch v-model="botForm.enabled" />
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-5 max-w-2xl">

                                    <!-- Delay -->
                                    <div class="space-y-1.5">
                                        <label class="text-[12px] font-medium text-foreground">Delay entre mensagens (segundos)</label>
                                        <Input v-model.number="botForm.delay_seconds" type="number" min="0" max="10" class="w-32" />
                                    </div>

                                    <!-- Menu message -->
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between">
                                            <label class="text-[12px] font-medium text-foreground">Mensagem do menu</label>
                                            <span class="text-[11px] text-muted-foreground">Variáveis: <code class="bg-muted px-1 rounded">{name}</code> <code class="bg-muted px-1 rounded">{sectors}</code></span>
                                        </div>
                                        <Textarea v-model="botForm.menu_message" class="text-[12.5px] min-h-[100px]" />
                                    </div>

                                    <!-- Confirm message -->
                                    <div class="space-y-1.5">
                                        <label class="text-[12px] font-medium text-foreground">Mensagem de confirmação</label>
                                        <Textarea v-model="botForm.confirm_message" class="text-[12.5px] min-h-[80px]" />
                                    </div>

                                    <!-- Invalid message -->
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between">
                                            <label class="text-[12px] font-medium text-foreground">Mensagem de opção inválida</label>
                                            <span class="text-[11px] text-muted-foreground">Variável: <code class="bg-muted px-1 rounded">{sectors}</code></span>
                                        </div>
                                        <Textarea v-model="botForm.invalid_message" class="text-[12.5px] min-h-[70px]" />
                                    </div>

                                    <!-- Sectors -->
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <label class="text-[12px] font-medium text-foreground">Setores do menu</label>
                                            <Button type="button" variant="outline" size="sm" @click="addSector">
                                                <Plus class="h-3.5 w-3.5" />
                                                Adicionar
                                            </Button>
                                        </div>
                                        <div class="border border-border rounded-lg overflow-hidden">
                                            <table class="w-full text-[12.5px]">
                                                <thead>
                                                    <tr class="bg-muted/40 border-b border-border">
                                                        <th class="px-3 py-2 text-left text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Opção</th>
                                                        <th class="px-3 py-2 text-left text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Emoji</th>
                                                        <th class="px-3 py-2 text-left text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Rótulo</th>
                                                        <th class="px-3 py-2 text-left text-[11px] font-medium text-muted-foreground uppercase tracking-wider">State (interno)</th>
                                                        <th class="px-3 py-2 text-right text-[11px] font-medium text-muted-foreground uppercase tracking-wider"></th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-border bg-card">
                                                    <tr v-for="(sector, i) in botForm.sectors" :key="i">
                                                        <td class="px-3 py-1.5">
                                                            <Input v-model="sector.key" class="w-14 h-7 text-[12px]" placeholder="1" />
                                                        </td>
                                                        <td class="px-3 py-1.5">
                                                            <Input v-model="sector.emoji" class="w-16 h-7 text-[14px]" placeholder="💬" />
                                                        </td>
                                                        <td class="px-3 py-1.5">
                                                            <Input v-model="sector.label" class="h-7 text-[12px]" placeholder="Suporte" />
                                                        </td>
                                                        <td class="px-3 py-1.5">
                                                            <Input v-model="sector.state" class="h-7 text-[12px] font-mono" placeholder="support" />
                                                        </td>
                                                        <td class="px-3 py-1.5 text-right">
                                                            <Button type="button" variant="ghost" size="sm"
                                                                    class="h-7 w-7 p-0 text-destructive hover:text-destructive"
                                                                    @click="removeSector(i)">
                                                                <Trash2 class="h-3.5 w-3.5" />
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                    <tr v-if="!botForm.sectors.length">
                                                        <td colspan="5" class="py-6 text-center text-[12.5px] text-muted-foreground">
                                                            Nenhum setor configurado. Clique em "Adicionar".
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="flex justify-end pt-1">
                                        <Button type="button" variant="default" :disabled="botSaving" @click="saveBot">
                                            {{ botSaving ? 'Salvando…' : 'Salvar bot' }}
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- ===== Integrações ===== -->
                    <TabsContent value="integrations" class="space-y-4">

                        <!-- Gateway de Mensagens -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Gateway de Mensagens</CardTitle>
                                <CardDescription>
                                    Selecione e configure o provedor de envio de mensagens WhatsApp.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-4 max-w-xl">
                                    <div class="space-y-1.5">
                                        <label class="text-[12px] font-medium text-foreground">Tipo</label>
                                        <Select :model-value="gateway.type" @update:model-value="onGatewayTypeChange">
                                            <SelectTrigger class="w-full">
                                                <SelectValue placeholder="Selecione o gateway" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="evolution">Evolution API</SelectItem>
                                                <SelectItem value="webhook">Webhook Genérico</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <!-- Evolution API -->
                                    <template v-if="gateway.type === 'evolution'">
                                        <div class="space-y-3">
                                            <div class="space-y-1.5">
                                                <label class="text-[12px] font-medium text-foreground">Endpoint da Evolution</label>
                                                <Input v-model="gateway.config.base_url" placeholder="https://evo.seudominio.com" />
                                            </div>
                                            <div class="space-y-1.5">
                                                <div class="flex items-center justify-between gap-3">
                                                    <label class="text-[12px] font-medium text-foreground">Chave de API</label>
                                                    <Badge :variant="integrations?.evolution?.api_key_set ? 'default' : 'outline'">
                                                        {{ integrations?.evolution?.api_key_set ? 'Configurada' : 'Não configurada' }}
                                                    </Badge>
                                                </div>
                                                <Input v-model="gateway.config.api_key" type="password"
                                                       placeholder="Deixe em branco para manter a chave atual" />
                                            </div>
                                            <div class="space-y-1.5">
                                                <label class="text-[12px] font-medium text-foreground">URL do webhook</label>
                                                <Input v-model="gateway.config.webhook_url" placeholder="https://seudominio.com/api/v1/webhooks/evolution" />
                                            </div>
                                            <div class="space-y-1.5">
                                                <label class="text-[12px] font-medium text-foreground">Eventos do webhook</label>
                                                <Textarea v-model="gateway.config.webhook_events"
                                                          placeholder="MESSAGES_UPSERT, MESSAGES_UPDATE, CONNECTION_UPDATE, QRCODE_UPDATED"
                                                          class="font-mono text-[12px] min-h-[80px]" />
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Generic webhook: editable -->
                                    <template v-else>
                                        <div class="space-y-3">
                                            <div class="space-y-1.5">
                                                <label class="text-[12px] font-medium text-foreground">URL de envio</label>
                                                <Input v-model="gateway.config.url" placeholder="https://gateway.example.com/send" />
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div class="space-y-1.5">
                                                    <label class="text-[12px] font-medium text-foreground">Header secreto</label>
                                                    <Input v-model="gateway.config.secret_header" placeholder="X-Secret" />
                                                </div>
                                                <div class="space-y-1.5">
                                                    <label class="text-[12px] font-medium text-foreground">Valor do header</label>
                                                    <Input v-model="gateway.config.secret_value" placeholder="meu-secret" type="password" />
                                                </div>
                                            </div>
                                            <div class="space-y-1.5">
                                                <label class="text-[12px] font-medium text-foreground">Mapeamento de eventos (JSON)</label>
                                                <Textarea v-model="gateway.config.event_mapping"
                                                          placeholder='{"MESSAGE_RECEIVED": "message.upsert"}'
                                                          class="font-mono text-[12px] min-h-[80px]" />
                                            </div>
                                        </div>
                                    </template>

                                    <div class="flex justify-end">
                                        <Button variant="default" :disabled="gatewaySaving" @click="saveGateway">
                                            {{ gatewaySaving ? 'Salvando…' : 'Salvar gateway' }}
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                    </TabsContent>
                </Tabs>
            </Motion>

        </div>
    </AppLayout>
</template>
