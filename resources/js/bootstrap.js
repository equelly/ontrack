import 'bootstrap/dist/css/bootstrap.min.css';
import * as bootstrap from 'bootstrap';

import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;        

// ===================== LARAVEL ECHO + REVERB =====================
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    disableStats: true,
    encrypted: false,
    enabledTransports: ['ws', 'wss'],
});

console.log('✅ Echo + Reverb инициализирован');