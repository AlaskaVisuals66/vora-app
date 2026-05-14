<script setup>
import { Head } from '@inertiajs/vue3';
import { Motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/vora/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useAuth } from '@/Composables/useAuth';
import { reactive, ref, computed } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

const { user } = useAuth();

const initials = (name) => (name || '?')
    .split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();

const roleLabels = { admin: 'Admin', supervisor: 'Supervisor', attendant: 'Atendente' };
const roleName = computed(() => {
    const r = user.value?.roles?.[0];
    return typeof r === 'string' ? r : r?.name;
});
const roleLabel = computed(() => roleLabels[roleName.value] || roleName.value || '—');

const form = reactive({
    name: user.value?.name || '',
    phone: user.value?.phone || '',
    current_password: '',
    password: '',
    password_confirmation: '',
});

const saving = ref(false);
const formError = ref(null);

async function save() {
    saving.value = true;
    formError.value = null;
    try {
        const payload = {
            name: form.name,
            phone: form.phone,
        };
        if (form.password) {
            payload.current_password = form.current_password;
            payload.password = form.password;
            payload.password_confirmation = form.password_confirmation;
        }
        const { data } = await axios.put('/api/v1/auth/profile', payload);
        if (data.user) localStorage.setItem('helpdesk.user', JSON.stringify(data.user));
        form.current_password = '';
        form.password = '';
        form.password_confirmation = '';
        toast.success('Perfil atualizado');
    } catch (e) {
        formError.value = e.response?.data?.message || 'Falha ao salvar. Verifique os dados.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head title="Perfil — Vora" />
    <AppLayout title="Perfil">
        <div class="px-8 py-8 max-w-[760px]">
            <PageHeader title="Perfil" description="Suas informações de conta e segurança" />

            <Motion :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
                    :transition="{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }">
                <Card class="mb-4">
                    <CardContent class="flex items-center gap-4 pt-6 pb-6">
                        <Avatar class="h-14 w-14">
                            <AvatarFallback class="text-[15px]">{{ initials(user?.name) }}</AvatarFallback>
                        </Avatar>
                        <div class="min-w-0">
                            <div class="text-[16px] font-semibold text-foreground tracking-tight truncate">
                                {{ user?.name || 'Usuário' }}
                            </div>
                            <div class="text-[12.5px] text-muted-foreground truncate">{{ user?.email }}</div>
                        </div>
                        <Badge variant="outline" class="ml-auto shrink-0">{{ roleLabel }}</Badge>
                    </CardContent>
                </Card>
            </Motion>

            <Motion :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
                    :transition="{ delay: 0.08, duration: 0.4, ease: [0.22, 1, 0.36, 1] }">
                <Card>
                    <CardHeader>
                        <CardTitle>Informações</CardTitle>
                        <CardDescription>Atualize seu nome, telefone e senha</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="save" class="space-y-5">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <label class="text-[12px] font-medium text-foreground">Nome completo</label>
                                    <Input v-model="form.name" required placeholder="Seu nome" />
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-[12px] font-medium text-foreground">Telefone</label>
                                    <Input v-model="form.phone" placeholder="(11) 90000-0000" />
                                </div>
                                <div class="space-y-1.5 sm:col-span-2">
                                    <label class="text-[12px] font-medium text-muted-foreground">E-mail</label>
                                    <Input :model-value="user?.email" disabled />
                                </div>
                            </div>

                            <div class="border-t border-border pt-5">
                                <p class="text-[12.5px] font-medium text-foreground mb-1">Alterar senha</p>
                                <p class="text-[11.5px] text-muted-foreground mb-3">
                                    Deixe em branco para manter a senha atual.
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1.5 sm:col-span-2">
                                        <label class="text-[12px] font-medium text-foreground">Senha atual</label>
                                        <Input v-model="form.current_password" type="password"
                                               placeholder="••••••••" autocomplete="current-password" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="text-[12px] font-medium text-foreground">Nova senha</label>
                                        <Input v-model="form.password" type="password"
                                               placeholder="••••••••" autocomplete="new-password" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="text-[12px] font-medium text-foreground">Confirmar nova senha</label>
                                        <Input v-model="form.password_confirmation" type="password"
                                               placeholder="••••••••" autocomplete="new-password" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="formError"
                                 class="text-[12.5px] text-destructive bg-destructive/8 border border-destructive/20 rounded-md px-3 py-2">
                                {{ formError }}
                            </div>

                            <div class="flex justify-end pt-1">
                                <Button type="submit" variant="default" :disabled="saving">
                                    {{ saving ? 'Salvando…' : 'Salvar alterações' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </Motion>
        </div>
    </AppLayout>
</template>
