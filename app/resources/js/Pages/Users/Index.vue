<script setup>
import { Head } from '@inertiajs/vue3';
import { Motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Card, CardContent } from '@/Components/ui/card';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog';
import { onMounted, ref, reactive, computed } from 'vue';
import axios from 'axios';
import { UserPlus, Users as UsersIcon, Pencil, Trash2, Power } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { useAuth } from '@/Composables/useAuth';

const { isAdmin } = useAuth();
const users = ref([]);
const sectors = ref([]);
const loading = ref(true);
const saving = ref(false);
const formError = ref(null);

const dialogOpen = ref(false);
const editing = ref(null);
const form = reactive({
    name: '', email: '', phone: '', password: '',
    role: 'attendant', is_active: true, sector_ids: [],
});

const initials = (name) => (name || '?')
    .split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();

const roleLabel = (r) => ({ admin: 'Admin', supervisor: 'Supervisor', attendant: 'Atendente' }[r] || r);
const roleOptions = [
    { value: 'attendant',  label: 'Atendente'  },
    { value: 'supervisor', label: 'Supervisor' },
    { value: 'admin',      label: 'Admin'      },
];

const dialogTitle = computed(() => editing.value ? 'Editar usuário' : 'Convidar usuário');
const dialogDescription = computed(() => editing.value
    ? 'Atualize as informações deste usuário'
    : 'Crie uma conta para um novo membro da equipe');

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/v1/users');
        users.value = data.data || [];
    } finally {
        loading.value = false;
    }
}

async function loadSectors() {
    try {
        const { data } = await axios.get('/api/v1/sectors');
        const flat = [];
        const walk = (list) => list.forEach(s => {
            flat.push({ id: s.id, name: s.name, color: s.color });
            if (s.children?.length) walk(s.children);
        });
        walk(data.data || []);
        sectors.value = flat;
    } catch (_) {}
}

function resetForm() {
    Object.assign(form, { name: '', email: '', phone: '', password: '', role: 'attendant', is_active: true, sector_ids: [] });
    formError.value = null;
}

function openCreate() {
    editing.value = null;
    resetForm();
    dialogOpen.value = true;
}

function openEdit(u) {
    editing.value = u;
    Object.assign(form, {
        name: u.name, email: u.email, phone: u.phone || '',
        password: '', role: u.role, is_active: u.is_active,
        sector_ids: (u.sectors || []).map(s => s.id),
    });
    formError.value = null;
    dialogOpen.value = true;
}

function toggleSector(id) {
    const i = form.sector_ids.indexOf(id);
    if (i >= 0) form.sector_ids.splice(i, 1);
    else form.sector_ids.push(id);
}

async function save() {
    if (form.role === 'attendant' && form.sector_ids.length === 0) {
        formError.value = 'Selecione pelo menos um setor para atendentes.';
        return;
    }
    saving.value = true;
    formError.value = null;
    try {
        const payload = { ...form };
        if (editing.value && !payload.password) delete payload.password;
        if (editing.value) {
            await axios.put(`/api/v1/users/${editing.value.id}`, payload);
        } else {
            await axios.post('/api/v1/users', payload);
        }
        dialogOpen.value = false;
        await load();
    } catch (e) {
        formError.value = e.response?.data?.message || 'Falha ao salvar. Verifique os dados.';
    } finally {
        saving.value = false;
    }
}

async function toggleActive(u) {
    try {
        await axios.put(`/api/v1/users/${u.id}`, { is_active: !u.is_active });
        await load();
        toast.success(u.is_active ? 'Usuário desativado.' : 'Usuário reativado.');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Falha ao atualizar usuário.');
    }
}

async function remove(u) {
    if (!confirm(`Remover ${u.name} permanentemente?`)) return;
    try {
        await axios.delete(`/api/v1/users/${u.id}`);
        await load();
        toast.success('Usuário removido.');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Falha ao remover.');
    }
}

onMounted(() => { load(); loadSectors(); });
</script>

<template>
    <Head title="Usuários — Vora" />
    <AppLayout title="Usuários">
        <div class="px-4 sm:px-8 py-6 sm:py-8 max-w-[1400px] mx-auto">
                <div v-if="isAdmin" class="flex justify-end pb-5">
                    <Button variant="default" @click="openCreate">
                        <UserPlus class="h-4 w-4" />
                        Convidar usuário
                    </Button>
                </div>

                <Motion :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
                        :transition="{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }">
                    <!-- Desktop table -->
                    <Card class="hidden md:block">
                        <CardContent class="px-0 pb-0 pt-0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-[13px]">
                                    <thead>
                                        <tr class="border-b border-border bg-muted/40">
                                            <th class="px-6 py-3 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Nome</th>
                                            <th class="px-6 py-3 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Papel</th>
                                            <th class="px-6 py-3 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Setores</th>
                                            <th class="px-6 py-3 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Em atendimento</th>
                                            <th class="px-6 py-3 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Resolvidos</th>
                                            <th class="px-6 py-3 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-right font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr v-for="u in users" :key="u.id"
                                            class="hover:bg-muted/40 transition-colors"
                                            :class="{ 'opacity-60': !u.is_active }">
                                            <td class="px-6 py-3.5">
                                                <div class="flex items-center gap-3 min-w-0">
                                                    <Avatar><AvatarFallback>{{ initials(u.name) }}</AvatarFallback></Avatar>
                                                    <div class="min-w-0">
                                                        <div class="font-medium text-foreground truncate flex items-center gap-2">
                                                            {{ u.name }}
                                                            <span v-if="!u.is_active"
                                                                  class="text-[10px] uppercase tracking-wider text-muted-foreground bg-muted px-1.5 py-0.5 rounded">
                                                                Inativo
                                                            </span>
                                                        </div>
                                                        <div class="text-[11.5px] text-muted-foreground truncate">{{ u.email }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-3.5">
                                                <Badge variant="outline">{{ roleLabel(u.role) }}</Badge>
                                            </td>
                                            <td class="px-6 py-3.5">
                                                <div class="flex flex-wrap gap-1">
                                                    <span v-for="s in (u.sectors || []).slice(0, 2)" :key="s.id"
                                                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-border text-[11px] text-foreground bg-card">
                                                        <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: s.color }"></span>
                                                        {{ s.name }}
                                                    </span>
                                                    <span v-if="(u.sectors || []).length > 2"
                                                          class="inline-flex items-center px-2 py-0.5 rounded-full border border-border text-[11px] text-muted-foreground">
                                                        +{{ u.sectors.length - 2 }}
                                                    </span>
                                                    <span v-if="!(u.sectors || []).length" class="text-[12px] text-muted-foreground">—</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-3.5 tabular-nums">{{ u.in_progress ?? 0 }}</td>
                                            <td class="px-6 py-3.5 tabular-nums">{{ u.resolved ?? 0 }}</td>
                                            <td class="px-6 py-3.5">
                                                <Badge variant="outline">
                                                    {{ u.status === 'online' ? 'Online' : 'Offline' }}
                                                </Badge>
                                            </td>
                                            <td class="px-6 py-2.5 text-right">
                                                <div v-if="isAdmin" class="inline-flex items-center gap-0.5">
                                                    <button type="button" @click="openEdit(u)"
                                                            title="Editar"
                                                            class="h-8 w-8 inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
                                                        <Pencil class="h-3.5 w-3.5" />
                                                    </button>
                                                    <button type="button" @click="toggleActive(u)"
                                                            :title="u.is_active ? 'Desativar' : 'Reativar'"
                                                            class="h-8 w-8 inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
                                                        <Power class="h-3.5 w-3.5" />
                                                    </button>
                                                    <button type="button" @click="remove(u)"
                                                            title="Remover"
                                                            class="h-8 w-8 inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
                                                        <Trash2 class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                                <span v-else class="text-[12px] text-muted-foreground">—</span>
                                            </td>
                                        </tr>
                                        <tr v-if="!loading && !users.length">
                                            <td colspan="7" class="text-center py-16">
                                                <div class="flex flex-col items-center">
                                                    <div class="h-12 w-12 rounded-xl bg-muted flex items-center justify-center mb-4">
                                                        <UsersIcon class="h-5 w-5 text-muted-foreground" />
                                                    </div>
                                                    <h3 class="text-[15px] font-semibold text-foreground">Nenhum usuário cadastrado</h3>
                                                    <p class="text-[13px] text-muted-foreground mt-1">Convide sua equipe para começar a atender.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Mobile cards -->
                    <div class="md:hidden space-y-2.5">
                        <Card v-for="u in users" :key="u.id"
                              :class="{ 'opacity-60': !u.is_active }">
                            <CardContent class="p-4 space-y-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <Avatar><AvatarFallback>{{ initials(u.name) }}</AvatarFallback></Avatar>
                                        <div class="min-w-0">
                                            <div class="font-medium text-foreground truncate flex items-center gap-2">
                                                {{ u.name }}
                                                <span v-if="!u.is_active"
                                                      class="text-[10px] uppercase tracking-wider text-muted-foreground bg-muted px-1.5 py-0.5 rounded">
                                                    Inativo
                                                </span>
                                            </div>
                                            <div class="text-[11.5px] text-muted-foreground truncate">{{ u.email }}</div>
                                        </div>
                                    </div>
                                    <Badge variant="outline" class="shrink-0">{{ roleLabel(u.role) }}</Badge>
                                </div>

                                <div v-if="(u.sectors || []).length" class="flex flex-wrap gap-1">
                                    <span v-for="s in u.sectors" :key="s.id"
                                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-border text-[11px] text-foreground bg-card">
                                        <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: s.color }"></span>
                                        {{ s.name }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between pt-1 text-[12px] text-muted-foreground">
                                    <div class="flex items-center gap-4 tabular-nums">
                                        <span>Atend.: <span class="text-foreground font-medium">{{ u.in_progress ?? 0 }}</span></span>
                                        <span>Resolv.: <span class="text-foreground font-medium">{{ u.resolved ?? 0 }}</span></span>
                                    </div>
                                    <div v-if="isAdmin" class="inline-flex items-center gap-0.5">
                                        <button type="button" @click="openEdit(u)"
                                                aria-label="Editar"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
                                            <Pencil class="h-3.5 w-3.5" />
                                        </button>
                                        <button type="button" @click="toggleActive(u)"
                                                :aria-label="u.is_active ? 'Desativar' : 'Reativar'"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
                                            <Power class="h-3.5 w-3.5" />
                                        </button>
                                        <button type="button" @click="remove(u)"
                                                aria-label="Remover"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card v-if="!loading && !users.length">
                            <CardContent class="text-center py-16">
                                <div class="flex flex-col items-center">
                                    <div class="h-12 w-12 rounded-xl bg-muted flex items-center justify-center mb-4">
                                        <UsersIcon class="h-5 w-5 text-muted-foreground" />
                                    </div>
                                    <h3 class="text-[15px] font-semibold text-foreground">Nenhum usuário cadastrado</h3>
                                    <p class="text-[13px] text-muted-foreground mt-1">Convide sua equipe para começar a atender.</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </Motion>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-w-lg p-0">
            <DialogHeader class="px-5 pt-5">
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>{{ dialogDescription }}</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="save" class="px-5 py-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="text-[12px] font-medium text-foreground">Nome completo</label>
                        <Input v-model="form.name" required placeholder="Maria Silva" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[12px] font-medium text-foreground">E-mail</label>
                        <Input v-model="form.email" type="email" required placeholder="maria@empresa.com" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[12px] font-medium text-foreground">Telefone</label>
                        <Input v-model="form.phone" placeholder="(11) 90000-0000" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[12px] font-medium text-foreground">
                            Senha <span v-if="editing" class="text-muted-foreground font-normal">(deixe em branco para manter)</span>
                        </label>
                        <Input v-model="form.password" type="password" :required="!editing" placeholder="••••••••" autocomplete="new-password" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[12px] font-medium text-foreground">Papel</label>
                        <select v-model="form.role"
                                class="flex h-9 w-full rounded-md border border-border bg-card px-3 py-1 text-[13px] text-foreground focus:outline-none focus:ring-2 focus:ring-ring/40 focus:border-ring transition-colors">
                            <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[12px] font-medium text-foreground">
                        Setores
                        <span v-if="form.role === 'attendant'" class="text-destructive">*</span>
                        <span v-else class="text-muted-foreground font-normal">(opcional)</span>
                    </label>
                    <div v-if="sectors.length === 0" class="text-[12px] text-muted-foreground">
                        Nenhum setor cadastrado.
                    </div>
                    <div v-else class="flex flex-wrap gap-1.5">
                        <button
                            v-for="s in sectors"
                            :key="s.id"
                            type="button"
                            @click="toggleSector(s.id)"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[12px] transition-colors"
                            :class="form.sector_ids.includes(s.id)
                                ? 'border-primary bg-primary/10 text-foreground'
                                : 'border-border bg-card text-muted-foreground hover:bg-muted'">
                            <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: s.color }"></span>
                            {{ s.name }}
                        </button>
                    </div>
                </div>

                <label class="flex items-center gap-2.5 cursor-pointer select-none pt-1">
                    <input type="checkbox" v-model="form.is_active"
                           class="h-4 w-4 rounded border-border text-primary focus:ring-ring/40" />
                    <span class="text-[12.5px] text-foreground">Usuário ativo</span>
                </label>

                <div v-if="formError"
                     class="text-[12.5px] text-destructive bg-destructive/8 border border-destructive/20 rounded-md px-3 py-2">
                    {{ formError }}
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-border -mx-5 px-5 mt-5 pt-4">
                    <Button type="button" variant="ghost" @click="dialogOpen = false">Cancelar</Button>
                    <Button type="submit" variant="default" :disabled="saving">
                        {{ editing ? 'Salvar' : 'Criar usuário' }}
                    </Button>
                </div>
            </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
