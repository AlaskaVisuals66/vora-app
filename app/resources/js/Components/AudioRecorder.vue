<script setup>
import { ref, onBeforeUnmount } from 'vue';
import { Mic, Square, Send, X, AlertCircle } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

const emit = defineEmits(['send', 'cancel']);

const isRecording = ref(false);
const duration = ref(0);
const audioBlob = ref(null);
const audioUrl = ref(null);
const timer = ref(null);
const error = ref(null);
let mediaRecorder = null;
let chunks = [];
let startTime = 0;

// Tenta o melhor formato de áudio disponível no browser
function getSupportedMimeType() {
    const types = [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/ogg;codecs=opus',
        'audio/mp4;codecs=mp4a.40.2',
        'audio/mp4',
        'audio/aac',
    ];
    for (const t of types) {
        if (MediaRecorder.isTypeSupported(t)) return t;
    }
    return ''; // browser decide
}

async function startRecording() {
    error.value = null;
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const mimeType = getSupportedMimeType();
        const options = mimeType ? { mimeType } : {};
        mediaRecorder = new MediaRecorder(stream, options);
        chunks = [];

        mediaRecorder.ondataavailable = (e) => {
            if (e.data.size > 0) chunks.push(e.data);
        };

        mediaRecorder.onstop = () => {
            const type = mimeType || mediaRecorder.mimeType || 'audio/webm';
            const blob = new Blob(chunks, { type });
            audioBlob.value = blob;
            audioUrl.value = URL.createObjectURL(blob);
            stream.getTracks().forEach(t => t.stop());
        };

        mediaRecorder.onerror = () => {
            error.value = 'Erro ao gravar áudio.';
            isRecording.value = false;
            stream.getTracks().forEach(t => t.stop());
        };

        mediaRecorder.start(250);
        isRecording.value = true;
        duration.value = 0;
        startTime = Date.now();
        timer.value = setInterval(() => {
            duration.value = Math.floor((Date.now() - startTime) / 1000);
        }, 500);
    } catch (err) {
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            error.value = 'Permissão do microfone negada.';
        } else if (err.name === 'NotFoundError') {
            error.value = 'Microfone não encontrado.';
        } else {
            error.value = 'Microfone indisponível.';
        }
        console.error('Microphone error', err);
    }
}

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
    clearInterval(timer.value);
    isRecording.value = false;
}

function cancelRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.ondataavailable = null;
        mediaRecorder.onstop = null;
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(t => t.stop());
    }
    clearInterval(timer.value);
    isRecording.value = false;
    audioBlob.value = null;
    audioUrl.value = null;
    chunks = [];
    error.value = null;
    emit('cancel');
}

function sendRecording() {
    if (!audioBlob.value) return;
    const ext = audioBlob.value.type.includes('mp4') ? 'mp4' : 'webm';
    const file = new File([audioBlob.value], `audio-${Date.now()}.${ext}`, { type: audioBlob.value.type });
    emit('send', file);
    audioBlob.value = null;
    audioUrl.value = null;
    chunks = [];
}

function formatDuration(s) {
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
}

onBeforeUnmount(() => {
    clearInterval(timer.value);
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stream.getTracks().forEach(t => t.stop());
    }
    if (audioUrl.value) URL.revokeObjectURL(audioUrl.value);
});
</script>

<template>
    <div class="flex items-center gap-2">
        <!-- Error state -->
        <div v-if="error" class="flex items-center gap-1.5 text-[12px] text-destructive">
            <AlertCircle class="h-3.5 w-3.5 shrink-0" />
            <span>{{ error }}</span>
        </div>

        <!-- Not recording: show mic button -->
        <template v-if="!isRecording && !audioBlob && !error">
            <Button variant="ghost" size="icon" @click="startRecording"
                    class="shrink-0 text-muted-foreground hover:text-foreground">
                <Mic class="h-4 w-4" />
            </Button>
        </template>

        <!-- Recording in progress -->
        <template v-else-if="isRecording">
            <div class="flex items-center gap-2 bg-destructive/10 rounded-full px-3 py-1.5">
                <span class="h-2 w-2 rounded-full bg-destructive animate-pulse" />
                <span class="text-[12px] font-mono text-destructive tabular-nums">
                    {{ formatDuration(duration) }}
                </span>
                <Button variant="ghost" size="icon" @click="cancelRecording"
                        class="h-6 w-6 text-destructive hover:text-destructive">
                    <X class="h-3.5 w-3.5" />
                </Button>
                <Button variant="ghost" size="icon" @click="stopRecording"
                        class="h-6 w-6 text-destructive hover:text-destructive">
                    <Square class="h-3.5 w-3.5 fill-current" />
                </Button>
            </div>
        </template>

        <!-- Recording done: preview + send/cancel -->
        <template v-else-if="audioBlob">
            <div class="flex items-center gap-2 bg-muted rounded-full px-3 py-1.5">
                <audio :src="audioUrl" controls class="h-8 w-48" />
                <Button variant="ghost" size="icon" @click="cancelRecording"
                        class="h-6 w-6 text-muted-foreground hover:text-foreground">
                    <X class="h-3.5 w-3.5" />
                </Button>
                <Button variant="default" size="icon" @click="sendRecording"
                        class="h-7 w-7">
                    <Send class="h-3.5 w-3.5" />
                </Button>
            </div>
        </template>
    </div>
</template>
