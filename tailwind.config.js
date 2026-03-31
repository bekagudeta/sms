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
                'rich-black': '#000000',
                'deep-jungle-green': '#14213D',
                'pearl-aqua': '#E5E5E5',
                'vivid-orange': '#FCA311',
                'yankees-blue': '#14213D',
                'dark-tangerine': '#FCA311',
                platinum: '#E5E5E5',
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
