<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard, MessagesSquare, Users, Building2, Settings, FlaskConical,
} from 'lucide-vue-next';

const isDev = import.meta.env.DEV;
const page  = usePage();

const nav = [
    { label: 'Início',        icon: LayoutDashboard, href: '/dashboard' },
    { label: 'Conversas',     icon: MessagesSquare,  href: '/conversations' },
    { label: 'Setores',       icon: Building2,       href: '/sectors' },
    { label: 'Usuários',      icon: Users,           href: '/users' },
    { label: 'Configurações', icon: Settings,        href: '/settings' },
];

const devNav = [
    { label: 'Simulador', icon: FlaskConical, href: '/simulator' },
];

function isActive(href) {
    return page.url.startsWith(href);
}
</script>

<template>
    <aside class="flex h-screen w-60 shrink-0 flex-col overflow-hidden bg-sidebar text-sidebar-foreground">
        <!-- Logo -->
        <div class="flex h-14 items-center px-5">
            <img src="/images/logo.png" alt="Vora" class="h-8 w-auto object-contain" />
        </div>

        <!-- Nav -->
        <nav class="flex-1 space-y-0.5 px-2 py-2">
            <Link
                v-for="item in nav"
                :key="item.href"
                :href="item.href"
                class="flex h-10 w-full items-center gap-3 rounded-[var(--radius)] px-3 text-[13px] font-medium transition-colors"
                :class="isActive(item.href)
                    ? 'bg-[#E9590C] text-white font-semibold'
                    : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-white'"
            >
                <component :is="item.icon" class="h-[18px] w-[18px] shrink-0" />
                <span class="truncate">{{ item.label }}</span>
            </Link>
        </nav>

        <!-- Dev tools -->
        <template v-if="isDev">
            <div class="mx-3 border-t border-sidebar-accent/40" />
            <div class="px-2 py-2">
                <Link
                    v-for="item in devNav"
                    :key="item.href"
                    :href="item.href"
                    class="flex h-9 w-full items-center gap-3 rounded-[var(--radius)] px-3 text-[12px] font-medium transition-colors"
                    :class="isActive(item.href)
                        ? 'bg-[#E9590C] text-white font-semibold'
                        : 'text-sidebar-muted hover:bg-sidebar-accent hover:text-white'"
                >
                    <component :is="item.icon" class="h-[15px] w-[15px] shrink-0" />
                    <span class="truncate">{{ item.label }}</span>
                </Link>
            </div>
        </template>
    </aside>
</template>
