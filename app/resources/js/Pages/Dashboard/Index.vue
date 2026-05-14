<script setup>
import { onMounted, ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Motion } from 'motion-v';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/vora/PageHeader.vue';
import EmptyState from '@/Components/vora/EmptyState.vue';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Skeleton } from '@/Components/ui/skeleton';
import { Inbox, Headphones, CheckCircle2, Clock, Users, BarChart3 } from 'lucide-vue-next';

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

const cards = computed(() => [
    {
        label: 'Tickets em aberto',
        value: data.value?.kpis?.open_tickets ?? 0,
        icon: Inbox,
        hint: 'Aguardando atendimento',
    },
    {
        label: 'Em atendimento',
        value: data.value?.kpis?.in_progress ?? 0,
        icon: Headphones,
        hint: 'Sendo atendidos agora',
    },
    {
        label: 'Resolvidos hoje',
        value: data.value?.kpis?.resolved_today ?? 0,
        icon: CheckCircle2,
        hint: 'Finalizados hoje',
    },
    {
        label: 'Tempo médio',
        value: Math.max(0, data.value?.kpis?.avg_handling_minutes ?? 0),
        icon: Clock,
        suffix: 'min',
        hint: 'Para resolver um ticket',
    },
]);

// Last 7 days only — fewer data points, faster to read.
const week = computed(() => (data.value?.timeseries || []).slice(-7));
const maxTickets = computed(() => Math.max(1, ...week.value.map(d => d.tickets || 0)));
const barHeight = (d) => `${Math.max(3, Math.round(((d.tickets || 0) / maxTickets.value) * 100))}%`;

const sectors = computed(() => data.value?.by_sector || []);
const attendants = computed(() => data.value?.by_attendant || []);

const initials = (name) => (name || '?')
    .split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();

const roleLabels = { admin: 'Admin', supervisor: 'Supervisor', attendant: 'Atendente' };
const roleLabel = (r) => roleLabels[r] || r || 'Atendente';
</script>

<template>
    <Head title="Início — Vora" />
    <AppLayout title="Início">
        <div class="px-8 py-8 space-y-8 max-w-[1200px]">

            <PageHeader title="Início" description="Veja como está o atendimento hoje" />

            <!-- KPIs -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <Motion v-for="(c, idx) in cards" :key="c.label"
                        :initial="{ opacity: 0, y: 10 }" :animate="{ opacity: 1, y: 0 }"
                        :transition="{ delay: idx * 0.06, duration: 0.35, ease: [0.22, 1, 0.36, 1] }">
                    <Card class="p-6">
                        <div class="flex items-center justify-between">
                            <span class="text-[13px] font-medium text-muted-foreground">{{ c.label }}</span>
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-muted">
                                <component :is="c.icon" class="h-4 w-4 text-muted-foreground" />
                            </div>
                        </div>
                        <div class="mt-5 flex items-baseline gap-1.5">
                            <Skeleton v-if="loading" class="h-9 w-20" />
                            <template v-else>
                                <span class="text-[34px] font-semibold leading-none tracking-tight text-foreground tabular-nums">
                                    {{ c.value }}
                                </span>
                                <span v-if="c.suffix" class="text-[15px] text-muted-foreground">{{ c.suffix }}</span>
                            </template>
                        </div>
                        <p class="mt-2.5 text-[12px] text-muted-foreground">{{ c.hint }}</p>
                    </Card>
                </Motion>
            </div>

            <!-- Chart + sectors -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <Motion :initial="{ opacity: 0, y: 10 }" :animate="{ opacity: 1, y: 0 }"
                        :transition="{ delay: 0.24, duration: 0.35 }" class="lg:col-span-2">
                    <Card class="p-6">
                        <div class="mb-1">
                            <h3 class="text-[15px] font-semibold tracking-tight text-foreground">
                                Atendimentos nos últimos 7 dias
                            </h3>
                            <p class="text-[12.5px] text-muted-foreground">Quantos tickets foram abertos por dia</p>
                        </div>

                        <div v-if="loading" class="mt-6 flex items-end gap-2.5 h-44">
                            <Skeleton v-for="i in 7" :key="i" class="flex-1" :style="{ height: (30 + i * 8) + '%' }" />
                        </div>

                        <template v-else-if="week.length">
                            <div class="mt-6 flex items-end gap-2.5 h-44">
                                <div v-for="(d, i) in week" :key="i"
                                     class="group relative flex-1 rounded-t-md bg-foreground/75 hover:bg-foreground transition-colors duration-200"
                                     :style="{ height: barHeight(d) }">
                                    <div class="pointer-events-none absolute -top-9 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-foreground px-2 py-1 text-[11px] font-medium text-background opacity-0 transition-opacity group-hover:opacity-100">
                                        {{ d.tickets }} atendimento{{ d.tickets === 1 ? '' : 's' }} · {{ d.date }}
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2.5 flex gap-2.5">
                                <span v-for="(d, i) in week" :key="i"
                                      class="flex-1 text-center text-[11px] text-muted-foreground tabular-nums">
                                    {{ d.date }}
                                </span>
                            </div>
                        </template>

                        <EmptyState v-else :icon="BarChart3" title="Ainda sem dados"
                                    description="Os atendimentos aparecem aqui assim que começarem." />
                    </Card>
                </Motion>

                <Motion :initial="{ opacity: 0, y: 10 }" :animate="{ opacity: 1, y: 0 }"
                        :transition="{ delay: 0.3, duration: 0.35 }">
                    <Card class="p-6">
                        <div class="mb-3">
                            <h3 class="text-[15px] font-semibold tracking-tight text-foreground">Por setor</h3>
                            <p class="text-[12.5px] text-muted-foreground">Tickets ativos em cada setor</p>
                        </div>

                        <div v-if="loading" class="space-y-3 pt-1">
                            <div v-for="i in 4" :key="i" class="flex items-center justify-between">
                                <Skeleton class="h-3.5 w-1/2" />
                                <Skeleton class="h-3.5 w-6" />
                            </div>
                        </div>

                        <ul v-else-if="sectors.length" class="divide-y divide-border">
                            <li v-for="s in sectors" :key="s.id"
                                class="flex items-center justify-between py-2.5">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="h-2 w-2 rounded-full shrink-0"
                                          :style="{ backgroundColor: s.color || '#94A3B8' }" />
                                    <span class="text-[13px] text-foreground truncate">{{ s.name }}</span>
                                </div>
                                <span class="text-[13px] font-semibold text-foreground tabular-nums">{{ s.open_tickets }}</span>
                            </li>
                        </ul>

                        <p v-else class="text-[13px] text-muted-foreground py-6 text-center">
                            Nenhum setor cadastrado ainda.
                        </p>
                    </Card>
                </Motion>
            </div>

            <!-- Attendants -->
            <Motion :initial="{ opacity: 0, y: 10 }" :animate="{ opacity: 1, y: 0 }"
                    :transition="{ delay: 0.36, duration: 0.35 }">
                <Card class="overflow-hidden">
                    <div class="px-6 py-5">
                        <h3 class="text-[15px] font-semibold tracking-tight text-foreground">Atendentes</h3>
                        <p class="text-[12.5px] text-muted-foreground">Quem está atendendo agora</p>
                    </div>

                    <div v-if="loading" class="px-6 pb-6 space-y-2">
                        <Skeleton v-for="i in 4" :key="i" class="h-12 w-full" />
                    </div>

                    <div v-else-if="attendants.length" class="overflow-x-auto">
                        <table class="w-full text-[13px]">
                            <thead>
                                <tr class="border-y border-border bg-muted/40">
                                    <th class="px-6 py-2.5 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Atendente</th>
                                    <th class="px-6 py-2.5 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Função</th>
                                    <th class="px-6 py-2.5 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Em atendimento</th>
                                    <th class="px-6 py-2.5 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Resolvidos hoje</th>
                                    <th class="px-6 py-2.5 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="a in attendants" :key="a.id"
                                    class="hover:bg-muted/40 transition-colors">
                                    <td class="px-6 py-3.5">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <Avatar class="h-9 w-9">
                                                <AvatarFallback class="text-[12px]">{{ initials(a.name) }}</AvatarFallback>
                                            </Avatar>
                                            <div class="min-w-0">
                                                <div class="font-medium text-foreground truncate">{{ a.name }}</div>
                                                <div class="text-[11.5px] text-muted-foreground truncate">{{ a.email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3.5">
                                        <Badge variant="outline">{{ roleLabel(a.role) }}</Badge>
                                    </td>
                                    <td class="px-6 py-3.5 tabular-nums text-foreground">{{ a.in_progress }}</td>
                                    <td class="px-6 py-3.5 tabular-nums text-foreground">{{ a.resolved }}</td>
                                    <td class="px-6 py-3.5">
                                        <span class="inline-flex items-center gap-2">
                                            <span class="h-2 w-2 rounded-full"
                                                  :class="a.status === 'online' ? 'bg-foreground' : 'bg-muted-foreground/30'" />
                                            <span class="text-[12.5px]"
                                                  :class="a.status === 'online' ? 'text-foreground' : 'text-muted-foreground'">
                                                {{ a.status === 'online' ? 'Online' : 'Offline' }}
                                            </span>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <EmptyState v-else :icon="Users" title="Nenhum atendente cadastrado"
                                description="Convide sua equipe na página de Usuários para começar a atender." />
                </Card>
            </Motion>
        </div>
    </AppLayout>
</template>
