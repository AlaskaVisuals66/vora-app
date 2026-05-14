<script setup>
import { onMounted, ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Motion } from 'motion-v';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Topbar from '@/Components/Topbar.vue';
import Card from '@/Components/ui/Card.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import Badge from '@/Components/ui/Badge.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import {
    Inbox, Users, CheckCircle2, Clock, TrendingUp, ArrowUpRight,
} from 'lucide-vue-next';

const data = ref(null);
const loading = ref(true);

onMounted(async () => {
    try {
        const { data: payload } = await axios.get('/api/v1/analytics/dashboard');
        data.value = payload.data || payload;
    } finally {
        loading.value = false;
    }
});

const kpis = computed(() => [
    { label: 'Tickets em aberto', value: data.value?.kpis?.open_tickets,         icon: Inbox,        accent: 'text-primary',  delta: '+12%' },
    { label: 'Na fila',           value: data.value?.kpis?.queued,               icon: Clock,        accent: 'text-amber-600',delta: '−4%'  },
    { label: 'Resolvidos hoje',   value: data.value?.kpis?.resolved_today,       icon: CheckCircle2, accent: 'text-emerald-600',delta: '+8%' },
    { label: 'TMA',               value: data.value?.kpis?.avg_handling_minutes, icon: TrendingUp,   accent: 'text-accent',   suffix: 'min', delta: '−15%' },
]);

const maxBar = computed(() => Math.max(1, ...((data.value?.timeseries || []).map(x => x.tickets || 0))));
</script>

<template>
    <Head title="Dashboard — Vora" />
    <AppLayout>
        <Topbar title="Dashboard" subtitle="Visão geral do atendimento em tempo real" />

        <div class="flex-1 overflow-y-auto scrollbar-thin">
            <div class="px-8 py-8 space-y-8 max-w-[1400px]">

                <!-- KPIs -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <Motion v-for="(k, idx) in kpis" :key="k.label"
                            :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
                            :transition="{ delay: idx * 0.06, duration: 0.4, ease: [0.22, 1, 0.36, 1] }">
                        <Card class="hover:shadow-pop transition-shadow duration-200">
                            <CardContent class="pt-6 pb-5">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="h-9 w-9 rounded-lg bg-muted flex items-center justify-center">
                                        <component :is="k.icon" :class="['h-4 w-4', k.accent]" />
                                    </div>
                                    <Badge variant="muted" class="text-[10.5px]">{{ k.delta }}</Badge>
                                </div>
                                <div class="text-[12.5px] text-muted-foreground font-medium">{{ k.label }}</div>
                                <div class="mt-1 flex items-baseline gap-1">
                                    <Skeleton v-if="loading" class="h-8 w-16" />
                                    <span v-else class="text-[28px] font-semibold text-foreground tracking-tight tabular-nums">
                                        {{ k.value ?? '—' }}
                                    </span>
                                    <span v-if="k.suffix" class="text-[13px] text-muted-foreground">{{ k.suffix }}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </Motion>
                </div>

                <!-- Timeseries + by sector -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <Motion :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
                            :transition="{ delay: 0.25, duration: 0.4 }" class="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <CardTitle>Volume últimos 14 dias</CardTitle>
                                        <CardDescription>Tickets abertos por dia</CardDescription>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[12px] text-emerald-600 font-medium">
                                        <ArrowUpRight class="h-3.5 w-3.5" />
                                        12.4% vs período anterior
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div class="h-64 flex items-end gap-1.5 pt-2">
                                    <div v-for="(d, i) in (data?.timeseries || [])" :key="i"
                                         class="group relative flex-1 rounded-t-md bg-gradient-to-t from-primary to-primary/60 hover:from-accent hover:to-accent/70 transition-all duration-200"
                                         :style="{ height: ((d.tickets || 0) / maxBar) * 100 + '%' }">
                                        <div class="absolute -top-7 left-1/2 -translate-x-1/2 bg-foreground text-background text-[10px] font-medium px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition pointer-events-none whitespace-nowrap">
                                            {{ d.tickets }} · {{ d.date }}
                                        </div>
                                    </div>
                                    <div v-if="!loading && !(data?.timeseries || []).length"
                                         class="w-full text-center text-[13px] text-muted-foreground">
                                        Sem dados
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </Motion>

                    <Motion :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
                            :transition="{ delay: 0.32, duration: 0.4 }">
                        <Card>
                            <CardHeader>
                                <CardTitle>Por setor</CardTitle>
                                <CardDescription>Tickets ativos</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ul class="space-y-3">
                                    <li v-for="s in (data?.by_sector || [])" :key="s.id"
                                        class="flex items-center justify-between group">
                                        <div class="flex items-center gap-2.5">
                                            <span class="h-2 w-2 rounded-full"
                                                  :style="{ backgroundColor: s.color || '#94A3B8' }" />
                                            <span class="text-[13px] text-foreground font-medium">{{ s.name }}</span>
                                        </div>
                                        <Badge variant="muted">{{ s.open_tickets }}</Badge>
                                    </li>
                                    <li v-if="!loading && !(data?.by_sector || []).length"
                                        class="text-[13px] text-muted-foreground py-4 text-center">
                                        Sem dados
                                    </li>
                                </ul>
                            </CardContent>
                        </Card>
                    </Motion>
                </div>

                <!-- Attendants table -->
                <Motion :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
                        :transition="{ delay: 0.4, duration: 0.4 }">
                    <Card>
                        <CardHeader>
                            <CardTitle>Atendentes</CardTitle>
                            <CardDescription>Carga atual e desempenho do dia</CardDescription>
                        </CardHeader>
                        <CardContent class="px-0 pb-0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-[13px]">
                                    <thead>
                                        <tr class="border-y border-border bg-muted/40">
                                            <th class="px-6 py-2.5 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Nome</th>
                                            <th class="px-6 py-2.5 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Em atendimento</th>
                                            <th class="px-6 py-2.5 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Resolvidos</th>
                                            <th class="px-6 py-2.5 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr v-for="a in (data?.by_attendant || [])" :key="a.id"
                                            class="hover:bg-muted/40 transition-colors">
                                            <td class="px-6 py-3">
                                                <div class="flex items-center gap-3">
                                                    <Avatar :name="a.name" :status="a.status" size="sm" />
                                                    <span class="font-medium text-foreground">{{ a.name }}</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-3 tabular-nums">{{ a.in_progress }}</td>
                                            <td class="px-6 py-3 tabular-nums">{{ a.resolved }}</td>
                                            <td class="px-6 py-3">
                                                <Badge :variant="a.status === 'online' ? 'success' : 'muted'">
                                                    {{ a.status }}
                                                </Badge>
                                            </td>
                                        </tr>
                                        <tr v-if="!loading && !(data?.by_attendant || []).length">
                                            <td colspan="4" class="text-center text-[13px] text-muted-foreground py-10">
                                                Nenhum atendente cadastrado
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
    </AppLayout>
</template>
