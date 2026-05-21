import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

export async function ensureSession() {
    const token = localStorage.getItem('helpdesk.jwt');
    if (!token) return false;
    try {
        const { data } = await axios.get('/api/v1/auth/me', {
            headers: { Authorization: `Bearer ${token}` },
        });
        localStorage.setItem('helpdesk.user', JSON.stringify(data.user || {}));
        return true;
    } catch (_) {
        localStorage.removeItem('helpdesk.jwt');
        localStorage.removeItem('helpdesk.user');
        return false;
    }
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
        const roles = (data.user?.roles || []).map((r) => (typeof r === 'string' ? r : r?.name));
        const dest = roles.includes('admin') ? '/dashboard' : '/conversations';
        window.location.href = dest;
    }

    async function logout() {
        try { await axios.post('/api/v1/auth/logout'); } catch (_) {}
        localStorage.removeItem('helpdesk.jwt');
        localStorage.removeItem('helpdesk.user');
        window.location.href = '/login';
    }

    return { user, hasRole, isAdmin, isSupervisor, isAttendant, login, logout };
}
