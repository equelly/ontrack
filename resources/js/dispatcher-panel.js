import './bootstrap'; // подключение Reverb/Echo

// слушаем канал диспетчера
window.Echo.channel('dispatcher')
    .listen('TruckStatusUpdated', (event) => {
        console.log('🚀 Обновление статуса грузовика:', event);

        // создаем уведомление
        const container = document.getElementById('realtime-notifications');
        const notification = document.createElement('div');
        notification.className = "bg-blue-500 text-white px-6 py-3 rounded-xl shadow-lg animate-pulse mb-2";
        notification.innerText = `🚛 Грузовик ${event.truck_number} теперь ${event.status}`;
        container.appendChild(notification);

        // убираем через 5 секунд
        setTimeout(() => notification.remove(), 5000);

        // уведомляем Livewire
        if (window.Livewire) {
            Livewire.emit('dispatcherTruckUpdated', event);
        }
    });
