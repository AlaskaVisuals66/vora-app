<script setup>
import { watch, onBeforeUnmount } from 'vue';
import { Motion } from 'motion-v';
import { X } from 'lucide-vue-next';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    width: { type: String, default: 'max-w-md' },
});
const emit = defineEmits(['update:open','close']);

function close() { emit('update:open', false); emit('close'); }
function onKey(e) { if (e.key === 'Escape' && props.open) close(); }

watch(() => props.open, (v) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = v ? 'hidden' : '';
    if (v) document.addEventListener('keydown', onKey);
    else document.removeEventListener('keydown', onKey);
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
        document.removeEventListener('keydown', onKey);
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="absolute inset-0 bg-foreground/40 backdrop-blur-[2px]" @click="close" />
                <Motion :initial="{ opacity: 0, y: 8, scale: 0.98 }"
                        :animate="{ opacity: 1, y: 0, scale: 1 }"
                        :transition="{ duration: 0.18, ease: [0.22, 1, 0.36, 1] }"
                        class="relative w-full"
                        :class="width">
                    <div class="bg-card border border-border rounded-xl shadow-pop overflow-hidden">
                        <header v-if="title || description" class="flex items-start justify-between gap-4 px-5 pt-5 pb-3 border-b border-border">
                            <div class="min-w-0">
                                <h3 v-if="title" class="text-[15px] font-semibold text-foreground tracking-tight">{{ title }}</h3>
                                <p v-if="description" class="text-[12.5px] text-muted-foreground mt-0.5">{{ description }}</p>
                            </div>
                            <button type="button" @click="close"
                                    class="text-muted-foreground hover:text-foreground transition-colors p-1 -mr-1 -mt-1">
                                <X class="h-4 w-4" />
                            </button>
                        </header>
                        <slot />
                    </div>
                </Motion>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
