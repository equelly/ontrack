// Получаем элемент панели водителя и truckId
const driverPanelEl = document.getElementById('driver-panel');
const truckId = driverPanelEl?.dataset.truckId;

// Функция уведомления (появление текста на UI)
function showRealtimeNotification(message) {
    const notif = document.getElementById('realtime-driver-notification');
    if (!notif) return;
    notif.textContent = message;
    notif.classList.add('animate-pulse');

    setTimeout(() => {
        notif.textContent = '';
        notif.classList.remove('animate-pulse');
    }, 4000);
}

// Подписка на канал грузовика
if (truckId && window.Echo) {
    window.Echo.channel(`truck.${truckId}`)
        .listen('TruckStatusUpdated', (e) => {
            console.log('REALTIME:', e);

            // Обновление UI через кастомное событие
            window.dispatchEvent(new CustomEvent('toast', {
                detail: { text: `Новый статус: ${e.status}` }
            }));

            showRealtimeNotification(`Новый статус: ${e.status}`);
        });
}
