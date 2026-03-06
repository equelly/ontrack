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
        $waitingStatuses = ['waiting_loading', 'waiting_unloading', 'delayed'];
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
            'waiting_loading' => 'warning',
            'waiting_unloading' => 'warning',
            'delayed' => 'warning',
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
                    <span class="badge bg-white text-dark fs-6" id="status-badge">
                        {{ TruckStatus::label($truck->status) }}
                    </span>
                </div>
                
                <div class="card-body">
                    @if($currentTrip)
                        <!-- Информация о маршруте -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="border rounded p-2">
                                    <small class="text-muted d-block">Откуда</small>
                                    <strong>{{ $currentTrip->miner->name_miner ?? 'Забой #' . $currentTrip->miner_id }}</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-2">
                                    <small class="text-muted d-block">Куда (Дамп)</small>
                                    <strong>{{ $currentTrip->dump->name_dump ?? 'Дамп #' . $currentTrip->dump_id }}</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-2 @if($currentTrip->zone) border-success @endif">
                                    <small class="text-muted d-block">Зона разгрузки</small>
                                    @if($currentTrip->zone)
                                        <strong class="text-success">{{ $currentTrip->zone->name_zone }}</strong>
                                        @if($currentTrip->zone->rocks->isNotEmpty())
                                            <br><small class="text-muted">{{ $currentTrip->zone->rocks->first()->name_rock }}</small>
                                        @endif
                                    @else
                                        <span class="text-warning">Не назначена</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-3">
                                <small class="text-muted">Расстояние</small>
                                <p class="mb-0 fw-bold">{{ $currentTrip->miningOrder->distance_km ?? '-' }} км</p>
                            </div>
                            <div class="col-3">
                                <small class="text-muted">Начало</small>
                                <p class="mb-0 fw-bold">{{ $currentTrip->started_at?->format('H:i') ?? '-' }}</p>
                            </div>
                            <div class="col-3">
                                <small class="text-muted">Время рейса</small>
                                <p class="mb-0 fw-bold" id="trip-duration">-</p>
                            </div>
                            <div class="col-3">
                                <small class="text-muted">Объём</small>
                                <p class="mb-0 fw-bold">{{ $truck->load_capacity ?? '-' }} м³</p>
                            </div>
                        </div>
                        
                        <!-- Информация о зоне -->
                        @if($currentTrip->zone)
                        <div class="alert alert-light mb-4 py-2">
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">Заполнено:</small>
                                    <strong>{{ $currentTrip->zone->volume ?? 0 }} / {{ $currentTrip->zone->capacity }} м³</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Статус зоны:</small>
                                    @if($currentTrip->zone->delivery)
                                        <span class="badge bg-success">Готова к приёму</span>
                                    @else
                                        <span class="badge bg-danger">Закрыта</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif
                    @else
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle"></i> Нет активного маршрута
                        </div>
                    @endif

                    <!-- Управление -->
                    <hr>
                    <div id="status-container">
                        <!-- Динамическое содержимое -->
                    </div>
                    
                    <!-- Дополнительные кнопки -->
                    <div id="extra-buttons" class="mt-3">
                        <!-- Динамические кнопки -->
                    </div>
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
                                <h2 class="text-primary mb-0">{{ $stats['total_trips'] ?? 0 }}</h2>
                                <small class="text-muted">Всего рейсов</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <h2 class="text-success mb-0">{{ $stats['today_trips'] ?? 0 }}</h2>
                                <small class="text-muted">Сегодня</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <h4 class="text-info mb-0">{{ number_format($stats['total_distance'] ?? 0, 1) }}</h4>
                                <small class="text-muted">Всего км</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <h4 class="text-warning mb-0">{{ number_format($stats['total_volume'] ?? 0, 1) }}</h4>
                                <small class="text-muted">Объём м³</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Модальное окно выбора зоны -->
<div class="modal fade" id="zoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🔄 Выберите зону разгрузки</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="available-zones">
                    <!-- Загружается динамически -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно ввода причины задержки -->
<div class="modal fade" id="delayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">⚠️ Укажите причину задержки</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Причина:</label>
                    <select class="form-control" id="delay-reason">
                        <option value="traffic">Пробки</option>
                        <option value="road_works">Дорожные работы</option>
                        <option value="weather">Погодные условия</option>
                        <option value="technical">Техническая проблема</option>
                        <option value="other">Другое</option>
                    </select>
                </div>
                <div class="form-group mt-2">
                    <label>Ожидаемое время задержки (мин):</label>
                    <input type="number" class="form-control" id="delay-minutes" value="15" min="1" max="120">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-warning" onclick="confirmDelay()">Подтвердить</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {

    const truckId = {{ $truck->id }};
    const currentTrip = @json($currentTrip);
    const statusContainer = document.getElementById('status-container');
    const extraButtons = document.getElementById('extra-buttons');
    const workingStatuses = @json($workingStatuses);
    const waitingStatuses = @json($waitingStatuses);

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
        'waiting_loading': 'warning',
        'waiting_unloading': 'warning',
        'delayed': 'warning',
    };

    // =========================================
    // ОБНОВЛЕНИЕ UI
    // =========================================
    function updateUI(status, transition) {
        statusContainer.innerHTML = '';
        extraButtons.innerHTML = '';

        // Основная кнопка перехода
        if (transition) {
            const btn = createButton(transition.label, 'btn-primary btn-lg w-100', 'status-btn');
            btn.addEventListener('click', () => changeStatus(transition.to));
            statusContainer.appendChild(btn);
        }

        // Дополнительные кнопки по статусам
        switch(status) {
            case 'to_miner':
                addExtraButton('⏳ Задержка в пути', 'btn-outline-warning', () => showDelayModal());
                break;

            case 'loading':
                addExtraButton('⏸ Ожидание погрузки', 'btn-outline-info', () => changeStatus('waiting_loading'));
                break;

            case 'transporting':
                addExtraButton('⏳ Задержка в пути', 'btn-outline-warning', () => showDelayModal());
                break;

            case 'unloading':
                addExtraButton('⏸ Ожидание разгрузки', 'btn-outline-info', () => changeStatus('waiting_unloading'));
                addExtraButton('🔄 Сменить зону', 'btn-outline-primary', () => showZoneModal());
                break;

            case 'waiting_loading':
                showInfo('Ожидание погрузки...');
                break;

            case 'waiting_unloading':
                showInfo('Ожидание разгрузки...');
                addExtraButton('🔄 Сменить зону', 'btn-outline-primary', () => showZoneModal());
                break;

            case 'delayed':
                showInfo('Задержка в пути...');
                break;

            case 'free':
                const assignBtn = createButton('🚀 Получить маршрут', 'btn-success btn-lg w-100', 'assign-btn');
                assignBtn.addEventListener('click', assignRoute);
                statusContainer.appendChild(assignBtn);
                break;

            case 'breakdown':
                const freeBtn = createButton('✅ Поломка устранена', 'btn-success btn-lg w-100', 'free-btn');
                freeBtn.addEventListener('click', () => changeStatus('free'));
                statusContainer.appendChild(freeBtn);
                break;
        }

        // Кнопка поломки (для всех кроме breakdown, maintenance, fueling)
        if (!['breakdown', 'maintenance', 'fueling'].includes(status)) {
            addExtraButton('🚨 Поломка', 'btn-outline-danger', () => {
                if (confirm('Сообщить о поломке?')) {
                    changeStatus('breakdown');
                }
            });
        }
    }

    function createButton(text, className, id) {
        const btn = document.createElement('button');
        btn.className = 'btn ' + className;
        btn.innerText = text;
        if (id) btn.id = id;
        return btn;
    }

    function addExtraButton(text, className, onClick) {
        const btn = createButton(text, className + ' btn-sm');
        btn.addEventListener('click', onClick);
        extraButtons.appendChild(btn);
    }

    function showInfo(message) {
        const div = document.createElement('div');
        div.className = 'alert alert-warning text-center mb-0';
        div.innerText = message;
        statusContainer.appendChild(div);
    }

    // =========================================
    // API ЗАПРОСЫ
    // =========================================
    function changeStatus(to, context = {}) {
        fetch('/driver/status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ 
                truck_id: truckId, 
                to: to,
                ...context
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success !== false) {
                location.reload();
            } else {
                alert(data.message || 'Ошибка');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Ошибка соединения');
        });
    }

    function assignRoute() {
        const btn = document.getElementById('assign-btn');
        btn.disabled = true;
        btn.innerText = '⏳ Получение...';

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
            location.reload();
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerText = '🚀 Получить маршрут';
        });
    }

    // =========================================
    // МОДАЛЬНЫЕ ОКНА
    // =========================================
    function showDelayModal() {
        $('#delayModal').modal('show');
    }

    window.confirmDelay = function() {
        const reason = document.getElementById('delay-reason').value;
        const minutes = document.getElementById('delay-minutes').value;
        
        $('#delayModal').modal('hide');
        changeStatus('delayed', { reason: reason, estimated_delay_minutes: minutes });
    };

    function showZoneModal() {
        // Загружаем доступные зоны
        fetch('/driver/available-zones?truck_id=' + truckId)
            .then(res => res.json())
            .then(data => {
                const container = document.getElementById('available-zones');
                
                if (!data.zones || data.zones.length === 0) {
                    container.innerHTML = '<div class="alert alert-warning">Нет доступных зон</div>';
                } else {
                    container.innerHTML = data.zones.map(zone => `
                        <div class="border rounded p-3 mb-2" style="cursor: pointer;" onclick="selectZone(${zone.id})">
                            <strong>${zone.name}</strong>
                            <small class="text-muted d-block">${zone.dump_name || ''}</small>
                            <small class="text-success">Свободно: ${zone.available_capacity} м³</small>
                        </div>
                    `).join('');
                }
                
                $('#zoneModal').modal('show');
            });
    }

    window.selectZone = function(zoneId) {
        $('#zoneModal').modal('hide');
        
        fetch('/driver/reassign-zone', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ 
                truck_id: truckId, 
                zone_id: zoneId 
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Ошибка');
            }
        });
    };

    // =========================================
    // REAL-TIME
    // =========================================
    if (window.Echo) {
        Echo.private('driver.{{ $truck->id }}')
            .listen('App.Events.DriverRouteUpdated', (e) => {
                console.log('🚛 Event:', e);
                
                if (['route_assigned', 'route_reassigned', 'zone_reassigned'].includes(e.action)) {
                    showToast('success', 'Маршрут обновлён!');
                    setTimeout(() => location.reload(), 1000);
                }
                
                if (e.action === 'route_cancelled') {
                    showToast('danger', 'Маршрут отменён!');
                    setTimeout(() => location.reload(), 1000);
                }
            });
    }

    function showToast(type, message) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 70px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    // =========================================
    // ИНИЦИАЛИЗАЦИЯ
    // =========================================
    @php
        $transition = TruckStatus::nextTransition($truck->status);
    @endphp
    updateUI('{{ $truck->status }}', @json($transition));

    // Обновление времени в пути
    @if($currentTrip && $currentTrip->started_at)
    const startedAt = new Date('{{ $currentTrip->started_at->toIso8601String() }}');
    setInterval(() => {
        const now = new Date();
        const diff = Math.floor((now - startedAt) / 1000);
        const min = Math.floor(diff / 60);
        const sec = diff % 60;
        const durationEl = document.getElementById('trip-duration');
        if (durationEl) {
            durationEl.innerText = min + ' мин ' + sec + ' сек';
        }
    }, 1000);
    @endif

});
</script>
@endsection