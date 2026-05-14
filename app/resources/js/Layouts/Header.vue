<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Search, Bell, LayoutDashboard, MessagesSquare, Building2, Users, Settings,
    User as UserIcon,
} from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
    CommandDialog, CommandInput, CommandList, CommandEmpty, CommandGroup, CommandItem,
} from '@/Components/ui/command';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuLabel, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import ThemeToggle from '@/Components/vora/ThemeToggle.vue';
import UserMenu from '@/Components/vora/UserMenu.vue';

defineProps({
    title: { type: String, default: '' },
});

const searchOpen = ref(false);

const navItems = [
    { label: 'Dashboard',     icon: LayoutDashboard, href: '/dashboard' },
    { label: 'Conversas',     icon: MessagesSquare,  href: '/conversations' },
    { label: 'Setores',       icon: Building2,       href: '/sectors' },
    { label: 'Usuários',      icon: Users,           href: '/users' },
    { label: 'Configurações', icon: Settings,        href: '/settings' },
    { label: 'Perfil',        icon: UserIcon,        href: '/profile' },
];

function go(href) {
    searchOpen.value = false;
    router.visit(href);
}

function onKeydown(e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        searchOpen.value = !searchOpen.value;
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <header class="flex h-14 shrink-0 items-center justify-between border-b border-border bg-card px-5">
        <div class="flex items-center gap-3">
            <h2 v-if="title" class="text-[14px] font-semibold tracking-tight text-foreground">{{ title }}</h2>
        </div>
        <div class="flex items-center gap-1">
            <Button variant="ghost" size="icon" aria-label="Buscar" @click="searchOpen = true">
                <Search class="h-4 w-4" />
            </Button>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" aria-label="Notificações">
                        <Bell class="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-72">
                    <DropdownMenuLabel>Notificações</DropdownMenuLabel>
                    <div class="flex flex-col items-center gap-1 px-4 py-8 text-center">
                        <Bell class="h-5 w-5 text-muted-foreground/50" />
                        <p class="text-[12.5px] text-muted-foreground">Nenhuma notificação</p>
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>
            <ThemeToggle />
            <div class="mx-1 h-5 w-px bg-border" />
            <UserMenu />
        </div>

        <CommandDialog v-model:open="searchOpen">
            <CommandInput placeholder="Buscar páginas…" />
            <CommandList>
                <CommandEmpty>Nenhum resultado.</CommandEmpty>
                <CommandGroup heading="Navegação">
                    <CommandItem v-for="item in navItems" :key="item.href"
                                 :value="item.label" @select="go(item.href)">
                        <component :is="item.icon" class="mr-2 h-4 w-4 text-muted-foreground" />
                        {{ item.label }}
                    </CommandItem>
                </CommandGroup>
            </CommandList>
        </CommandDialog>
    </header>
</template>
