import './bootstrap'; 
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import 'bootstrap/dist/css/bootstrap.min.css';
import '../css/app.css'; // твои кастомные стили

window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: 'reverb',
//     key: import.meta.env.VITE_REVERB_APP_KEY,
//     wsHost: import.meta.env.VITE_REVERB_HOST,
//     wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
//     wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
//     forceTLS: false,
//     enabledTransports: ['ws', 'wss'],
// });

// Слушатель канала (только ПОСЛЕ инициализации window.Echo)
window.Echo.channel('test-channel')
    .listen('.test.event', (e) => {
        console.log('✅ Сигнал получен:', e);
    });