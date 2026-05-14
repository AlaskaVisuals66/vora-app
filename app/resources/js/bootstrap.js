import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

const token = () => localStorage.getItem('helpdesk.jwt');

window.axios.interceptors.request.use((config) => {
    const t = token();
    if (t) config.headers.Authorization = `Bearer ${t}`;
    return config;
});

window.axios.interceptors.response.use(
    (r) => r,
    async (error) => {
        const original = error.config;
        if (error.response?.status === 401 && error.response?.data?.code === 'token_expired' && !original._retry) {
            original._retry = true;
            try {
                const { data } = await axios.post('/api/v1/auth/refresh', {}, {
                    headers: { Authorization: `Bearer ${localStorage.getItem('helpdesk.jwt')}` },
                });
                const newToken = data.access_token || data.token;
                localStorage.setItem('helpdesk.jwt', newToken);
                original.headers.Authorization = `Bearer ${newToken}`;
                return axios(original);
            } catch (_) {
                localStorage.removeItem('helpdesk.jwt');
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    },
);
