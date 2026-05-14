<script setup>
import { ref, computed } from 'vue';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/table';
import { Button } from '@/Components/ui/button';
import { ChevronUp, ChevronDown, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    // columns: [{ key, label, sortable?: bool, align?: 'left'|'right' }]
    columns: { type: Array, required: true },
    rows: { type: Array, required: true },
    pageSize: { type: Number, default: 10 },
});

const sortKey = ref(null);
const sortDir = ref('asc');
const page = ref(1);

function toggleSort(col) {
    if (!col.sortable) return;
    if (sortKey.value === col.key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = col.key;
        sortDir.value = 'asc';
    }
}

const sorted = computed(() => {
    if (!sortKey.value) return props.rows;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    return [...props.rows].sort((a, b) => {
        const av = a[sortKey.value], bv = b[sortKey.value];
        return av > bv ? dir : av < bv ? -dir : 0;
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(sorted.value.length / props.pageSize)));
const paged = computed(() => {
    const start = (page.value - 1) * props.pageSize;
    return sorted.value.slice(start, start + props.pageSize);
});
</script>

<template>
    <div class="rounded-[var(--radius)] border border-border">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead v-for="col in columns" :key="col.key"
                               :class="[col.align === 'right' ? 'text-right' : '', col.sortable ? 'cursor-pointer select-none' : '']"
                               @click="toggleSort(col)">
                        <span class="inline-flex items-center gap-1">
                            {{ col.label }}
                            <ChevronUp v-if="sortKey === col.key && sortDir === 'asc'" class="h-3.5 w-3.5" />
                            <ChevronDown v-else-if="sortKey === col.key && sortDir === 'desc'" class="h-3.5 w-3.5" />
                        </span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="(row, i) in paged" :key="i">
                    <TableCell v-for="col in columns" :key="col.key"
                               :class="col.align === 'right' ? 'text-right tabular-nums' : ''">
                        <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                            {{ row[col.key] }}
                        </slot>
                    </TableCell>
                </TableRow>
                <TableRow v-if="!paged.length">
                    <TableCell :colspan="columns.length" class="py-10 text-center text-muted-foreground">
                        Nenhum registro
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
        <div v-if="totalPages > 1" class="flex items-center justify-between border-t border-border px-3 py-2">
            <span class="text-[12px] text-muted-foreground">Página {{ page }} de {{ totalPages }}</span>
            <div class="flex gap-1">
                <Button variant="ghost" size="icon" :disabled="page === 1" @click="page--">
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="icon" :disabled="page === totalPages" @click="page++">
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
