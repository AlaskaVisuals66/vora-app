import { ref } from 'vue';

const STORAGE_KEY = 'vora.theme';
const theme = ref('light');

function apply(value) {
    theme.value = value;
    document.documentElement.classList.toggle('dark', value === 'dark');
    localStorage.setItem(STORAGE_KEY, value);
}

export function initTheme() {
    const saved = localStorage.getItem(STORAGE_KEY);
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    apply(saved || (prefersDark ? 'dark' : 'light'));
}

export function useTheme() {
    function toggle() {
        apply(theme.value === 'dark' ? 'light' : 'dark');
    }
    return { theme, toggle, setTheme: apply };
}
