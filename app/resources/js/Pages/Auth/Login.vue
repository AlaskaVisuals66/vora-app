<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Motion } from 'motion-v';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { useAuth } from '@/Composables/useAuth';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Mail, Lock, AlertCircle, ArrowRight } from 'lucide-vue-next';

const { login } = useAuth();
const form = ref({ email: '', password: '' });
const loading = ref(false);
const error = ref(null);

async function submit() {
    loading.value = true;
    error.value = null;
    try {
        await login(form.value.email, form.value.password);
    } catch (e) {
        error.value = e.response?.data?.message || 'Falha ao autenticar. Verifique e-mail e senha.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <GuestLayout>
        <Head title="Entrar — Vora" />

        <Motion :initial="{ opacity: 0, y: 16 }" :animate="{ opacity: 1, y: 0 }"
                :transition="{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }">
            <!-- Brand mark above card -->
            <div class="text-center mb-8">
                <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-vora-mark text-white text-lg font-bold mb-4 shadow-accent">
                    V
                </div>
                <h1 class="text-[22px] font-semibold text-white tracking-tight">Bem-vindo de volta</h1>
                <p class="text-[13px] text-white/60 mt-1.5">Acesse sua central de atendimento Vora</p>
            </div>

            <div class="rounded-xl bg-card border border-white/10 shadow-2xl p-7">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="space-y-1.5">
                        <label class="text-[12.5px] font-medium text-foreground">E-mail</label>
                        <div class="relative">
                            <Mail class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                            <Input v-model="form.email" type="email" required
                                   placeholder="voce@empresa.com" class="pl-9" autocomplete="email" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-[12.5px] font-medium text-foreground">Senha</label>
                            <a href="#" class="text-[11.5px] text-primary hover:underline">Esqueceu?</a>
                        </div>
                        <div class="relative">
                            <Lock class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                            <Input v-model="form.password" type="password" required
                                   placeholder="••••••••" class="pl-9" autocomplete="current-password" />
                        </div>
                    </div>

                    <Motion v-if="error" :initial="{ opacity: 0, y: -4 }" :animate="{ opacity: 1, y: 0 }">
                        <div class="flex items-start gap-2 text-[12.5px] text-destructive bg-destructive/8 border border-destructive/20 rounded-md px-3 py-2">
                            <AlertCircle class="h-4 w-4 shrink-0 mt-px" />
                            <span>{{ error }}</span>
                        </div>
                    </Motion>

                    <Button type="submit" :disabled="loading" class="w-full mt-2" size="lg">
                        <span>{{ loading ? 'Entrando…' : 'Entrar' }}</span>
                        <ArrowRight v-if="!loading" class="h-4 w-4" />
                    </Button>
                </form>
            </div>

            <p class="text-center text-white/40 text-[11px] mt-6 tracking-wide">
                © {{ new Date().getFullYear() }} Vora Soluções Digitais
            </p>
        </Motion>
    </GuestLayout>
</template>
