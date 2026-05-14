<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Card } from '@/Components/ui/card';
import MetricCard from '@/Components/vora/MetricCard.vue';
import ChartCard from '@/Components/vora/ChartCard.vue';
import EmptyState from '@/Components/vora/EmptyState.vue';
import LoadingSkeleton from '@/Components/vora/LoadingSkeleton.vue';
import DataTable from '@/Components/vora/DataTable.vue';
import PageHeader from '@/Components/vora/PageHeader.vue';
import { MessagesSquare, Activity } from 'lucide-vue-next';

const tableColumns = [
    { key: 'name', label: 'Nome', sortable: true },
    { key: 'tickets', label: 'Tickets', sortable: true, align: 'right' },
];
const tableRows = [
    { name: 'Ana', tickets: 12 },
    { name: 'Bruno', tickets: 7 },
    { name: 'Carla', tickets: 19 },
];
</script>

<template>
    <Head title="UI Showcase" />
    <AppLayout title="UI Showcase">
        <div class="space-y-10 p-8">
            <PageHeader title="Showcase de componentes" description="Todos os componentes do design system Vora — claro e escuro." />

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-muted-foreground">Buttons</h3>
                <div class="flex flex-wrap gap-2">
                    <Button>Default</Button>
                    <Button variant="secondary">Secondary</Button>
                    <Button variant="outline">Outline</Button>
                    <Button variant="ghost">Ghost</Button>
                    <Button variant="destructive">Destructive</Button>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-muted-foreground">Inputs & Badges</h3>
                <div class="flex flex-wrap items-center gap-3">
                    <Input class="w-64" placeholder="Digite algo…" />
                    <Badge>Default</Badge>
                    <Badge variant="secondary">Secondary</Badge>
                    <Badge variant="outline">Outline</Badge>
                    <Badge variant="destructive">Destructive</Badge>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-muted-foreground">Metric cards</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <MetricCard label="Tickets abertos" :value="42" :delta="8" :icon="MessagesSquare" />
                    <MetricCard label="TMA (min)" :value="6.4" :delta="-3" :icon="Activity" />
                    <MetricCard label="Na fila" :value="5" />
                    <MetricCard label="Resolvidos hoje" :value="128" :delta="12" />
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <ChartCard title="Volume 14 dias" subtitle="Mensagens recebidas">
                    <div class="flex h-full items-center justify-center text-muted-foreground">[gráfico]</div>
                </ChartCard>
                <Card class="p-5">
                    <EmptyState :icon="MessagesSquare" title="Nenhuma conversa"
                                description="Quando chegar uma mensagem ela aparece aqui." />
                </Card>
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-muted-foreground">DataTable</h3>
                <DataTable :columns="tableColumns" :rows="tableRows" :page-size="2" />
            </section>

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-muted-foreground">Loading skeletons</h3>
                <LoadingSkeleton variant="cards" />
                <LoadingSkeleton variant="list" :rows="3" />
            </section>
        </div>
    </AppLayout>
</template>
