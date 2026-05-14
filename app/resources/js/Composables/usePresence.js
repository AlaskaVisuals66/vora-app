import { onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

const PING_INTERVAL = 30_000;

export function usePresence() {
    let timer = null;

    async function ping() {
        try { await axios.get('/api/v1/presence/ping'); } catch (_) {}
    }

    onMounted(() => {
        ping();
        timer = setInterval(ping, PING_INTERVAL);
    });

    onBeforeUnmount(() => {
        if (timer) clearInterval(timer);
    });
}
