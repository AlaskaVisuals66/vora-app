<script setup>
import Sidebar from '@/Components/Sidebar.vue';
import { usePresence } from '@/Composables/usePresence';
import { onMounted } from 'vue';
import { initEcho } from '@/lib/echo';

usePresence();
onMounted(() => initEcho());
</script>

<template>
    <div class="flex min-h-screen bg-background">
        <Sidebar />
        <main class="flex-1 flex flex-col overflow-hidden min-w-0">
            <slot />
        </main>
    </div>
</template>
