<script setup>
import { Head } from '@inertiajs/vue3';
import { Motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';
import Topbar from '@/Components/Topbar.vue';
import Card from '@/Components/ui/Card.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import { onMounted, ref, reactive, computed } from 'vue';
import axios from 'axios';
import { UserPlus, Users as UsersIcon, Pencil, Trash2, Power } from 'lucide-vue-next';

const users = ref([]);
const loading = ref(true);
const saving = ref(false);
const formError = ref(null);

const dialogOpen = ref(false);
const editing = ref(null);
const form = reactive({
    name: '', email: '', phone: '', password: '',
    role: 'attendant', is_active: true,
});

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

function resetForm() {
    Object.assign(form, { name: '', email: '', phone: '', password: '', role: 'attendant', is_active: true });
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
    });
    formError.value = null;
    dialogOpen.value = true;
}

async function save() {
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
    } catch (_) {}
}

async function remove(u) {
    if (!confirm(`Remover ${u.name} permanentemente?`)) return;
    try {
        await axios.delete(`/api/v1/users/${u.id}`);
        await load();
    } catch (e) {
        alert(e.response?.data?.message || 'Falha ao remover.');
    }
}

onMounted(load);
</script>

<template>
    <Head title="Usuários — Vora" />
    <AppLayout>
        <Topbar title="Usuários" subtitle="Atendentes, supervisores e administradores">
            <template #actions>
                <Button variant="outline" @click="openCreate">
                    <UserPlus class="h-4 w-4" />
                    Convidar usuário
                </Button>
            </template>
        </Topbar>

        <div class="flex-1 overflow-y-auto scrollbar-thin">
            <div class="px-8 py-8 max-w-[1400px]">
                <Motion :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
                        :transition="{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }">
                    <Card>
                        <CardContent class="px-0 pb-0 pt-0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-[13px]">
                                    <thead>
                                        <tr class="border-b border-border bg-muted/40">
                                            <th class="px-6 py-3 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Nome</th>
                                            <th class="px-6 py-3 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Papel</th>
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
                                                    <Avatar :name="u.name" :status="u.status" size="sm" />
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
                                                <Badge variant="muted">{{ roleLabel(u.role) }}</Badge>
                                            </td>
                                            <td class="px-6 py-3.5 tabular-nums">{{ u.in_progress ?? 0 }}</td>
                                            <td class="px-6 py-3.5 tabular-nums">{{ u.resolved ?? 0 }}</td>
                                            <td class="px-6 py-3.5">
                                                <Badge variant="muted">
                                                    {{ u.status === 'online' ? 'Online' : 'Offline' }}
                                                </Badge>
                                            </td>
                                            <td class="px-6 py-2.5 text-right">
                                                <div class="inline-flex items-center gap-0.5">
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
                                            </td>
                                        </tr>
                                        <tr v-if="!loading && !users.length">
                                            <td colspan="6" class="text-center py-16">
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
                </Motion>
            </div>
        </div>

        <Dialog v-model:open="dialogOpen" :title="dialogTitle" :description="dialogDescription" width="max-w-lg">
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
                    <Button type="submit" variant="outline" :loading="saving">
                        {{ editing ? 'Salvar' : 'Criar usuário' }}
                    </Button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
