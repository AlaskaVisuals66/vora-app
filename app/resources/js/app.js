import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import { createPinia } from 'pinia';
import { ensureAutoLogin } from './Composables/useAuth';
import { initTheme } from './composables/useTheme';

const appName = import.meta.env.VITE_APP_NAME || 'Vora';

ensureAutoLogin().finally(() => {
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
        progress: { color: '#FF5A1F', showSpinner: false },
    });
});
