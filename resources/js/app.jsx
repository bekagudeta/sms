import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route } from 'ziggy-js';
import axios from 'axios';


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Get CSRF token from meta tag or props
const getCsrfToken = () => {
    const token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.content : '';
};

const updateAxiosCsrfToken = (token) => {
    if (token) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        axios.defaults.headers.post['X-CSRF-TOKEN'] = token;
        axios.defaults.headers.put['X-CSRF-TOKEN'] = token;
        axios.defaults.headers.patch['X-CSRF-TOKEN'] = token;
        axios.defaults.headers.delete['X-CSRF-TOKEN'] = token;
    }
};

let csrfToken = getCsrfToken();
updateAxiosCsrfToken(csrfToken);

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
        // Update CSRF token from props on each page load
        if (props.initialPage.props && props.initialPage.props.csrf_token) {
            csrfToken = props.initialPage.props.csrf_token;
            updateAxiosCsrfToken(csrfToken);
        }
        
        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
