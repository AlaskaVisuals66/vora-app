import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import { createPinia } from 'pinia';
import { ensureSession } from './Composables/useAuth';
import { initTheme } from './Composables/useTheme';

const appName = import.meta.env.VITE_APP_NAME || 'Vora';

const PUBLIC_PATHS = ['/login'];

(async () => {
    const path = window.location.pathname;
    const isPublic = PUBLIC_PATHS.some((p) => path === p || path.startsWith(p + '/'));
    const hasSession = await ensureSession();

    if (!hasSession && !isPublic) {
        window.location.replace('/login');
        return;
    }
    if (hasSession && path === '/login') {
        window.location.replace('/conversations');
        return;
    }

    initTheme();
    createInertiaApp({
        title: (title) => (title ? `${title} · ${appName}` : appName),
        resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
        setup({ el, App, props, plugin }) {
            const pinia = createPinia();
            return createApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue)
                .use(pinia)
                .mount(el);
        },
        progress: { color: '#0A0A0A', showSpinner: false },
    });
})();
