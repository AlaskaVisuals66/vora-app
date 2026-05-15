<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Tooltip, TooltipContent, TooltipProvider, TooltipTrigger,
} from '@/Components/ui/tooltip';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { useAuth } from '@/Composables/useAuth';
import {
    LayoutDashboard, MessagesSquare, Users, Building2, Settings,
    PanelLeftClose, PanelLeftOpen,
} from 'lucide-vue-next';

const collapsed = ref(true);
const hovered = ref(false);
const expanded = () => !collapsed.value || hovered.value;

const page = usePage();
const { user } = useAuth();
const nav = [
    { label: 'Início',     icon: LayoutDashboard, href: '/dashboard' },
    { label: 'Conversas',  icon: MessagesSquare,  href: '/conversations' },
    { label: 'Setores',    icon: Building2,       href: '/sectors' },
    { label: 'Usuários',   icon: Users,           href: '/users' },
    { label: 'Configurações', icon: Settings,     href: '/settings' },
];

function isActive(href) {
    return page.url.startsWith(href);
}

const initials = (name) => (name || '?')
    .split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();

const roleLabels = { admin: 'Admin', supervisor: 'Supervisor', attendant: 'Atendente' };
const roleLabel = computed(() => {
    const r = user.value?.roles?.[0];
    const name = typeof r === 'string' ? r : r?.name;
    return roleLabels[name] || name || '';
});
</script>

<template>
    <aside
        class="flex h-screen flex-col overflow-hidden bg-sidebar text-sidebar-foreground transition-[width] duration-200 ease-out"
        :class="expanded() ? 'w-60' : 'w-[64px]'"
        @mouseenter="hovered = true"
        @mouseleave="hovered = false"
    >
        <!-- Wordmark -->
        <div class="flex h-14 items-center px-4">
            <span class="text-[17px] font-semibold tracking-tight text-white">
                <template v-if="expanded()">Vora<span class="text-sidebar-muted">.</span></template>
                <template v-else>V<span class="text-sidebar-muted">.</span></template>
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
                                ? 'bg-sidebar-active text-sidebar font-semibold'
                                : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-white'"
                        >
                            <component :is="item.icon" class="h-[18px] w-[18px] shrink-0" />
                            <span v-if="expanded()" class="truncate">{{ item.label }}</span>
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent v-if="!expanded()" side="right">{{ item.label }}</TooltipContent>
                </Tooltip>
            </nav>

            <!-- Identity -->
            <div class="border-t border-sidebar-border p-2">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Link
                            href="/profile"
                            class="flex items-center gap-3 rounded-[var(--radius)] px-2 py-2 transition-colors"
                            :class="isActive('/profile')
                                ? 'bg-sidebar-accent'
                                : 'hover:bg-sidebar-accent'"
                        >
                            <Avatar class="h-8 w-8 shrink-0">
                                <AvatarFallback class="bg-sidebar-accent text-[11px] text-sidebar-foreground">
                                    {{ initials(user?.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div v-if="expanded()" class="min-w-0">
                                <div class="truncate text-[12.5px] font-medium text-white">
                                    {{ user?.name || 'Usuário' }}
                                </div>
                                <div class="truncate text-[11px] text-sidebar-muted">{{ roleLabel }}</div>
                            </div>
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent v-if="!expanded()" side="right">{{ user?.name || 'Perfil' }}</TooltipContent>
                </Tooltip>
            </div>
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
