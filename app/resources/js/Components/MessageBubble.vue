<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Check, CheckCheck, Clock, AlertCircle, Image as ImageIcon, Mic, Video as VideoIcon, FileText, Loader2, Download, X, Play, Pause } from 'lucide-vue-next';
import { useFormat } from '@/Composables/useFormat';
import axios from 'axios';

const props = defineProps({
    message: { type: Object, required: true },
});
const { time } = useFormat();

const mediaUrl = ref(null);
const mediaLoading = ref(false);
const mediaError = ref(false);

const playableTypes = ['image','audio','video','document','sticker'];
const needsFetch = computed(() => playableTypes.includes(props.message.type));

async function fetchMedia() {
    if (!needsFetch.value || mediaUrl.value || mediaLoading.value) return;
    mediaLoading.value = true;
    mediaError.value = false;
    try {
        const { data } = await axios.get(`/api/v1/messages/${props.message.id}/media`, { responseType: 'blob' });
        mediaUrl.value = URL.createObjectURL(data);
    } catch (e) {
        mediaError.value = true;
    } finally {
        mediaLoading.value = false;
    }
}

const rootEl = ref(null);
let observer = null;

watch(() => props.message.id, () => {
    if (mediaUrl.value) { URL.revokeObjectURL(mediaUrl.value); mediaUrl.value = null; }
    mediaError.value = false;
}, { immediate: false });

// Lazy-load: só busca a mídia quando a mensagem chega perto da tela.
// Evita disparar dezenas de requisições ao abrir a conversa (delay + "Too Many Attempts").
onMounted(() => {
    if (!needsFetch.value) return;
    observer = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) {
            fetchMedia();
            observer?.disconnect();
        }
    }, { rootMargin: '250px' });
    if (rootEl.value) observer.observe(rootEl.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    if (mediaUrl.value) URL.revokeObjectURL(mediaUrl.value);
});

const mediaIcon = computed(() => ({
    image:    ImageIcon,
    audio:    Mic,
    video:    VideoIcon,
    document: FileText,
    sticker:  ImageIcon,
}[props.message.type] || FileText));

const isInbound = computed(() => props.message.direction === 'inbound');
const isSystem  = computed(() => props.message.direction === 'system');

const timestampLabel = computed(() => {
    const ts = props.message.sent_at || props.message.delivered_at || props.message.created_at;
    if (!ts) return '';
    const d = new Date(ts);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const day = new Date(d);
    day.setHours(0, 0, 0, 0);
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    if (day.getTime() === today.getTime()) return `${hh}:${mm}`;
    const dd = String(d.getDate()).padStart(2, '0');
    const mo = String(d.getMonth() + 1).padStart(2, '0');
    if (day.getFullYear() === today.getFullYear()) return `${dd}/${mo} ${hh}:${mm}`;
    return `${dd}/${mo}/${String(d.getFullYear()).slice(-2)} ${hh}:${mm}`;
});

const StatusIcon = computed(() => ({
    queued:    Clock,
    sent:      Check,
    delivered: CheckCheck,
    read:      CheckCheck,
    failed:    AlertCircle,
}[props.message.status]));

const isRead = computed(() => props.message.status === 'read');

/* ---------- Lightbox de imagem ---------- */
const lightbox = ref(false);
function downloadMedia() {
    if (!mediaUrl.value) return;
    const a = document.createElement('a');
    a.href = mediaUrl.value;
    a.download = props.message.media?.fileName || props.message.media?.name || `imagem-${props.message.id}.jpg`;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

/* ---------- Player de áudio customizado ---------- */
const audioEl = ref(null);
const audioPlaying = ref(false);
const audioCur = ref(0);
const audioDur = ref(0);

function toggleAudio() {
    const el = audioEl.value;
    if (!el) return;
    if (el.paused) { el.play(); } else { el.pause(); }
}
function onAudioMeta() {
    const d = audioEl.value?.duration;
    audioDur.value = (d && isFinite(d)) ? d : 0;
}
function onAudioTime() {
    audioCur.value = audioEl.value?.currentTime || 0;
    // alguns ogg/opus só revelam a duração ao tocar
    const d = audioEl.value?.duration;
    if ((!audioDur.value || !isFinite(audioDur.value)) && d && isFinite(d)) audioDur.value = d;
}
function seekAudio(e) {
    const el = audioEl.value;
    if (!el || !audioDur.value) return;
    const rect = e.currentTarget.getBoundingClientRect();
    const ratio = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
    el.currentTime = ratio * audioDur.value;
}
const audioProgress = computed(() => (audioDur.value ? (audioCur.value / audioDur.value) * 100 : 0));
function fmtDur(s) {
    if (!s || !isFinite(s)) return '0:00';
    const m = Math.floor(s / 60);
    const sec = Math.floor(s % 60).toString().padStart(2, '0');
    return `${m}:${sec}`;
}
</script>

<template>
    <!-- System/divider -->
    <div v-if="isSystem" class="flex justify-center my-4">
        <span class="text-[11px] px-3 py-1 bg-muted text-muted-foreground rounded-full">{{ message.body }}</span>
    </div>

    <!-- Inbound / Outbound -->
    <div v-else ref="rootEl" :class="['flex mb-2.5', isInbound ? 'justify-start' : 'justify-end']">
        <div :class="[
            'max-w-[480px] px-3.5 py-2.5 text-[13.5px] leading-relaxed shadow-card',
            isInbound
                ? 'bg-card text-foreground border border-border rounded-2xl rounded-bl-md'
                : 'bg-primary text-primary-foreground rounded-2xl rounded-br-md',
        ]">
            <!-- Media -->
            <div v-if="needsFetch" :class="message.body ? 'mb-2' : ''">
                <div v-if="mediaLoading"
                     :class="['flex items-center gap-2 px-2.5 py-3 rounded-xl text-[12px]',
                              isInbound ? 'bg-muted/60 text-muted-foreground' : 'bg-white/15 text-primary-foreground/80']">
                    <Loader2 class="h-3.5 w-3.5 animate-spin" />
                    Carregando {{ message.type }}…
                </div>
                <div v-else-if="mediaError"
                     :class="['flex items-center gap-2 px-2.5 py-2.5 rounded-xl text-[12px]',
                              isInbound ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300' : 'bg-amber-300/30 text-amber-50']">
                    <component :is="mediaIcon" class="h-3.5 w-3.5" />
                    Mídia indisponível
                </div>
                <template v-else-if="mediaUrl">
                    <!-- Imagem / sticker (clicável -> lightbox) -->
                    <img v-if="message.type === 'image' || message.type === 'sticker'"
                         :src="mediaUrl"
                         class="rounded-xl max-w-full max-h-72 object-cover cursor-pointer hover:opacity-90 transition"
                         @click="lightbox = true" />

                    <!-- Áudio: player customizado -->
                    <div v-else-if="message.type === 'audio'"
                         :class="['flex items-center gap-3 rounded-2xl pl-2 pr-3.5 py-2 w-64 max-w-full',
                                  isInbound ? 'bg-muted/50' : 'bg-white/15']">
                        <button @click="toggleAudio"
                                :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-full transition',
                                         isInbound ? 'bg-primary text-primary-foreground hover:opacity-90'
                                                   : 'bg-white text-primary hover:opacity-90']">
                            <Pause v-if="audioPlaying" class="h-4 w-4" />
                            <Play v-else class="h-4 w-4 translate-x-[1px]" />
                        </button>
                        <div class="flex-1 min-w-0">
                            <div @click="seekAudio"
                                 :class="['h-1.5 w-full rounded-full cursor-pointer',
                                          isInbound ? 'bg-foreground/15' : 'bg-white/30']">
                                <div class="h-full rounded-full"
                                     :class="isInbound ? 'bg-primary' : 'bg-white'"
                                     :style="{ width: audioProgress + '%' }"></div>
                            </div>
                            <div :class="['mt-1 flex items-center gap-1 text-[10.5px] tabular-nums',
                                          isInbound ? 'text-muted-foreground' : 'text-primary-foreground/70']">
                                <Mic class="h-3 w-3" />
                                <span>{{ fmtDur(audioCur) }}<span v-if="audioDur"> / {{ fmtDur(audioDur) }}</span></span>
                            </div>
                        </div>
                        <audio ref="audioEl" :src="mediaUrl" preload="metadata" class="hidden"
                               @play="audioPlaying = true" @pause="audioPlaying = false" @ended="audioPlaying = false"
                               @loadedmetadata="onAudioMeta" @timeupdate="onAudioTime" />
                    </div>

                    <!-- Vídeo -->
                    <video v-else-if="message.type === 'video'" :src="mediaUrl"
                           controls preload="metadata" class="rounded-xl max-w-full max-h-72" />

                    <!-- Documento -->
                    <a v-else :href="mediaUrl" target="_blank" download
                       :class="['inline-flex items-center gap-2 px-3 py-2.5 rounded-xl text-[12.5px]',
                                isInbound ? 'bg-muted/60 hover:bg-muted' : 'bg-white/15 hover:bg-white/25']">
                        <FileText class="h-4 w-4 shrink-0" />
                        <span class="truncate">{{ message.media?.fileName || message.media?.name || 'arquivo' }}</span>
                        <Download class="h-3.5 w-3.5 shrink-0 opacity-70" />
                    </a>
                </template>
            </div>

            <!-- Body -->
            <div v-if="message.body" class="whitespace-pre-wrap break-words">{{ message.body }}</div>

            <!-- Meta -->
            <div :class="[
                'text-[10px] mt-1.5 flex items-center gap-1 justify-end font-medium tabular-nums',
                isInbound ? 'text-muted-foreground' : 'text-primary-foreground/70',
            ]">
                <span>{{ timestampLabel }}</span>
                <component v-if="!isInbound && StatusIcon" :is="StatusIcon"
                           :class="['h-3.5 w-3.5', isRead ? 'text-sky-300' : '']" />
            </div>
        </div>
    </div>

    <!-- Lightbox de imagem -->
    <Teleport to="body">
        <div v-if="lightbox && mediaUrl"
             class="fixed inset-0 z-[100] flex items-center justify-center bg-black/85 backdrop-blur-sm"
             @click.self="lightbox = false">
            <div class="absolute top-4 right-4 flex items-center gap-2">
                <button @click="downloadMedia" title="Baixar"
                        class="rounded-full bg-white/15 hover:bg-white/30 p-2.5 text-white transition">
                    <Download class="h-5 w-5" />
                </button>
                <button @click="lightbox = false" title="Fechar"
                        class="rounded-full bg-white/15 hover:bg-white/30 p-2.5 text-white transition">
                    <X class="h-5 w-5" />
                </button>
            </div>
            <img :src="mediaUrl" class="max-h-[90vh] max-w-[92vw] rounded-lg object-contain shadow-2xl" @click.stop />
        </div>
    </Teleport>
</template>
