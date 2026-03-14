import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    // =========================================
    // ДИСПЕТЧЕР
    // =========================================
    if (window.location.pathname === '/dispatcher') {
        if (window.Echo) {
            
            const channel = window.Echo.channel('dispatcher');
            
            channel.listen('.DispatcherNotification', (e) => {
                showToast(e.status, e);
                setTimeout(() => location.reload(), 2000);
            });
            
        } else {
            console.error('❌ window.Echo is not defined!');
        }

        const STATUS_LABELS = {
            to_miner: 'в пути к забою',
            loading: 'идет загрузка',
            transporting: 'движение к месту выгрузки',
            unloading: 'разгрузка',
            completed: 'рейс завершен',
            free: 'готов к работе',
            maintenance: 'обслуживание',
            fueling: 'заправка',
            breakdown: 'неисправность',
        };

        function humanStatus(status) {
            return STATUS_LABELS[status] ?? status;
        }

        document.querySelectorAll('.status').forEach(cell => {
            const rawStatus = cell.textContent.trim();
            cell.textContent = humanStatus(rawStatus);
        });
    }
    
    // =========================================
    // ПАНЕЛЬ ЭКСКАВАТОРЩИКА
    // =========================================
    if (window.location.pathname.includes('/excavator')) {
        
        const minerId = window.currentMinerId || document.body.dataset.minerId;
        
        console.log('🚜 Экскаватор - minerId:', minerId);
        
        if (window.Echo && minerId) {
            
            // Приватный канал для уведомлений о начале погрузки
            window.Echo.private('miner.' + minerId)
                .listen('.loading.started', (e) => {
                    console.log('🚛 Событие loading.started:', e);
                    
                    showToast('loading', {
                        message: e.message || `Самосвал ${e.truck_number} начал погрузку`
                    });

                    setTimeout(() => location.reload(), 2000);
                });
        }
    }
    
    // =========================================
    // ПАНЕЛЬ ВОДИТЕЛЯ
    // =========================================
    if (window.location.pathname.includes('/driver/')) {
        
        const truckId = window.truckId || document.body.dataset.truckId;
        
        console.log('🚚 Водитель - truckId:', truckId);
        
        if (window.Echo && truckId) {
            
            // Приватный канал для уведомлений о маршруте
            window.Echo.private('driver.' + truckId)
                .listen('App.Events.DriverRouteUpdated', (e) => {
                    
                    if (e.action === 'route_assigned') {
                        showToast('success', 'Вам назначен новый маршрут!');
                    }
                    
                    if (e.action === 'route_cancelled') {
                        showToast('warning', 'Маршрут отменён!');
                    }
                    
                    setTimeout(() => location.reload(), 1000);
                });
            
            // Приватный канал для уведомления о завершении погрузки
            window.Echo.private('truck.' + truckId)
                .listen('.loading.completed', (e) => {
                    console.log('✅ Событие loading.completed:', e);
                    
                    showToast('loading_completed', e);
                    
                    setTimeout(() => location.reload(), 2000);
                });
        }
    }

    // Цвета статусов для таблиц
    function statusColor(status) {
        const colors = {
            'free': '#d4edda',
            'to_miner': '#fff3cd',
            'loading': '#cce5ff',
            'transporting': '#ffe5b4',
            'unloading': '#f8d7da',
            'completed': '#e2e3e5',
            'breakdown': '#f5c6cb',
            'maintenance': '#d6d8d9',
            'fueling': '#d1ecf1',
        };
        return colors[status] || '#ffffff';
    }

    document.querySelectorAll('tbody tr').forEach(row => {
        const statusCell = row.querySelector('.status');
        if(statusCell) {
            row.style.backgroundColor = statusColor(statusCell.textContent.trim());
        }
    });

    // Подтверждение маршрута водителем
    if (window.driverId && document.getElementById('ackRoute')) {
        document.getElementById('ackRoute').addEventListener('click', () => {
            fetch('/driver/route/ack', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    truck_id: window.truckId
                })
            })
            .then(r => r.json())
        });
    }
});

// Toast уведомления
function showToast(status, data) {
    const toast = document.createElement('div');
    toast.className = 'alert alert-info alert-dismissible fade show position-fixed';
    toast.style.cssText = 'top: 70px; right: 20px; z-index: 9999; min-width: 300px;';
    
    let message = '';
    switch(status) {
        case 'route_assigned':
            message = `✅ Маршрут назначен`;
            break;
        case 'breakdown':
            message = `🚨 Поломка`;
            break;
        case 'transporting':
            message = `🚛 В пути`;
            break;
        case 'unloading':
            message = `📦 Разгрузка`;
            break;
        case 'loading':
            message = `⏳ Загрузка`;
            break;
        case 'loading_completed':
            message = data?.message || '✅ Погрузка завершена';
            break;
        case 'completed':
            message = `✅ Рейс завершён`;
            break;
        default:
            message = `📢 ${data?.message || status}`;
    }
    
    toast.innerHTML = `
        <strong>${message}</strong>
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 500);
    }, 4000);
}