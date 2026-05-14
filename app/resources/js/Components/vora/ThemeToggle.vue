<script setup>
import { Sun, Moon } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { useTheme } from '@/Composables/useTheme';

const { theme, toggle } = useTheme();
</script>

<template>
    <Button variant="ghost" size="icon" @click="toggle" aria-label="Alternar tema">
        <Sun v-if="theme === 'dark'" class="h-4 w-4" />
        <Moon v-else class="h-4 w-4" />
    </Button>
</template>
