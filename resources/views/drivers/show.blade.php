@extends('layouts.app')
@section('content')
<div class="container py-4">
    
    <!-- Заголовок -->
    <div class="row mb-4">
        <div class="col-12">
            <h3 class="mb-1">🚛 Панель водителя</h3>
            <p class="text-muted mb-0">
                <strong>{{ $truck->driver->name ?? 'Водитель' }}</strong> | 
                <strong>{{ $truck->number ?? '#' . $truck->id }}</strong>
                @if($truck->brand)
                    <span class="badge bg-secondary ms-1">{{ $truck->brand }}</span>
                @endif
                <span class="ms-3">⛽ {{ $truck->fuel_level }}%</span>
            </p>
        </div>
    </div>

    @php
        use App\Domain\TruckStatus;
        $workingStatuses = ['to_miner', 'loading', 'transporting', 'unloading'];
        $transition = TruckStatus::nextTransition($truck->status);
        
        $statusColors = [
            'free' => 'success',
            'to_miner' => 'primary',
            'loading' => 'warning',
            'transporting' => 'info',
            'unloading' => 'secondary',
            'completed' => 'dark',
            'breakdown' => 'danger',
            'maintenance' => 'secondary',
            'fueling' => 'secondary',
        ];
    @endphp

    <div class="row">
        <!-- Текущий маршрут + Управление -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <!-- Заголовок со статусом -->
                <div class="card-header bg-{{ $statusColors[$truck->status] ?? 'secondary' }} text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        📍 Текущий маршрут
                    </h5>
                    <span class="badge bg-white text-dark fs-6">
                        {{ TruckStatus::label($truck->status) }}
                    </span>
                </div>
                
                <div class="card-body">
                    @if($currentTrip)
                        <!-- Информация о маршруте -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-primary me-2">Откуда</span>
                                    <strong>{{ $currentTrip->miner->name ?? 'Забой №' . $currentTrip->miner->name_miner }}</strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-success me-2">Куда</span>
                                    <strong>{{ $currentTrip->dump->name ?? 'Пункт разгрузки №' . $currentTrip->dump->name_dump }}</strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-4">
                                <small class="text-muted">Расстояние</small>
                                <p class="mb-0 fw-bold">
                                    {{ $currentTrip->miningOrder->distance_km ?? '-' }} км
                                </p>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Начало</small>
                                <p class="mb-0 fw-bold">{{ $currentTrip->started_at?->format('H:i') ?? '-' }}</p>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Время рейса</small>
                                <p class="mb-0 fw-bold" id="trip-duration">-</p>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle"></i> Нет активного маршрута
                        </div>
                    @endif

                    <!-- Управление -->
                    <hr>
                    <div id="status-container">
                        @if (in_array($truck->status, $workingStatuses) && $transition)
                            <button id="status-btn" class="btn btn-primary btn-lg w-100">
                                ✅ {{ $transition['label'] }}
                            </button>
                        @elseif($truck->status === 'free')
                            <button id="assign-btn" class="btn btn-success btn-lg w-100">
                                🚀 Получить новый маршрут
                            </button>
                        @elseif($truck->status === 'breakdown')
                            <button id="free-btn" class="btn btn-success btn-lg w-100">
                                🔧 Поломка устранена
                            </button>
                        @else
                            <div class="text-center text-muted py-3">
                                ⏳ Ожидание нового маршрута...
                            </div>
                        @endif
                    </div>
                    
                    <!-- Кнопка поломки -->
                    @if(!in_array($truck->status, ['breakdown', 'maintenance']))
                        <div class="mt-3 text-center">
                            <button id="breakdown-btn" class="btn btn-outline-danger btn-sm">
                                🚨 Сообщить о поломке
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Статистика -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">📊 Статистика</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <h2 class="text-primary mb-0">{{ $stats['total_trips'] }}</h2>
                                <small class="text-muted">Всего рейсов</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <h2 class="text-success mb-0">{{ $stats['today_trips'] }}</h2>
                                <small class="text-muted">Сегодня</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <h4 class="text-info mb-0">{{ number_format($stats['total_distance'], 1) }}</h4>
                                <small class="text-muted">Всего км</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <h4 class="text-warning mb-0">{{ number_format($stats['total_volume'], 1) }}</h4>
                                <small class="text-muted">Объём м³</small>
                            </div>
                        </div>
                    </div>
                    
                    @if($truck->load_capacity)
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Грузоподъёмность:</span>
                            <strong>{{ $truck->load_capacity }} т</strong>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

    const truckId = {{ $truck->id }};
    const statusContainer = document.getElementById('status-container');
    const workingStatuses = @json($workingStatuses);

    const statusColors = {
        'free': 'success',
        'to_miner': 'primary',
        'loading': 'warning',
        'transporting': 'info',
        'unloading': 'secondary',
        'completed': 'dark',
        'breakdown': 'danger',
        'maintenance': 'secondary',
        'fueling': 'secondary',
    };

    function updateStatusButton(status, transition) {
        statusContainer.innerHTML = '';

        if (workingStatuses.includes(status) && transition) {
            const btn = document.createElement('button');
            btn.id = 'status-btn';
            btn.className = 'btn btn-primary btn-lg w-100';
            btn.innerText = '✅ ' + transition.label;
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
                    if (data.status === 'free') {
                        setTimeout(() => location.reload(), 300);
                    } else {
                        location.reload();
                    }
                })
                .catch(() => {
                    inProgress = false;
                    btn.disabled = false;
                    btn.innerText = '✅ ' + transition.label;
                });
            });

        } else if (status === 'free') {
            const assignBtn = document.createElement('button');
            assignBtn.id = 'assign-btn';
            assignBtn.className = 'btn btn-success btn-lg w-100';
            assignBtn.innerText = "🚀 Получить новый маршрут";
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
                    setTimeout(() => location.reload(), 300);
                })
                .catch(err => {
                    console.error(err);
                    assignBtn.disabled = false;
                    assignBtn.innerText = "🚀 Получить новый маршрут";
                });
            });

        } else if (status === 'breakdown') {
            const freeBtn = document.createElement('button');
            freeBtn.id = 'free-btn';
            freeBtn.className = 'btn btn-success btn-lg w-100';
            freeBtn.innerText = "🔧 Поломка устранена";
            statusContainer.appendChild(freeBtn);
            
            freeBtn.addEventListener('click', () => {
                freeBtn.disabled = true;
                freeBtn.innerText = "⏳ Отправка...";
                fetch('/driver/status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ truck_id: truckId, to: 'free' })
                })
                .then(res => res.json())
                .then(data => {
                    setTimeout(() => location.reload(), 300);
                });
            });

        } else {
            const msg = document.createElement('div');
            msg.className = 'text-center text-muted py-3';
            msg.innerText = "⏳ Ожидание нового маршрута...";
            statusContainer.appendChild(msg);
        }
    }

    // Кнопка поломки
    const breakdownBtn = document.getElementById('breakdown-btn');
    if (breakdownBtn) {
        breakdownBtn.addEventListener('click', () => {
            if (!confirm('Вы уверены, что хотите сообщить о поломке?')) return;
            
            breakdownBtn.disabled = true;
            breakdownBtn.innerText = "⏳ Отправка...";
            
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
                location.reload();
            })
            .catch(() => {
                breakdownBtn.disabled = false;
                breakdownBtn.innerText = "🚨 Сообщить о поломке";
            });
        });
    }

    // Live update через Echo/Reverb
    if (window.Echo) {
        Echo.private('driver.{{ $truck->id }}')
            .listen('App.Events.DriverRouteUpdated', (e) => {
                console.log('🚛 Driver event:', e);
                
                if (e.action === 'route_assigned') {
                    showToast('success', 'Вам назначен новый маршрут!');
                    setTimeout(() => location.reload(), 1000);
                }
                
                if (e.action === 'route_cancelled') {
                    showToast('warning', 'Маршрут отменён!');
                    setTimeout(() => location.reload(), 1000);
                }
            });
    }

    // Инициализация
    @php
        $transition = TruckStatus::nextTransition($truck->status);
    @endphp
    updateStatusButton('{{ $truck->status }}', @json($transition));

    // Обновление времени в пути
    @if($currentTrip && $currentTrip->started_at)
    const startedAt = new Date('{{ $currentTrip->started_at->toIso8601String() }}');
    setInterval(() => {
        const now = new Date();
        const diff = Math.floor((now - startedAt) / 1000);
        const min = Math.floor(diff / 60);
        const sec = diff % 60;
        document.getElementById('trip-duration').innerText = min + ' мин ' + sec + ' сек';
    }, 1000);
    @endif

});
</script>
@endsection