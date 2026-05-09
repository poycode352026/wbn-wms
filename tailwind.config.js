import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50:  '#fff7ed',
                    100: '#ffedd5',
                    300: '#fdba74',
                    400: '#fb923c',
                    500: '#f97316',
                    600: '#ea580c',
                    700: '#c2410c',
                },
                brand: {
                    blue:       '#1e3a8a',
                    'blue-mid': '#3b82f6',
                    orange:     '#f97316',
                    amber:      '#f59e0b',
                    gold:       '#fbbf24',
                },
                dark: {
                    bg:      '#0d1117',
                    surface: '#111827',
                    card:    '#1e2535',
                    border:  '#2a3550',
                },
            },
            borderRadius: {
                'xl':  '12px',
                '2xl': '16px',
                '3xl': '20px',
            },
            boxShadow: {
                'card':       '0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)',
                'card-hover': '0 8px 24px rgba(0,0,0,0.08)',
                'orange':     '0 4px 14px rgba(249,115,22,0.3)',
                'orange-lg':  '0 8px 22px rgba(249,115,22,0.4)',
                'modal':      '0 24px 64px rgba(0,0,0,0.15)',
            },
            animation: {
                'spin-slow':  'spin 2s linear infinite',
                'pulse-glow': 'pulse 2s ease infinite',
                'fade-in':    'fadeIn 200ms ease',
                'slide-down': 'slideDown 200ms ease',
            },
            keyframes: {
                fadeIn: {
                    from: { opacity: '0' },
                    to:   { opacity: '1' },
                },
                slideDown: {
                    from: { opacity: '0', transform: 'translateY(-8px)' },
                    to:   { opacity: '1', transform: 'translateY(0)' },
                },
            },
            transitionDuration: {
                '250': '250ms',
            },
        },
    },

    plugins: [forms],
};
