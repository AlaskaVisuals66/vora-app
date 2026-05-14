<script setup>
import { computed } from 'vue';
import { Check, CheckCheck, Clock, AlertCircle } from 'lucide-vue-next';
import { useFormat } from '@/Composables/useFormat';

const props = defineProps({
    message: { type: Object, required: true },
});
const { time } = useFormat();

const isInbound = computed(() => props.message.direction === 'inbound');
const isSystem  = computed(() => props.message.direction === 'system');

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
            <div v-if="message.media?.url && message.type !== 'text'" class="mb-1.5">
                <img v-if="message.type === 'image'" :src="message.media.url"
                     class="rounded-lg max-w-full max-h-64 object-cover" />
                <audio v-else-if="message.type === 'audio'" :src="message.media.url"
                       controls class="w-64" />
                <video v-else-if="message.type === 'video'" :src="message.media.url"
                       controls class="rounded-lg max-w-full max-h-64" />
                <a v-else :href="message.media.url" target="_blank"
                   class="underline text-[13px] opacity-90 hover:opacity-100">
                    {{ message.media.name || 'arquivo' }}
                </a>
            </div>

            <!-- Body -->
            <div v-if="message.body" class="whitespace-pre-wrap">{{ message.body }}</div>

            <!-- Meta -->
            <div :class="[
                'text-[10px] mt-1 flex items-center gap-1 justify-end font-medium tabular-nums',
                isInbound ? 'text-muted-foreground' : 'text-primary-foreground/70',
            ]">
                <span>{{ time(message.sent_at || message.created_at) }}</span>
                <component v-if="!isInbound && StatusIcon" :is="StatusIcon"
                           :class="['h-3 w-3', isRead ? 'text-primary-foreground' : '']" />
            </div>
        </div>
    </div>
</template>
