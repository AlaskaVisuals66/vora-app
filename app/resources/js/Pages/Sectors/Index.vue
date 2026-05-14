<script setup>
import { Head } from '@inertiajs/vue3';
import { Motion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/vora/PageHeader.vue';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { Plus, Users as UsersIcon, GitBranch, Hash, Layers } from 'lucide-vue-next';

const sectors = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/v1/analytics/dashboard');
        sectors.value = data.data?.by_sector || [];
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head title="Setores — Vora" />
    <AppLayout title="Setores">
        <div class="px-8 py-8 max-w-[1400px]">
                <PageHeader title="Setores" description="Estrutura de atendimento e menu automatizado">
                    <template #actions>
                        <Button variant="default">
                            <Plus class="h-4 w-4" />
                            Novo setor
                        </Button>
                    </template>
                </PageHeader>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <Motion v-for="(s, idx) in sectors" :key="s.id"
                            :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
                            :transition="{ delay: idx * 0.04, duration: 0.35, ease: [0.22, 1, 0.36, 1] }">
                        <Card class="hover:shadow-pop transition-shadow duration-200 group cursor-pointer">
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
                                                tecla {{ s.menu_key }}
                                            </div>
                                        </div>
                                    </div>
                                    <Badge variant="outline">{{ s.open_tickets }} aberto{{ s.open_tickets === 1 ? '' : 's' }}</Badge>
                                </div>

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
                    <Button variant="default" class="mt-5">
                        <Plus class="h-4 w-4" />
                        Criar primeiro setor
                    </Button>
                </div>
        </div>
    </AppLayout>
</template>
