<script setup>
import { Head } from '@inertiajs/vue3';
import { Motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/vora/PageHeader.vue';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog';
import { useAuth } from '@/Composables/useAuth';
import { onMounted, ref, reactive, computed } from 'vue';
import axios from 'axios';
import { Plus, Users as UsersIcon, GitBranch, Hash, Layers, Pencil, Trash2 } from 'lucide-vue-next';

const { isAdmin } = useAuth();

const sectors = ref([]);
const loading = ref(true);

const dialogOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const formError = ref(null);
const form = reactive({ name: '', menu_key: '', color: '#737373', description: '', parent_id: '' });

const dialogTitle = computed(() => editing.value ? 'Editar setor' : 'Novo setor');
const parentOptions = computed(() => sectors.value
    .filter(s => !editing.value || s.id !== editing.value.id)
    .map(s => ({ value: s.id, label: s.name })));

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/v1/sectors');
        sectors.value = data.data || [];
    } finally {
        loading.value = false;
    }
}

function resetForm() {
    Object.assign(form, { name: '', menu_key: '', color: '#737373', description: '', parent_id: '' });
    formError.value = null;
}

function openCreate(parentId = '') {
    editing.value = null;
    resetForm();
    form.parent_id = parentId || '';
    dialogOpen.value = true;
}

function openEdit(s) {
    editing.value = s;
    Object.assign(form, {
        name: s.name,
        menu_key: s.menu_key || '',
        color: s.color || '#737373',
        description: s.description || '',
        parent_id: s.parent_id || '',
    });
    formError.value = null;
    dialogOpen.value = true;
}

async function save() {
    saving.value = true;
    formError.value = null;
    try {
        const payload = {
            name: form.name,
            menu_key: form.menu_key || null,
            color: form.color,
            description: form.description || null,
            parent_id: form.parent_id || null,
        };
        if (editing.value) {
            await axios.put(`/api/v1/sectors/${editing.value.id}`, payload);
        } else {
            await axios.post('/api/v1/sectors', payload);
        }
        dialogOpen.value = false;
        await load();
    } catch (e) {
        formError.value = e.response?.data?.message || 'Falha ao salvar o setor. Verifique os dados.';
    } finally {
        saving.value = false;
    }
}

async function remove(s) {
    if (!confirm(`Remover o setor "${s.name}"?`)) return;
    try {
        await axios.delete(`/api/v1/sectors/${s.id}`);
        await load();
    } catch (e) {
        alert(e.response?.data?.message || 'Falha ao remover o setor.');
    }
}

onMounted(load);
</script>

<template>
    <Head title="Setores — Vora" />
    <AppLayout title="Setores">
        <div class="px-8 py-8 max-w-[1400px] mx-auto">
            <PageHeader title="Setores" description="Estrutura de atendimento e menu automatizado">
                <template #actions>
                    <Button v-if="isAdmin" variant="default" @click="openCreate()">
                        <Plus class="h-4 w-4" />
                        Novo setor
                    </Button>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <Motion v-for="(s, idx) in sectors" :key="s.id"
                        :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
                        :transition="{ delay: idx * 0.04, duration: 0.35, ease: [0.22, 1, 0.36, 1] }">
                    <Card class="hover:shadow-pop transition-shadow duration-200 group">
                        <CardContent class="pt-5 pb-5">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="h-10 w-10 rounded-lg flex items-center justify-center shrink-0"
                                         :style="{ backgroundColor: (s.color || '#94A3B8') + '1a' }">
                                        <Layers class="h-4 w-4" :style="{ color: s.color || '#94A3B8' }" />
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="font-semibold text-foreground text-[14.5px] tracking-tight truncate">
                                            {{ s.name }}
                                        </h3>
                                        <div class="flex items-center gap-1 text-[11.5px] text-muted-foreground mt-0.5">
                                            <Hash class="h-3 w-3" />
                                            {{ s.menu_key ? `tecla ${s.menu_key}` : 'sem tecla' }}
                                        </div>
                                    </div>
                                </div>
                                <Badge variant="outline">{{ s.open_tickets }} aberto{{ s.open_tickets === 1 ? '' : 's' }}</Badge>
                            </div>

                            <p v-if="s.description" class="text-[12px] text-muted-foreground mb-3 line-clamp-2">
                                {{ s.description }}
                            </p>

                            <div class="grid grid-cols-2 gap-2 pt-3 border-t border-border">
                                <div class="flex items-center gap-2">
                                    <UsersIcon class="h-3.5 w-3.5 text-muted-foreground" />
                                    <div class="text-[12px] text-foreground">
                                        <span class="font-semibold tabular-nums">{{ s.attendants_count || 0 }}</span>
                                        <span class="text-muted-foreground"> atendentes</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <GitBranch class="h-3.5 w-3.5 text-muted-foreground" />
                                    <div class="text-[12px] text-foreground">
                                        <span class="font-semibold tabular-nums">{{ s.children_count || 0 }}</span>
                                        <span class="text-muted-foreground"> subsetores</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Subsetores -->
                            <ul v-if="s.children && s.children.length" class="mt-3 space-y-1">
                                <li v-for="c in s.children" :key="c.id"
                                    class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-muted/50 transition-colors group/child">
                                    <span class="h-1.5 w-1.5 rounded-full shrink-0"
                                          :style="{ backgroundColor: c.color || '#94A3B8' }" />
                                    <span class="text-[12.5px] text-foreground truncate flex-1">{{ c.name }}</span>
                                    <span class="text-[11px] text-muted-foreground tabular-nums">
                                        {{ c.open_tickets }}
                                    </span>
                                    <span v-if="isAdmin" class="inline-flex items-center gap-0.5 opacity-0 group-hover/child:opacity-100 transition-opacity">
                                        <button type="button" @click="openEdit(c)" title="Editar subsetor"
                                                class="h-6 w-6 inline-flex items-center justify-center rounded text-muted-foreground hover:text-foreground hover:bg-muted">
                                            <Pencil class="h-3 w-3" />
                                        </button>
                                        <button type="button" @click="remove(c)" title="Remover subsetor"
                                                class="h-6 w-6 inline-flex items-center justify-center rounded text-muted-foreground hover:text-foreground hover:bg-muted">
                                            <Trash2 class="h-3 w-3" />
                                        </button>
                                    </span>
                                </li>
                            </ul>

                            <!-- Ações -->
                            <div v-if="isAdmin" class="flex items-center gap-1 mt-3 pt-3 border-t border-border">
                                <Button variant="ghost" size="sm" @click="openCreate(s.id)">
                                    <Plus class="h-3.5 w-3.5" />
                                    Subsetor
                                </Button>
                                <Button variant="ghost" size="sm" @click="openEdit(s)">
                                    <Pencil class="h-3.5 w-3.5" />
                                    Editar
                                </Button>
                                <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive ml-auto"
                                        @click="remove(s)">
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </Motion>
            </div>

            <div v-if="!loading && !sectors.length"
                 class="flex flex-col items-center justify-center py-20 text-center">
                <div class="h-12 w-12 rounded-xl bg-muted flex items-center justify-center mb-4">
                    <Layers class="h-5 w-5 text-muted-foreground" />
                </div>
                <h3 class="text-[15px] font-semibold text-foreground">Nenhum setor cadastrado</h3>
                <p class="text-[13px] text-muted-foreground mt-1 max-w-sm">
                    Crie setores para organizar o atendimento e configurar o menu automatizado.
                </p>
                <Button v-if="isAdmin" variant="default" class="mt-5" @click="openCreate()">
                    <Plus class="h-4 w-4" />
                    Criar primeiro setor
                </Button>
            </div>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-w-lg p-0">
                <DialogHeader class="px-5 pt-5">
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>
                        Configure o setor, a tecla do menu automatizado e o setor pai.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="save" class="px-5 py-5 space-y-4">
                    <div class="space-y-1.5">
                        <label class="text-[12px] font-medium text-foreground">Nome do setor</label>
                        <Input v-model="form.name" required placeholder="Suporte técnico" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[12px] font-medium text-foreground">Tecla do menu</label>
                            <Input v-model="form.menu_key" maxlength="8" placeholder="ex: 1" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[12px] font-medium text-foreground">Cor</label>
                            <div class="flex items-center gap-2">
                                <input type="color" v-model="form.color"
                                       class="h-9 w-12 rounded-md border border-border bg-card cursor-pointer" />
                                <Input v-model="form.color" class="flex-1 font-mono text-[12px]" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[12px] font-medium text-foreground">Setor pai</label>
                        <select v-model="form.parent_id"
                                class="flex h-9 w-full rounded-md border border-border bg-card px-3 py-1 text-[13px] text-foreground focus:outline-none focus:ring-2 focus:ring-ring/40 focus:border-ring transition-colors">
                            <option value="">Nenhum (setor principal)</option>
                            <option v-for="p in parentOptions" :key="p.value" :value="p.value">{{ p.label }}</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[12px] font-medium text-foreground">Descrição</label>
                        <textarea v-model="form.description" rows="2"
                                  placeholder="Descrição opcional do setor"
                                  class="flex w-full rounded-md border border-border bg-card px-3 py-2 text-[13px] text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/40 focus:border-ring transition-colors resize-none"></textarea>
                    </div>

                    <div v-if="formError"
                         class="text-[12.5px] text-destructive bg-destructive/8 border border-destructive/20 rounded-md px-3 py-2">
                        {{ formError }}
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border -mx-5 px-5 mt-5 pt-4">
                        <Button type="button" variant="ghost" @click="dialogOpen = false">Cancelar</Button>
                        <Button type="submit" variant="default" :disabled="saving">
                            {{ saving ? 'Salvando…' : (editing ? 'Salvar' : 'Criar setor') }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
