<script setup>
import { Search, Bell } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import ThemeToggle from '@/Components/vora/ThemeToggle.vue';
import UserMenu from '@/Components/vora/UserMenu.vue';

defineProps({
    title: { type: String, default: '' },
});
</script>

<template>
    <header class="flex h-14 shrink-0 items-center justify-between border-b border-border bg-card px-5">
        <div class="flex items-center gap-3">
            <h2 v-if="title" class="text-[14px] font-semibold tracking-tight text-foreground">{{ title }}</h2>
        </div>
        <div class="flex items-center gap-1">
            <Button variant="ghost" size="icon" aria-label="Buscar">
                <Search class="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon" aria-label="Notificações">
                <Bell class="h-4 w-4" />
            </Button>
            <ThemeToggle />
            <div class="mx-1 h-5 w-px bg-border" />
            <UserMenu />
        </div>
    </header>
</template>
