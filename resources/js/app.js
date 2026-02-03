import './bootstrap'; 




// Слушатель канала (только ПОСЛЕ инициализации window.Echo)
window.Echo.channel('test-channel')
    .listen('.test.event', (e) => {
        console.log('✅ Сигнал получен:', e);
    });
//import './driver-panel';
// window.Echo = new Echo({
//     broadcaster: 'reverb',
//     key: import.meta.env.VITE_REVERB_APP_KEY,
//     wsHost: import.meta.env.VITE_REVERB_HOST,
//     wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
//     wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
//     forceTLS: false,
//     enabledTransports: ['ws', 'wss'],
// });
