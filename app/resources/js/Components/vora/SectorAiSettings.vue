<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Switch } from '@/Components/ui/switch';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { Separator } from '@/Components/ui/separator';

const sectors = ref([]);
const saving = ref({});

async function load() {
    const { data } = await axios.get('/api/v1/sectors');
    const flat = [];
    for (const s of (data.data || [])) {
        flat.push(s);
        for (const c of (s.children || [])) flat.push(c);
    }
    sectors.value = flat.map(s => ({
        ...s,
        ai: {
            ai_enabled:       s.ai_settings?.ai_enabled ?? false,
            ai_prompt:        s.ai_settings?.ai_prompt ?? '',
            n8n_workflow_id:  s.ai_settings?.n8n_workflow_id ?? '',
            n8n_webhook_path: s.ai_settings?.n8n_webhook_path ?? '',
        },
    }));
}

async function save(sector) {
    saving.value[sector.id] = true;
    try {
        await axios.put(`/api/v1/sectors/${sector.id}/ai-settings`, sector.ai);
        toast.success(`${sector.name}: configurações salvas`);
    } catch {
        toast.error('Falha ao salvar');
    } finally {
        saving.value[sector.id] = false;
    }
}

async function n8nAction(sector, type) {
    try {
        await axios.post(`/api/v1/sectors/${sector.id}/n8n-action`, { type });
        toast.success(`Ação "${type}" disparada`);
    } catch {
        toast.error('Falha ao disparar ação');
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <div v-if="!sectors.length" class="text-[12.5px] text-muted-foreground py-4 text-center">
            Nenhum setor encontrado.
        </div>

        <div v-for="sector in sectors" :key="sector.id"
             class="border border-border rounded-lg p-4 space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-[13px] font-medium">{{ sector.name }}</span>
                <div class="flex items-center gap-2 text-[12px] text-muted-foreground">
                    IA ativa
                    <Switch v-model:checked="sector.ai.ai_enabled" />
                </div>
            </div>

            <div v-if="sector.ai.ai_enabled" class="space-y-3">
                <div class="space-y-1.5">
                    <label class="text-[12px] font-medium text-foreground">Prompt do bot</label>
                    <Textarea v-model="sector.ai.ai_prompt"
                              placeholder="Você é um assistente de suporte da empresa..."
                              class="text-[12.5px] min-h-[80px]" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="text-[12px] font-medium text-foreground">Workflow ID (n8n)</label>
                        <Input v-model="sector.ai.n8n_workflow_id" placeholder="abc123" class="text-[12.5px]" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[12px] font-medium text-foreground">Webhook path (n8n)</label>
                        <Input v-model="sector.ai.n8n_webhook_path" placeholder="sector-ai" class="text-[12.5px]" />
                    </div>
                </div>
            </div>

            <Separator />

            <div class="flex items-center justify-between gap-2 flex-wrap">
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" @click="n8nAction(sector, 'edit-number')">
                        Editar número
                    </Button>
                    <Button variant="outline" size="sm" @click="n8nAction(sector, 'edit-conversation')">
                        Editar conversa
                    </Button>
                </div>
                <Button variant="default" size="sm"
                        :disabled="saving[sector.id]"
                        @click="save(sector)">
                    {{ saving[sector.id] ? 'Salvando…' : 'Salvar' }}
                </Button>
            </div>
        </div>
    </div>
</template>
