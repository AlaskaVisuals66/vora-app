<script setup>
import { onMounted, provide, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Sidebar from '@/Layouts/Sidebar.vue';
import Header from '@/Layouts/Header.vue';
import { Toaster } from '@/Components/ui/sonner';
import { usePresence } from '@/Composables/usePresence';
import { initEcho } from '@/lib/echo';

defineProps({
    title: { type: String, default: '' },
});

const sidebarOpen = ref(false);
function toggleSidebar() { sidebarOpen.value = !sidebarOpen.value; }
function closeSidebar() { sidebarOpen.value = false; }
provide('sidebarOpen', sidebarOpen);
provide('toggleSidebar', toggleSidebar);
provide('closeSidebar', closeSidebar);

router.on('navigate', () => { sidebarOpen.value = false; });

usePresence();
onMounted(() => initEcho());
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-background">
        <Sidebar />
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-black/40 md:hidden"
            @click="closeSidebar"
            aria-hidden="true"
        />
        <div class="flex min-w-0 flex-1 flex-col">
            <Header :title="title" />
            <main class="min-h-0 flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
    <Toaster position="bottom-right" />
</template>
