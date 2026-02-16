import './bootstrap'; 


// временно — только для dispatcher
import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname === '/dispatcher') {
        if (window.Echo) {
            window.Echo
                .channel('dispatcher-channel')
                .listen('.dispatcher.notification', (e) => {
                    console.log('🚨 Dispatcher notification received', e);

                    const row = document.getElementById(`truck-${e.truck_id}`);
                    
                    if (row) {
                        if (e.status) {
                            row.querySelector('.status').textContent = e.status;
                             // обновляем цвет строки
                        row.style.backgroundColor = statusColor(e.status);
                        }
                        if (e.data?.order_id) {
                            row.querySelector('.current-order').textContent = e.data.order_id;
                        }
                    }
                });
        } else {
            console.error('window.Echo is not defined!');
        }
        // для вывода в удобной форме статусов
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
});

//
if (window.driverId && document.getElementById('ackRoute')) {
    document.getElementById('ackRoute').addEventListener('click', () => {
        fetch('/driver/route/ack', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    .content
            },
            body: JSON.stringify({
                truck_id: window.truckId
            })
        })
        .then(r => r.json())
        .then(() => {
            console.log('✅ Маршрут подтверждён водителем');
        });
    });
}

function statusColor(status) {
    switch(status) {
        case 'free': return '#d4edda';
        case 'to_miner': return '#fff3cd';
        case 'loading': return '#cce5ff';
        case 'transporting': return '#ffe5b4';
        case 'unloading': return '#f8d7da';
        case 'completed': return '#e2e3e5';
        case 'breakdown': return '#f5c6cb';
        case 'maintenance': return '#d6d8d9';
        case 'fueling': return '#d1ecf1';
        default: return '#ffffff';
    }
}
document.querySelectorAll('tbody tr').forEach(row => {
    const statusCell = row.querySelector('.status');
    if(statusCell) {
        row.style.backgroundColor = statusColor(statusCell.textContent.trim());
    }
});

