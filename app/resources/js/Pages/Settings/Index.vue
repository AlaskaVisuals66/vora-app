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
import { Avatar, AvatarImage, AvatarFallback } from '@/Components/ui/avatar';
import { onMounted, ref, computed } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Plus, QrCode, RefreshCw, Trash2, Smartphone, CheckCircle2, Building2 } from 'lucide-vue-next';
import SectorAiSettings from '@/Components/vora/SectorAiSettings.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';

/* ---------- WhatsApp sessions ---------- */
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
            gateway.value = data.data.integrations.gateway;
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
        if (data.data.integrations?.gateway) gateway.value = data.data.integrations.gateway;
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

onMounted(() => { load(); loadTenant(); });
</script>

<template>
    <Head title="Configurações — Vora" />
    <AppLayout title="Configurações">
        <div class="px-8 py-8 space-y-6 max-w-[1400px] mx-auto">

            <PageHeader title="Configurações" description="Empresa, sessões WhatsApp e integrações" />

            <Motion :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
                    :transition="{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }">
                <Tabs default-value="company">
                    <TabsList>
                        <TabsTrigger value="company">Empresa</TabsTrigger>
                        <TabsTrigger value="whatsapp">WhatsApp</TabsTrigger>
                        <TabsTrigger value="integrations">Integrações</TabsTrigger>
                    </TabsList>

                    <!-- ===== Empresa ===== -->
                    <TabsContent value="company">
                        <Card>
                            <CardHeader>
                                <CardTitle>Dados da empresa</CardTitle>
                                <CardDescription>Identidade, contato e endereço da empresa.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form @submit.prevent="saveTenant" class="space-y-5 max-w-xl">
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
                                        <Select v-model="gateway.type">
                                            <SelectTrigger class="w-full">
                                                <SelectValue placeholder="Selecione o gateway" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="evolution">Evolution API</SelectItem>
                                                <SelectItem value="webhook">Webhook Genérico</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <!-- Evolution: read-only -->
                                    <template v-if="gateway.type === 'evolution'">
                                        <dl class="divide-y divide-border border border-border rounded-lg overflow-hidden">
                                            <div class="flex items-center justify-between gap-4 px-4 py-3">
                                                <dt class="text-[12.5px] text-muted-foreground">Endpoint</dt>
                                                <dd class="font-mono text-[12.5px] text-foreground truncate">
                                                    {{ integrations?.evolution?.url || '—' }}
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-4 px-4 py-3">
                                                <dt class="text-[12.5px] text-muted-foreground">Chave de API</dt>
                                                <dd>
                                                    <Badge :variant="integrations?.evolution?.api_key_set ? 'default' : 'outline'">
                                                        {{ integrations?.evolution?.api_key_set ? 'Configurada' : 'Não configurada' }}
                                                    </Badge>
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-4 px-4 py-3">
                                                <dt class="text-[12.5px] text-muted-foreground">Webhook</dt>
                                                <dd class="font-mono text-[12.5px] text-foreground truncate">
                                                    {{ integrations?.evolution?.webhook_url || '—' }}
                                                </dd>
                                            </div>
                                        </dl>
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

                        <!-- n8n por Setor -->
                        <Card>
                            <CardHeader>
                                <CardTitle>n8n por Setor</CardTitle>
                                <CardDescription>
                                    Configure o bot de IA e ações n8n para cada setor.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <SectorAiSettings />
                            </CardContent>
                        </Card>

                    </TabsContent>
                </Tabs>
            </Motion>

        </div>
    </AppLayout>
</template>
