import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import animate from 'tailwindcss-animate';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js,ts}',
    ],
    theme: {
        container: {
            center: true,
            padding: '2rem',
            screens: { '2xl': '1400px' },
        },
        extend: {
            fontFamily: {
                sans: ['Inter', '"Inter var"', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
                display: ['Inter', '"Inter var"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
            },
            colors: {
                border: 'hsl(var(--border))',
                input: 'hsl(var(--input))',
                ring: 'hsl(var(--ring))',
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',
                primary: {
                    DEFAULT: 'hsl(var(--primary))',
                    foreground: 'hsl(var(--primary-foreground))',
                },
                secondary: {
                    DEFAULT: 'hsl(var(--secondary))',
                    foreground: 'hsl(var(--secondary-foreground))',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive))',
                    foreground: 'hsl(var(--destructive-foreground))',
                },
                muted: {
                    DEFAULT: 'hsl(var(--muted))',
                    foreground: 'hsl(var(--muted-foreground))',
                },
                accent: {
                    DEFAULT: 'hsl(var(--accent))',
                    foreground: 'hsl(var(--accent-foreground))',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover))',
                    foreground: 'hsl(var(--popover-foreground))',
                },
                card: {
                    DEFAULT: 'hsl(var(--card))',
                    foreground: 'hsl(var(--card-foreground))',
                },
                // Vora brand tokens (raw access for gradients/brand surfaces)
                vora: {
                    navy:        '#0A0A0A',
                    'navy-deep': '#171717',
                    orange:      '#F43C1A',
                    'orange-light': '#FED7AA',
                    bg:          '#FFFFFF',
                    border:      '#E5E5E5',
                    text:        '#0A0A0A',
                    muted:       '#737373',
                },
                sidebar: {
                    DEFAULT:    '#0A0A0A',
                    foreground: '#A3A3A3',
                    muted:      '#737373',
                    accent:     '#1C1C1C',
                    active:     '#F43C1A',
                    border:     '#262626',
                },
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },
            boxShadow: {
                soft:    '0 1px 2px rgba(0, 0, 0, 0.04), 0 1px 3px rgba(0, 0, 0, 0.06)',
                card:    '0 1px 2px rgba(0, 0, 0, 0.04)',
                pop:     '0 4px 24px -8px rgba(0, 0, 0, 0.12), 0 2px 6px rgba(0, 0, 0, 0.06)',
                ring:    '0 0 0 4px rgba(0, 0, 0, 0.08)',
            },
            keyframes: {
                'accordion-down': { from: { height: 0 }, to: { height: 'var(--radix-accordion-content-height)' } },
                'accordion-up':   { from: { height: 'var(--radix-accordion-content-height)' }, to: { height: 0 } },
                'fade-in':        { from: { opacity: 0 }, to: { opacity: 1 } },
                'slide-up':       { from: { opacity: 0, transform: 'translateY(8px)' }, to: { opacity: 1, transform: 'translateY(0)' } },
                'shimmer':        { '0%': { backgroundPosition: '-200% 0' }, '100%': { backgroundPosition: '200% 0' } },
            },
            animation: {
                'accordion-down': 'accordion-down 0.2s ease-out',
                'accordion-up':   'accordion-up 0.2s ease-out',
                'fade-in':        'fade-in 0.3s ease-out',
                'slide-up':       'slide-up 0.4s cubic-bezier(0.22, 1, 0.36, 1)',
                'shimmer':        'shimmer 2s linear infinite',
            },
        },
    },
    plugins: [forms, typography, animate],
};
