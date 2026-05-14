<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem,
    DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { LogOut, User as UserIcon } from 'lucide-vue-next';
import { useAuth } from '@/Composables/useAuth';

const { user, logout } = useAuth();

const initials = computed(() => {
    const n = user.value?.name || 'Vora';
    return n.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button class="flex items-center gap-2 rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <Avatar class="h-8 w-8">
                    <AvatarFallback>{{ initials }}</AvatarFallback>
                </Avatar>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-52">
            <DropdownMenuLabel>
                <div class="font-medium text-foreground">{{ user?.name || 'Usuário' }}</div>
                <div class="text-[12px] font-normal text-muted-foreground">{{ user?.email }}</div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="router.visit('/profile')">
                <UserIcon class="mr-2 h-4 w-4" /> Perfil
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="logout">
                <LogOut class="mr-2 h-4 w-4" /> Sair
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
