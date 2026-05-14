import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

const DEV_AUTOLOGIN = { email: 'admin@helpdesk.local', password: 'password' };

export async function ensureAutoLogin() {
    const existing = localStorage.getItem('helpdesk.jwt');
    if (existing) {
        try {
            const { data } = await axios.get('/api/v1/auth/me', {
                headers: { Authorization: `Bearer ${existing}` },
            });
            localStorage.setItem('helpdesk.user', JSON.stringify(data.user || {}));
            return;
        } catch (_) {
            localStorage.removeItem('helpdesk.jwt');
            localStorage.removeItem('helpdesk.user');
        }
    }
    try {
        const { data } = await axios.post('/api/v1/auth/login', DEV_AUTOLOGIN);
        localStorage.setItem('helpdesk.jwt', data.access_token || data.token);
        localStorage.setItem('helpdesk.user', JSON.stringify(data.user || {}));
    } catch (_) {}
}

export function useAuth() {
    const page = usePage();
    const user = computed(() => {
        if (page.props.auth?.user) return page.props.auth.user;
        try { return JSON.parse(localStorage.getItem('helpdesk.user') || 'null'); } catch (_) { return null; }
    });

    const hasRole = (role) => (user.value?.roles || [])
        .some((r) => (typeof r === 'string' ? r : r?.name) === role);
    const isAdmin = computed(() => hasRole('admin'));
    const isSupervisor = computed(() => hasRole('supervisor'));
    const isAttendant = computed(() => hasRole('attendant'));

    async function login(email, password) {
        const { data } = await axios.post('/api/v1/auth/login', { email, password });
        localStorage.setItem('helpdesk.jwt', data.access_token || data.token);
        localStorage.setItem('helpdesk.user', JSON.stringify(data.user || {}));
        window.location.href = '/conversations';
    }

    async function logout() {
        try { await axios.post('/api/v1/auth/logout'); } catch (_) {}
        localStorage.removeItem('helpdesk.jwt');
        localStorage.removeItem('helpdesk.user');
        window.location.href = '/conversations';
    }

    return { user, hasRole, isAdmin, isSupervisor, isAttendant, login, logout };
}
