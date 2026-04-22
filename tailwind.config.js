import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            colors: {
                // Primary blues - professional and modern
                'primary': '#06749b',
                'primary-dark': '#086e95',
                'primary-light': '#007ea4',
                'primary-accent': '#198eb9',
                'primary-bright': '#0291b2',
                
                // Green accents for success and positive actions
                'success': '#20ae4d',
                'success-light': '#3cac64',
                'success-dark': '#109347',
                
                // Light backgrounds and borders
                'light-bg': '#82d3ee',
                'light-accent': '#47ac74',
                
                // Standard Tailwind colors (keeping for compatibility)
                'gray': {
                    50: '#f9fafb',
                    100: '#f3f4f6',
                    200: '#e5e7eb',
                    300: '#d1d5db',
                    400: '#9ca3af',
                    500: '#6b7280',
                    600: '#4b5563',
                    700: '#374151',
                    800: '#1f2937',
                    900: '#111827',
                },
                'white': '#ffffff',
                'black': '#000000',
            },
            boxShadow: {
                focus: '0 0 0 3px rgba(252, 163, 17, 0.25)',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
