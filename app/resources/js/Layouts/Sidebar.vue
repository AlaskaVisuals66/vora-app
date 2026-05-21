<script setup>
import { computed, inject } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard, MessagesSquare, Users, Building2, Settings, FlaskConical,
} from 'lucide-vue-next';
import { useAuth } from '@/Composables/useAuth';

const isDev = import.meta.env.DEV;
const page  = usePage();
const { isAdmin } = useAuth();
const sidebarOpen = inject('sidebarOpen', null);
const closeSidebar = inject('closeSidebar', () => {});

const allNav = [
    { label: 'Início',        icon: LayoutDashboard, href: '/dashboard',   adminOnly: true },
    { label: 'Conversas',     icon: MessagesSquare,  href: '/conversations' },
    { label: 'Setores',       icon: Building2,       href: '/sectors',     adminOnly: true },
    { label: 'Usuários',      icon: Users,           href: '/users',       adminOnly: true },
    { label: 'Configurações', icon: Settings,        href: '/settings',    adminOnly: true },
];
const nav = computed(() => allNav.filter(item => !item.adminOnly || isAdmin.value));

const devNav = [
    { label: 'Simulador', icon: FlaskConical, href: '/simulator' },
];

function isActive(href) {
    return page.url.startsWith(href);
}
</script>

<template>
    <aside
        class="fixed inset-y-0 left-0 z-40 flex h-screen w-60 shrink-0 transform flex-col overflow-hidden bg-sidebar text-sidebar-foreground transition-transform duration-200 ease-out md:relative md:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full'"
    >
        <!-- Logo -->
        <div class="flex h-[76px] shrink-0 items-center px-3 pt-4 pb-3">
            <img src="/images/logo.png" alt="Vora"
                 class="block object-contain"
                 style="max-width: 140px; max-height: 50px; height: 100px; width: auto;" />
        </div>

        <!-- Nav -->
        <nav class="flex-1 space-y-0.5 px-2 pb-2">
            <Link
                v-for="item in nav"
                :key="item.href"
                :href="item.href"
                class="flex h-10 w-full items-center gap-3 rounded-[var(--radius)] px-3 text-[13px] font-medium transition-colors"
                :class="isActive(item.href)
                    ? 'bg-[#EF351A] text-white font-semibold'
                    : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-white'"
                @click="closeSidebar"
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
                        ? 'bg-[#EF351A] text-white font-semibold'
                        : 'text-sidebar-muted hover:bg-sidebar-accent hover:text-white'"
                    @click="closeSidebar"
                >
                    <component :is="item.icon" class="h-[15px] w-[15px] shrink-0" />
                    <span class="truncate">{{ item.label }}</span>
                </Link>
            </div>
        </template>
    </aside>
</template>
