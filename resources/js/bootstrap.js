import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Ensure CSRF token is sent with all requests
const token = document.querySelector('meta[name="csrf-token"]');
if (token) {
	window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
	console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

// Import Bootstrap CSS and JS if you're using Bootstrap
// import 'bootstrap/dist/css/bootstrap.min.css';
// import 'bootstrap/dist/js/bootstrap.bundle.min.js';
