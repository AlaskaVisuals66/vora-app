<script setup>
import { onMounted } from 'vue';
import Sidebar from '@/Layouts/Sidebar.vue';
import Header from '@/Layouts/Header.vue';
import { Toaster } from '@/Components/ui/sonner';
import { usePresence } from '@/Composables/usePresence';
import { initEcho } from '@/lib/echo';

defineProps({
    title: { type: String, default: '' },
});

usePresence();
onMounted(() => initEcho());
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-background">
        <Sidebar />
        <div class="flex min-w-0 flex-1 flex-col">
            <Header :title="title" />
            <main class="min-h-0 flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>
        <Toaster rich-colors position="top-right" />
    </div>
</template>
