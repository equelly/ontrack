import 'bootstrap/dist/css/bootstrap.min.css';
import * as bootstrap from 'bootstrap';

// ---------------------------
// Alpine.js - НЕ запускаем вручную!
// Livewire v3 уже включает Alpine и сам его запускает
// Просто делаем Alpine доступным глобально для пользовательских функций
// ---------------------------
import Alpine from 'alpinejs';
window.Alpine = Alpine;
// ---------------------------
// Axios
// ---------------------------
import axios from 'axios';
window.axios = axios;

axios.defaults.withCredentials = true; // куки для CSRF и сессий
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF-токен
const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content');

if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

// ---------------------------
// Laravel Echo + Reverb
// ---------------------------
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: false,
    disableStats: true,
    encrypted: false,
    enabledTransports: ['ws', 'wss'],

    // ---------------------------
    // Обязательный POST и CSRF для Laravel
    // ---------------------------
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': csrfToken,
        },
        withCredentials: true, // чтобы куки отправлялись
    },
});

console.log('✅ Echo + Reverb инициализирован');