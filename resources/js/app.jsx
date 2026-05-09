import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route } from 'ziggy-js';
import axios from 'axios';


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Get CSRF token from meta tag
const getCsrfToken = () => {
    const token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.content : '';
};

const csrfToken = getCsrfToken();

// Configure axios to always include CSRF token
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
    axios.defaults.headers.post['X-CSRF-TOKEN'] = csrfToken;
    axios.defaults.headers.put['X-CSRF-TOKEN'] = csrfToken;
    axios.defaults.headers.patch['X-CSRF-TOKEN'] = csrfToken;
    axios.defaults.headers.delete['X-CSRF-TOKEN'] = csrfToken;
}

// Add request interceptor to ensure CSRF token is always included
axios.interceptors.request.use((config) => {
    const token = getCsrfToken();
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }
    return config;
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.jsx`,
            import.meta.glob('./pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
