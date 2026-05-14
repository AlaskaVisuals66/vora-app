<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useAuth } from '@/Composables/useAuth';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import axios from 'axios';
import {
    LayoutDashboard, MessagesSquare, Layers, Users, Settings, LogOut,
} from 'lucide-vue-next';

const { user, isAdmin, logout } = useAuth();
const page = usePage();
const sectors = ref([]);

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/v1/sectors');
        sectors.value = data.data || [];
    } catch (_) {}
});

const items = computed(() => [
    { name: 'Dashboard',     href: '/dashboard',      icon: LayoutDashboard,  show: true },
    { name: 'Conversas',     href: '/conversations',  icon: MessagesSquare,   show: true },
    { name: 'Setores',       href: '/sectors',        icon: Layers,           show: isAdmin.value },
    { name: 'Usuários',      href: '/users',          icon: Users,            show: isAdmin.value },
    { name: 'Configurações', href: '/settings',       icon: Settings,         show: isAdmin.value },
].filter(i => i.show));

const conversationsActive = computed(() =>
    page.url === '/conversations' || /^\/conversations(\/(?!sector)|\/sector\/)/.test(page.url)
);
const isActive = (href) => {
    if (href === '/conversations') return conversationsActive.value && !page.url.includes('/sector/');
    return page.url === href || page.url.startsWith(href + '/') || page.url.startsWith(href + '?');
};
const sectorActive = (slug) => page.url.startsWith(`/conversations/sector/${slug}`);

const initials = (name) => (name || '?')
    .split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
</script>

<template>
    <aside class="hidden md:flex md:flex-col w-64 bg-sidebar text-sidebar-foreground sticky top-0 h-screen border-r border-sidebar-border">
        <!-- Nav -->
        <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto scrollbar-thin">
            <div class="px-3 mb-2 text-[10px] font-medium text-sidebar-muted uppercase tracking-[0.14em]">Vora</div>

            <template v-for="item in items" :key="item.href">
                <Link :href="item.href"
                      :class="[
                          'group relative flex items-center gap-3 px-3 py-2 rounded-md text-[13px] font-medium transition-colors duration-150',
                          isActive(item.href)
                              ? 'bg-sidebar-accent text-white'
                              : 'text-sidebar-foreground/80 hover:bg-white/[0.04] hover:text-white'
                      ]">
                    <span :class="['absolute left-0 top-1/2 -translate-y-1/2 h-5 w-[2px] rounded-r-full bg-white/60 transition-opacity',
                                   isActive(item.href) ? 'opacity-100' : 'opacity-0']" />
                    <component :is="item.icon" :class="['h-[16px] w-[16px] shrink-0 transition-colors',
                                                        isActive(item.href) ? 'text-white' : 'text-sidebar-muted group-hover:text-white']" />
                    <span class="flex-1 truncate">{{ item.name }}</span>
                </Link>

                <!-- Sector sub-nav -->
                <div v-if="item.href === '/conversations' && sectors.length"
                     class="ml-3 my-1 space-y-px border-l border-sidebar-border/60 pl-3">
                    <Link v-for="s in sectors" :key="s.id"
                          :href="`/conversations/sector/${s.slug}`"
                          :class="['flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-[12px] transition-colors duration-150',
                                   sectorActive(s.slug)
                                       ? 'bg-white/[0.06] text-white'
                                       : 'text-sidebar-foreground/60 hover:bg-white/[0.04] hover:text-white']">
                        <span class="h-1.5 w-1.5 rounded-full shrink-0 bg-sidebar-muted" />
                        <span class="flex-1 truncate">{{ s.name }}</span>
                    </Link>
                </div>
            </template>
        </nav>

        <!-- User -->
        <div class="border-t border-sidebar-border p-3">
            <div class="flex items-center gap-2.5 px-2 py-1.5 rounded-md hover:bg-white/[0.04] transition-colors">
                <Avatar><AvatarFallback>{{ initials(user?.name) }}</AvatarFallback></Avatar>
                <div class="flex-1 min-w-0">
                    <div class="text-[12.5px] font-medium text-white truncate leading-tight">{{ user?.name }}</div>
                    <div class="text-[10.5px] text-sidebar-muted truncate leading-tight mt-0.5 capitalize">{{ (user?.roles || [])[0] || 'attendant' }}</div>
                </div>
                <button @click="logout"
                        class="text-sidebar-muted hover:text-white transition-colors p-1.5 rounded-md hover:bg-white/[0.06]"
                        title="Sair">
                    <LogOut class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    </aside>
</template>
