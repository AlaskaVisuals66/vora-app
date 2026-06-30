<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/vora/PageHeader.vue';
import { Card, CardContent } from '@/Components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/Components/ui/dialog';
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { useAuth } from '@/Composables/useAuth';
import { MessageSquarePlus, Search, Phone, Loader2, UserPlus, Plus, DownloadCloud, Inbox } from 'lucide-vue-next';

const { isAdmin } = useAuth();

const clients = ref([]);
const loading = ref(true);
const search = ref('');
const page = ref(1);
const lastPage = ref(1);

const sessions = ref([]);          // os números (instâncias) do tenant
const activeSession = ref(null);   // null = todos os números
const importing = ref(false);

const sessionLabel = (s) => s.display_name || s.phone_number || s.instance_name || 'Número';

const dialogOpen = ref(false);
const mode = ref('new');  // 'new' | 'pick'
const newPhone = ref('');
const newName = ref('');
const pickedId = ref(null);
const starting = ref(false);

const contactDialogOpen = ref(false);
const contactName = ref('');
const contactPhone = ref('');
const contactEmail = ref('');
const savingContact = ref(false);
const contactDigits = computed(() => (contactPhone.value || '').replace(/\D+/g, ''));

const digitsOnly = computed(() => (newPhone.value || '').replace(/\D+/g, ''));

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { page.value = 1; load(); }, 300);
});

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/v1/clients', {
            params: {
                search: search.value || undefined,
                session_id: activeSession.value || undefined,
                page: page.value,
                per_page: 50,
            },
        });
        clients.value = data.data || [];
        lastPage.value = data.last_page || 1;
    } catch (e) {
        toast.error('Falha ao carregar contatos.');
    } finally {
        loading.value = false;
    }
}

function initials(name) {
    return (name || '?').split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
}

function openNew() {
    mode.value = 'new';
    newPhone.value = '';
    newName.value = '';
    pickedId.value = null;
    dialogOpen.value = true;
}

function pickContact(c) {
    mode.value = 'pick';
    pickedId.value = c.id;
    newName.value = c.name;
    newPhone.value = c.phone;
    dialogOpen.value = true;
}

async function startChat() {
    if (mode.value === 'new' && digitsOnly.value.length < 8) {
        toast.error('Informe um telefone válido (mínimo 8 dígitos).');
        return;
    }
    starting.value = true;
    try {
        const payload = mode.value === 'pick'
            ? { client_id: pickedId.value }
            : { phone: digitsOnly.value, name: newName.value || undefined };
        const { data } = await axios.post('/api/v1/clients/start-conversation', payload);
        dialogOpen.value = false;
        if (data.reused) toast.info('Conversa em andamento — abrindo.');
        else toast.success('Conversa iniciada.');
        router.visit(`/conversations/${data.ticket_id}`);
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Falha ao iniciar conversa.');
    } finally {
        starting.value = false;
    }
}

function openAddContact() {
    contactName.value = '';
    contactPhone.value = '';
    contactEmail.value = '';
    contactDialogOpen.value = true;
}

async function saveContact() {
    if (contactDigits.value.length < 8) {
        toast.error('Telefone inválido.');
        return;
    }
    savingContact.value = true;
    try {
        await axios.post('/api/v1/clients', {
            name: contactName.value || undefined,
            phone: contactDigits.value,
            email: contactEmail.value || undefined,
        });
        contactDialogOpen.value = false;
        toast.success('Contato salvo.');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Falha ao salvar contato.');
    } finally {
        savingContact.value = false;
    }
}

async function loadSessions() {
    try {
        const { data } = await axios.get('/api/v1/whatsapp/sessions');
        sessions.value = data.data || [];
    } catch (e) {
        sessions.value = [];
    }
}

function selectSession(id) {
    activeSession.value = id;
    page.value = 1;
    load();
}

async function importContacts() {
    importing.value = true;
    try {
        const { data } = await axios.post('/api/v1/clients/import-contacts',
            activeSession.value ? { session_id: activeSession.value } : {});
        toast.success(data.message || 'Importação iniciada.');
        // Dá um tempo pro job processar e recarrega.
        setTimeout(load, 4000);
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Falha ao importar contatos.');
    } finally {
        importing.value = false;
    }
}

onMounted(() => {
    loadSessions();
    load();
});
</script>

<template>
    <Head title="Contatos" />
    <AppLayout>
        <div class="mx-auto w-full max-w-3xl p-4 sm:p-6">
            <PageHeader title="Contatos" description="Todos os contatos do tenant. Inicie uma conversa por telefone ou da lista.">
                <template #actions>
                    <Button v-if="isAdmin" @click="importContacts" :disabled="importing" variant="outline" class="gap-2">
                        <Loader2 v-if="importing" class="h-4 w-4 animate-spin" />
                        <DownloadCloud v-else class="h-4 w-4" />
                        <span class="hidden sm:inline">{{ importing ? 'Importando…' : 'Importar contatos' }}</span>
                    </Button>
                    <Button @click="openAddContact" variant="outline" class="gap-2">
                        <Plus class="h-4 w-4" />
                        <span class="hidden sm:inline">Novo contato</span>
                    </Button>
                    <Button @click="openNew" class="gap-2">
                        <MessageSquarePlus class="h-4 w-4" />
                        <span class="hidden sm:inline">Nova conversa</span>
                    </Button>
                </template>
            </PageHeader>

            <div class="relative mb-4">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                <Input v-model="search" type="text" placeholder="Nome, telefone ou e-mail" class="pl-10 h-10" inputmode="search" />
            </div>

            <!-- Filtro por número (instância) — separa os contatos em vez de tudo junto -->
            <div v-if="sessions.length" class="mb-4 flex gap-2 overflow-x-auto pb-1">
                <button
                    @click="selectSession(null)"
                    class="shrink-0 inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[12.5px] font-medium transition-colors"
                    :class="!activeSession ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-muted-foreground hover:bg-muted/60'">
                    <Inbox class="h-3.5 w-3.5" /> Todos
                </button>
            </div>

            <Card>
                <CardContent class="p-0">
                    <div v-if="loading" class="flex items-center justify-center py-10 text-sm text-muted-foreground">
                        <Loader2 class="h-4 w-4 mr-2 animate-spin" /> Carregando…
                    </div>
                    <ul v-else-if="clients.length" class="divide-y divide-border">
                        <li v-for="c in clients" :key="c.id"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-muted/40 transition-colors">
                            <Avatar class="h-9 w-9 shrink-0"><AvatarImage v-if="c.avatar_url" :src="c.avatar_url" :alt="c.name" /><AvatarFallback>{{ initials(c.name || c.phone) }}</AvatarFallback></Avatar>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[14px] font-medium text-foreground">{{ c.name || c.phone }}</div>
                                <div class="flex items-center gap-1.5 text-[12px] text-muted-foreground font-mono flex-wrap">
                                    <Phone class="h-3 w-3" /> {{ c.phone }}
                                    <span v-if="c.tickets_count" class="ml-1 inline-flex items-center rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium tabular-nums">{{ c.tickets_count }} tickets</span>
                                    <span v-for="s in (c.sessions || [])" :key="s.id"
                                          class="inline-flex items-center rounded-full bg-primary/10 text-primary px-1.5 py-0.5 text-[10px] font-medium font-sans">
                                        {{ sessionLabel(s) }}
                                    </span>
                                </div>
                            </div>
                            <Button size="sm" variant="ghost" class="shrink-0" @click="pickContact(c)">
                                <MessageSquarePlus class="h-4 w-4" />
                            </Button>
                        </li>
                    </ul>
                    <div v-else class="px-6 py-12 text-center text-sm text-muted-foreground">
                        Nenhum contato encontrado.
                    </div>
                </CardContent>
            </Card>

            <div v-if="lastPage > 1" class="mt-4 flex items-center justify-center gap-2 text-sm">
                <Button variant="outline" size="sm" :disabled="page <= 1" @click="page--; load()">Anterior</Button>
                <span class="text-muted-foreground">Página {{ page }} de {{ lastPage }}</span>
                <Button variant="outline" size="sm" :disabled="page >= lastPage" @click="page++; load()">Próxima</Button>
            </div>

            <!-- Dialogo: novo contato (sem iniciar conversa) -->
            <Dialog v-model:open="contactDialogOpen">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <UserPlus class="h-5 w-5" />
                            Novo contato
                        </DialogTitle>
                        <DialogDescription>Adiciona um contato sem iniciar conversa.</DialogDescription>
                    </DialogHeader>
                    <div class="space-y-3">
                        <div class="space-y-2">
                            <Label for="c-name">Nome</Label>
                            <Input id="c-name" v-model="contactName" placeholder="Nome do contato" autocomplete="off" />
                        </div>
                        <div class="space-y-2">
                            <Label for="c-phone">Telefone</Label>
                            <Input id="c-phone" v-model="contactPhone" placeholder="ex: 5521982212296" inputmode="tel" autocomplete="off" />
                            <p v-if="contactDigits" class="text-xs text-muted-foreground">Vai salvar: <span class="font-mono">{{ contactDigits }}</span></p>
                        </div>
                        <div class="space-y-2">
                            <Label for="c-email">E-mail (opcional)</Label>
                            <Input id="c-email" v-model="contactEmail" type="email" placeholder="email@exemplo.com" autocomplete="off" />
                        </div>
                    </div>
                    <DialogFooter class="flex-col-reverse sm:flex-row gap-2">
                        <Button variant="ghost" class="w-full sm:w-auto" @click="contactDialogOpen = false">Cancelar</Button>
                        <Button class="w-full sm:w-auto" :disabled="savingContact || contactDigits.length < 8" @click="saveContact">
                            <Loader2 v-if="savingContact" class="h-4 w-4 mr-2 animate-spin" />
                            {{ savingContact ? 'Salvando…' : 'Salvar' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="dialogOpen">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <UserPlus class="h-5 w-5" />
                            {{ mode === 'pick' ? 'Iniciar conversa' : 'Nova conversa' }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ mode === 'pick' ? 'Vai abrir uma nova conversa com esse contato.' : 'Digite o telefone (com DDI) ou escolha um contato da lista.' }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-3">
                        <div class="space-y-2">
                            <Label for="phone">Telefone</Label>
                            <Input id="phone" v-model="newPhone" placeholder="ex: 5521982212296" inputmode="tel" autocomplete="off" :disabled="mode === 'pick'" />
                            <p v-if="digitsOnly && mode !== 'pick'" class="text-xs text-muted-foreground">Vai usar: <span class="font-mono">{{ digitsOnly }}</span></p>
                        </div>
                        <div class="space-y-2">
                            <Label for="name">Nome (opcional)</Label>
                            <Input id="name" v-model="newName" placeholder="Nome do contato" autocomplete="off" :disabled="mode === 'pick'" />
                        </div>
                    </div>

                    <DialogFooter class="flex-col-reverse sm:flex-row gap-2">
                        <Button variant="ghost" class="w-full sm:w-auto" @click="dialogOpen = false">Cancelar</Button>
                        <Button class="w-full sm:w-auto" :disabled="starting || (mode === 'new' && digitsOnly.length < 8)" @click="startChat">
                            <Loader2 v-if="starting" class="h-4 w-4 mr-2 animate-spin" />
                            {{ starting ? 'Iniciando…' : 'Iniciar conversa' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
