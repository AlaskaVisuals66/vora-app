<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Tooltip, TooltipContent, TooltipProvider, TooltipTrigger,
} from '@/Components/ui/tooltip';
import {
    LayoutDashboard, MessagesSquare, Users, Building2, Settings,
    PanelLeftClose, PanelLeftOpen,
} from 'lucide-vue-next';

const collapsed = ref(true);
const hovered = ref(false);
const expanded = () => !collapsed.value || hovered.value;

const page = usePage();
const nav = [
    { label: 'Dashboard',  icon: LayoutDashboard, href: '/dashboard' },
    { label: 'Conversas',  icon: MessagesSquare,  href: '/conversations' },
    { label: 'Setores',    icon: Building2,       href: '/sectors' },
    { label: 'Usuários',   icon: Users,           href: '/users' },
    { label: 'Configurações', icon: Settings,     href: '/settings' },
];

function isActive(href) {
    return page.url.startsWith(href);
}
</script>

<template>
    <aside
        class="flex h-screen flex-col bg-sidebar text-sidebar-foreground transition-[width] duration-200 ease-out"
        :class="expanded() ? 'w-60' : 'w-[64px]'"
        @mouseenter="hovered = true"
        @mouseleave="hovered = false"
    >
        <!-- Wordmark -->
        <div class="flex h-14 items-center px-4">
            <span class="text-[17px] font-semibold tracking-tight text-white">
                <template v-if="expanded()">Vora<span class="text-vora-orange">.</span></template>
                <template v-else>V<span class="text-vora-orange">.</span></template>
            </span>
        </div>

        <!-- Nav -->
        <TooltipProvider :delay-duration="0">
            <nav class="flex-1 space-y-1 px-2 py-2">
                <Tooltip v-for="item in nav" :key="item.href">
                    <TooltipTrigger as-child>
                        <Link
                            :href="item.href"
                            class="flex items-center gap-3 rounded-[var(--radius)] px-3 py-2 text-[13px] font-medium transition-colors"
                            :class="isActive(item.href)
                                ? 'bg-sidebar-active text-white'
                                : 'text-sidebar-foreground hover:bg-sidebar-accent'"
                        >
                            <component :is="item.icon" class="h-[18px] w-[18px] shrink-0" />
                            <span v-if="expanded()" class="truncate">{{ item.label }}</span>
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent v-if="!expanded()" side="right">{{ item.label }}</TooltipContent>
                </Tooltip>
            </nav>
        </TooltipProvider>

        <!-- Collapse toggle -->
        <div class="border-t border-sidebar-border p-2">
            <button
                class="flex w-full items-center gap-3 rounded-[var(--radius)] px-3 py-2 text-[13px] text-sidebar-muted transition-colors hover:bg-sidebar-accent hover:text-sidebar-foreground"
                @click="collapsed = !collapsed"
            >
                <component :is="collapsed ? PanelLeftOpen : PanelLeftClose" class="h-[18px] w-[18px] shrink-0" />
                <span v-if="expanded()">Recolher</span>
            </button>
        </div>
    </aside>
</template>
