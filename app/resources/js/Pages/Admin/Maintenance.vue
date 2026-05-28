<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/vora/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Badge } from '@/Components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/Components/ui/dialog';
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Trash2, Search, AlertTriangle, Eraser } from 'lucide-vue-next';
import { useAuth } from '@/Composables/useAuth';

const { isAdmin } = useAuth();

const phone = ref('');
const keepContact = ref(false);
const preview = ref(null);
const previewLoading = ref(false);
const wiping = ref(false);

const wipeAllOpen = ref(false);
const wipeAllConfirm = ref('');
const wipeAllKeepContacts = ref(false);
const wipingAll = ref(false);

const digitsOnly = computed(() => (phone.value || '').replace(/\D+/g, ''));

let previewTimer = null;
watch(phone, () => {
    clearTimeout(previewTimer);
    if (digitsOnly.value.length < 4) {
        preview.value = null;
        return;
    }
    previewTimer = setTimeout(loadPreview, 350);
});

async function loadPreview() {
    previewLoading.value = true;
    try {
        const { data } = await axios.get('/api/v1/admin/maintenance/preview', {
            params: { phone: digitsOnly.value },
        });
        preview.value = data;
    } catch (e) {
        if (e?.response?.status !== 404) {
            toast.error('Falha ao buscar contato.');
        }
        preview.value = { phone: digitsOnly.value, matches: [] };
    } finally {
        previewLoading.value = false;
    }
}

async function wipe() {
    if (digitsOnly.value.length < 4) {
        toast.error('Informe um número válido.');
        return;
    }
    if (!confirm(`Apagar conversas do número ${digitsOnly.value}? Essa ação não pode ser desfeita.`)) return;

    wiping.value = true;
    try {
        const { data } = await axios.post('/api/v1/admin/maintenance/wipe-by-phone', {
            phone: digitsOnly.value,
            keep_contact: keepContact.value,
        });
        const s = data.stats || {};
        toast.success(`Apagado: ${s.messages_removed} msgs, ${s.tickets_removed} tickets, ${s.contacts_removed} contatos.`);
        preview.value = null;
        phone.value = '';
    } catch (e) {
        const msg = e?.response?.data?.message || 'Falha ao apagar.';
        toast.error(msg);
    } finally {
        wiping.value = false;
    }
}

async function wipeAll() {
    if (wipeAllConfirm.value.trim() !== 'APAGAR TUDO') {
        toast.error('Digite "APAGAR TUDO" para confirmar.');
        return;
    }
    wipingAll.value = true;
    try {
        const { data } = await axios.post('/api/v1/admin/maintenance/wipe-all', {
            confirm: 'APAGAR TUDO',
            keep_contacts: wipeAllKeepContacts.value,
        });
        const s = data.stats || {};
        toast.success(`Tudo apagado: ${s.messages_removed} msgs, ${s.tickets_removed} tickets, ${s.contacts_removed} contatos.`);
        wipeAllOpen.value = false;
        wipeAllConfirm.value = '';
    } catch (e) {
        const msg = e?.response?.data?.message || 'Falha ao apagar.';
        toast.error(msg);
    } finally {
        wipingAll.value = false;
    }
}
</script>

<template>
    <Head title="Manutenção" />
    <AppLayout>
        <div class="mx-auto w-full max-w-2xl p-4 sm:p-6">
            <PageHeader
                title="Manutenção"
                description="Apague conversas pra fazer testes. Ação irreversível — só admin."
            />

            <div v-if="!isAdmin" class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-700 dark:text-amber-300">
                <AlertTriangle class="inline h-4 w-4 mr-1" />
                Esta página é restrita a administradores.
            </div>

            <template v-else>
                <Card class="mb-4">
                    <CardHeader>
                        <CardTitle class="text-base">Apagar por número</CardTitle>
                        <CardDescription>
                            Informe o telefone (com ou sem DDI/máscara). Vou achar todas as conversas vinculadas a esse contato.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="phone">Telefone</Label>
                            <Input
                                id="phone"
                                v-model="phone"
                                placeholder="ex: 5521982212296"
                                inputmode="tel"
                                autocomplete="off"
                                class="text-base"
                            />
                            <p v-if="digitsOnly" class="text-xs text-muted-foreground">
                                Vou buscar por: <span class="font-mono">{{ digitsOnly }}</span>
                            </p>
                        </div>

                        <div class="flex items-center justify-between rounded-md border border-border bg-muted/30 px-3 py-2">
                            <div class="text-sm">
                                <div class="font-medium">Manter contato</div>
                                <div class="text-xs text-muted-foreground">Apaga só tickets/mensagens; preserva o cadastro do contato.</div>
                            </div>
                            <Switch v-model="keepContact" />
                        </div>

                        <div v-if="previewLoading" class="text-sm text-muted-foreground">
                            <Search class="inline h-4 w-4 mr-1 animate-pulse" /> Buscando…
                        </div>

                        <div v-else-if="preview && preview.matches?.length" class="rounded-md border border-border bg-card">
                            <div class="border-b border-border px-3 py-2 text-xs font-medium uppercase text-muted-foreground">
                                {{ preview.matches.length }} contato(s) encontrado(s)
                            </div>
                            <ul class="divide-y divide-border">
                                <li v-for="m in preview.matches" :key="m.id" class="px-3 py-2 text-sm">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="truncate font-medium">{{ m.name || '(sem nome)' }}</div>
                                            <div class="text-xs text-muted-foreground font-mono">{{ m.phone }}</div>
                                        </div>
                                        <div class="flex shrink-0 flex-col items-end gap-1">
                                            <Badge variant="secondary" class="text-[11px]">{{ m.tickets_count }} tickets</Badge>
                                            <Badge variant="outline" class="text-[11px]">{{ m.messages_count }} msgs</Badge>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <div v-else-if="preview && digitsOnly.length >= 4" class="rounded-md border border-dashed border-border px-3 py-3 text-center text-sm text-muted-foreground">
                            Nenhum contato encontrado para esse número.
                        </div>

                        <Button
                            variant="destructive"
                            class="w-full h-11 text-base"
                            :disabled="wiping || digitsOnly.length < 4 || !(preview?.matches?.length)"
                            @click="wipe"
                        >
                            <Trash2 class="h-4 w-4 mr-2" />
                            {{ wiping ? 'Apagando…' : 'Apagar conversas desse número' }}
                        </Button>
                    </CardContent>
                </Card>

                <Card class="border-destructive/40">
                    <CardHeader>
                        <CardTitle class="text-base flex items-center gap-2">
                            <Eraser class="h-4 w-4 text-destructive" />
                            Zona de perigo
                        </CardTitle>
                        <CardDescription>
                            Apaga TODAS as conversas e contatos do seu tenant. Use só pra reset completo de teste.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="outline" class="w-full h-11 border-destructive/60 text-destructive hover:bg-destructive/10" @click="wipeAllOpen = true">
                            Apagar todas as conversas
                        </Button>
                    </CardContent>
                </Card>

                <Dialog v-model:open="wipeAllOpen">
                    <DialogContent class="max-w-md">
                        <DialogHeader>
                            <DialogTitle class="flex items-center gap-2 text-destructive">
                                <AlertTriangle class="h-5 w-5" /> Confirmar reset total
                            </DialogTitle>
                            <DialogDescription>
                                Vai apagar todos os tickets, mensagens e (opcionalmente) contatos do tenant. Sem volta.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between rounded-md border border-border bg-muted/30 px-3 py-2">
                                <div class="text-sm">
                                    <div class="font-medium">Manter contatos</div>
                                    <div class="text-xs text-muted-foreground">Apaga só tickets/mensagens.</div>
                                </div>
                                <Switch v-model="wipeAllKeepContacts" />
                            </div>

                            <div class="space-y-2">
                                <Label for="confirm">Digite <span class="font-mono">APAGAR TUDO</span> para confirmar</Label>
                                <Input id="confirm" v-model="wipeAllConfirm" autocomplete="off" />
                            </div>
                        </div>

                        <DialogFooter class="flex-col-reverse sm:flex-row gap-2">
                            <Button variant="ghost" class="w-full sm:w-auto" @click="wipeAllOpen = false">Cancelar</Button>
                            <Button
                                variant="destructive"
                                class="w-full sm:w-auto"
                                :disabled="wipingAll || wipeAllConfirm.trim() !== 'APAGAR TUDO'"
                                @click="wipeAll"
                            >
                                {{ wipingAll ? 'Apagando…' : 'Apagar tudo' }}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </template>
        </div>
    </AppLayout>
</template>
