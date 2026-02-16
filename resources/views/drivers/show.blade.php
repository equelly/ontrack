@extends('layouts.app')
@section('content')
<div class="container">
    
<h3 class="mt-5">Консоль водителя</h3>

@php
    use App\Domain\TruckStatus;

    $workingStatuses = ['to_miner', 'loading', 'transporting', 'unloading'];
@endphp

<p>Статус: <strong id="truck-status">{{ TruckStatus::label($truck->status) }}</strong></p>

<div id="status-container">
    @php
        $transition = TruckStatus::nextTransition($truck->status);
    @endphp

    @if (in_array($truck->status, $workingStatuses) && $transition)
        <button id="status-btn" class="btn-secondary">{{ $transition['label'] }}</button>
    @elseif($truck->status === 'free')
        <button id="assign-btn">Получить новый маршрут</button>
    @else
        <p id="waiting-msg" style="color:gray;">⏳ Ожидание нового маршрута...</p>
    @endif
</div>

<br>

<button id="breakdown-btn" data-to="breakdown" style="color:red">🚨 Поломка</button>

</div>
<script>
document.addEventListener('DOMContentLoaded', () => {

    const truckId = {{ $truck->id }};
    const statusContainer = document.getElementById('status-container');
    const workingStatuses = @json($workingStatuses);

    // -------------------------------
    // Функция обновления кнопки статуса
    function updateStatusButton(status, transition) {
        statusContainer.innerHTML = '';

        if (workingStatuses.includes(status) && transition) {
            const btn = document.createElement('button');
            btn.id = 'status-btn';
            btn.innerText = transition.label;
            statusContainer.appendChild(btn);
            let inProgress = false;

            btn.addEventListener('click', () => {
                if (inProgress) return;

                inProgress = true;
                btn.disabled = true;
                btn.innerText = '⏳ Отправка...';

                fetch('/driver/status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        truck_id: truckId,
                        to: transition.to
                    })
                })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('truck-status').innerText = data.statusLabel;
                    updateStatusButton(data.status, data.transition);
                })
                .catch(() => {
                    inProgress = false;
                    btn.disabled = false;
                    btn.innerText = transition.label;
                });
            });


        } else if (status === 'free') {
            const assignBtn = document.createElement('button');
            assignBtn.id = 'assign-btn';
            assignBtn.innerText = "Получить новый маршрут";
            statusContainer.appendChild(assignBtn);

            assignBtn.addEventListener('click', () => {
                assignBtn.disabled = true;
                assignBtn.innerText = "⏳ Получение маршрута...";
                fetch('/driver/assign', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ truck_id: truckId })
                })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('truck-status').innerText = data.statusLabel;
                    updateStatusButton(data.status, data.transition);
                })
                .catch(err => {
                    console.error(err);
                    assignBtn.disabled = false;
                    assignBtn.innerText = "Получить новый маршрут";
                });
            });

        } else {
            const msg = document.createElement('p');
            msg.id = 'waiting-msg';
            msg.style.color = 'gray';
            msg.innerText = "⏳ Ожидание нового маршрута...";
            statusContainer.appendChild(msg);
        }
    }

    // -------------------------------
    // Кнопка поломки
    const breakdownBtn = document.getElementById('breakdown-btn');
    breakdownBtn.addEventListener('click', () => {
        fetch('/driver/status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ truck_id: truckId, to: 'breakdown' })
            })
            .then(res => res.json())
            .then(data => {
                breakdownBtn.disabled = true;
                breakdownBtn.innerText = "🚨 Поломка зафиксирована";

                document.getElementById('truck-status').innerText = data.statusLabel;
                updateStatusButton(data.status, data.transition);
            });

    });

    // -------------------------------
    // Live update через Echo/Reverb
    if (window.Echo) {
        Echo.private(`driver.${truckId}`)
            .listen('DriverRouteUpdated', (e) => {
                console.log("Новое событие маршрута:", e);

                if (e.action === 'route_assigned') {
                    updateStatusButton('to_miner', { 
                        to: 'loading', 
                        label: 'Начать загрузку' 
                    });
                    document.getElementById('truck-status').innerText = 'В пути к забою';
                }

                if (e.action === 'route_cancelled') {
                    updateStatusButton('free', null);
                    document.getElementById('truck-status').innerText = 'Готов к работе';
                }
            });
    }
    updateStatusButton(
    '{{ $truck->status }}',
    @json($transition)
);


});
</script>
@endsection