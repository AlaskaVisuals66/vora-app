<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Check, CheckCheck, Clock, AlertCircle, Image as ImageIcon, Mic, Video as VideoIcon, FileText, Loader2 } from 'lucide-vue-next';
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

watch(() => props.message.id, () => {
    if (mediaUrl.value) { URL.revokeObjectURL(mediaUrl.value); mediaUrl.value = null; }
    mediaError.value = false;
    fetchMedia();
}, { immediate: true });

onBeforeUnmount(() => {
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
</script>

<template>
    <!-- System/divider -->
    <div v-if="isSystem" class="flex justify-center my-4">
        <span class="text-[11px] px-3 py-1 bg-muted text-muted-foreground rounded-full">{{ message.body }}</span>
    </div>

    <!-- Inbound / Outbound -->
    <div v-else :class="['flex mb-2', isInbound ? 'justify-start' : 'justify-end']">
        <div :class="[
            'max-w-[480px] px-3.5 py-2 text-[13.5px] leading-relaxed shadow-card',
            isInbound
                ? 'bg-card text-foreground border border-border rounded-2xl rounded-bl-md'
                : 'bg-primary text-primary-foreground rounded-2xl rounded-br-md',
        ]">
            <!-- Media -->
            <div v-if="needsFetch" class="mb-1.5">
                <div v-if="mediaLoading"
                     :class="['flex items-center gap-2 px-2 py-3 rounded-lg text-[12px]',
                              isInbound ? 'bg-muted/60 text-muted-foreground' : 'bg-white/15 text-primary-foreground/80']">
                    <Loader2 class="h-3.5 w-3.5 animate-spin" />
                    Carregando {{ message.type }}…
                </div>
                <div v-else-if="mediaError"
                     :class="['flex items-center gap-2 px-2 py-2 rounded-lg text-[12px]',
                              isInbound ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300' : 'bg-amber-300/30 text-amber-50']">
                    <component :is="mediaIcon" class="h-3.5 w-3.5" />
                    [Mídia indisponível]
                </div>
                <template v-else-if="mediaUrl">
                    <img v-if="message.type === 'image' || message.type === 'sticker'"
                         :src="mediaUrl"
                         class="rounded-lg max-w-full max-h-64 object-cover" />
                    <audio v-else-if="message.type === 'audio'" :src="mediaUrl"
                           controls preload="metadata" class="w-64 max-w-full" />
                    <video v-else-if="message.type === 'video'" :src="mediaUrl"
                           controls preload="metadata" class="rounded-lg max-w-full max-h-64" />
                    <a v-else :href="mediaUrl" target="_blank" download
                       :class="['inline-flex items-center gap-2 px-2.5 py-2 rounded-lg text-[12.5px] underline',
                                isInbound ? 'bg-muted/60' : 'bg-white/15']">
                        <FileText class="h-3.5 w-3.5" />
                        {{ message.media?.fileName || message.media?.name || 'arquivo' }}
                    </a>
                </template>
            </div>

            <!-- Body -->
            <div v-if="message.body" class="whitespace-pre-wrap">{{ message.body }}</div>

            <!-- Meta -->
            <div :class="[
                'text-[10px] mt-1 flex items-center gap-1 justify-end font-medium tabular-nums',
                isInbound ? 'text-muted-foreground' : 'text-primary-foreground/70',
            ]">
                <span>{{ timestampLabel }}</span>
                <component v-if="!isInbound && StatusIcon" :is="StatusIcon"
                           :class="['h-3 w-3', isRead ? 'text-primary-foreground' : '']" />
            </div>
        </div>
    </div>
</template>
