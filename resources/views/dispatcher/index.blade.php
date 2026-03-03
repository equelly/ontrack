@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    
    <!-- Заголовок и фильтры -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h3 class="mb-1"> Панель диспетчера</h3>
            <p class="text-muted mb-0">Управление грузопотоками и мониторинг</p>
        </div>
        <div class="col-md-6 text-md-end">
            <span class="badge bg-primary fs-6" id="realtime-status">
                🟢 Real-time активно
            </span>
        </div>
    </div>

    <!-- Статистика дашборда -->
    <div class="row mb-4">
        <div class="col-md-2 col-6 mb-3">
            <div class="card bg-primary text-white h-60">
                <div class="card-body text-center py-3">
                    <h2 class="mb-0">{{ $dashboardStats['trucks_in_work'] }}</h2>
                    <small>В работе</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card bg-success text-white h-60">
                <div class="card-body text-center py-3">
                    <h2 class="mb-0">{{ $dashboardStats['free_trucks'] }}</h2>
                    <small>Свободны</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card bg-danger text-white h-60">
                <div class="card-body text-center py-3">
                    <h2 class="mb-0">{{ $dashboardStats['breakdown_trucks'] }}</h2>
                    <small>Поломка</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card bg-info text-white h-60">
                <div class="card-body text-center py-3">
                    <h2 class="mb-0">{{ $dashboardStats['active_miners'] }}</h2>
                    <small>Забои</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card bg-warning text-dark h-60">
                <div class="card-body text-center py-3">
                    <h2 class="mb-0">{{ $dashboardStats['active_zones'] }}</h2>
                    <small>Зоны приёма</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card bg-secondary text-white h-60">
                <div class="card-body text-center py-3">
                    <h2 class="mb-0">{{ $dashboardStats['total_trucks'] }}</h2>
                    <small>Всего ТС</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Вкладки -->
    <ul class="nav nav-tabs mb-4" id="dispatcherTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="trucks-tab" data-bs-toggle="tab" data-bs-target="#trucks" type="button">
                Автомобили
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="routes-tab" data-bs-toggle="tab" data-bs-target="#routes" type="button">
                Маршруты
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="zones-tab" data-bs-toggle="tab" data-bs-target="#zones" type="button">
                Зоны
            </button>
        </li>
    </ul>

    <div class="tab-content" id="dispatcherTabsContent">
        
        <!-- Вкладка: Грузовики -->
        <div class="tab-pane fade show active" id="trucks" role="tabpanel">
            <div class="row">
                
                <!-- В работе -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">🚛 В работе ({{ count($trucks['in_work'] ?? []) }})</h5>
                        </div>
                        <div class="card-body p-0">
                            @if(!empty($trucks['in_work']))
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>ТС</th>
                                            <th>Водитель</th>
                                            <th>Статус</th>
                                            <th>Маршрут</th>
                                            <th>Время</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($trucks['in_work'] as $truck)
                                        <tr data-truck-id="{{ $truck['id'] }}">
                                            <td>
                                                <strong>{{ $truck['number'] }}</strong>
                                                @if($truck['brand'])
                                                <br><small class="text-muted">{{ $truck['brand'] }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $truck['driver_name'] ?? '-' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $truck['status'] === 'loading' ? 'warning' : 'primary' }}">
                                                    {{ $truck['status_label'] }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($truck['current_trip'])
                                                    {{ $truck['current_trip']['miner_name'] }} → {{ $truck['current_trip']['dump_name'] }}
                                                    <br><small class="text-muted">{{ $truck['current_trip']['distance'] }} км</small>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if($truck['current_trip'])
                                                    {{ $truck['current_trip']['started_at'] }}
                                                    <br><small class="text-muted">{{ $truck['current_trip']['duration_minutes'] }} мин</small>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="openReassignModal({{ $truck['id'] }})" title="Переназначить">
                                                        🔄
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="setBreakdown({{ $truck['id'] }})" title="Поломка">
                                                        🚨
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <div class="p-4 text-center text-muted">
                                Нет грузовиков в работе
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Свободные и поломки -->
                <div class="col-lg-6 mb-4">
                    <div class="row">
                        <!-- Свободные -->
                        <div class="col-12 mb-3">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">✅ Свободны ({{ count($trucks['free'] ?? []) }})</h5>
                                </div>
                                <div class="card-body p-0">
                                    @if(!empty($trucks['free']))
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>ТС</th>
                                                    <th>Водитель</th>
                                                    <th>Топливо</th>
                                                    <th>Действия</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($trucks['free'] as $truck)
                                                <tr data-truck-id="{{ $truck['id'] }}">
                                                    <td><strong>{{ $truck['number'] }}</strong></td>
                                                    <td>{{ $truck['driver_name'] ?? '-' }}</td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar bg-{{ $truck['fuel_level'] > 30 ? 'success' : 'warning' }}" 
                                                                 style="width: {{ $truck['fuel_level'] }}%">
                                                                {{ $truck['fuel_level'] }}%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-secondary" onclick="setMaintenance({{ $truck['id'] }})" title="Обслуживание">
                                                                🔧
                                                            </button>
                                                            <button class="btn btn-outline-info" onclick="setFueling({{ $truck['id'] }})" title="Заправка">
                                                                ⛽
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @else
                                    <div class="p-3 text-center text-muted">
                                        Нет свободных грузовиков
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Поломки и обслуживание -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0">⚠️ Поломки / Обслуживание ({{ count($trucks['breakdown'] ?? []) + count($trucks['maintenance'] ?? []) + count($trucks['fueling'] ?? []) }})</h5>
                                </div>
                                <div class="card-body p-0">
                                    @php
                                        $inactive = array_merge(
                                            $trucks['breakdown'] ?? [],
                                            $trucks['maintenance'] ?? [],
                                            $trucks['fueling'] ?? []
                                        );
                                    @endphp
                                    @if(!empty($inactive))
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>ТС</th>
                                                    <th>Статус</th>
                                                    <th>Водитель</th>
                                                    <th>Действие</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($inactive as $truck)
                                                <tr data-truck-id="{{ $truck['id'] }}">
                                                    <td><strong>{{ $truck['number'] }}</strong></td>
                                                    <td>
                                                        <span class="badge bg-{{ $truck['status'] === 'breakdown' ? 'danger' : 'secondary' }}">
                                                            {{ $truck['status_label'] }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $truck['driver_name'] ?? '-' }}</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-success" onclick="setFree({{ $truck['id'] }})">
                                                            ✅ Готов к работе
                                                        </button>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @else
                                    <div class="p-3 text-center text-muted">
                                        Нет грузовиков на обслуживании
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Вкладка: Маршруты -->
        <div class="tab-pane fade" id="routes" role="tabpanel">
            
            <!-- Фильтры -->
            <div class="card mb-4">
                <div class="card-body py-3">
                    <div class="row align-items-center">
                        <div class="col-md-4 mb-2 mb-md-0">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="active_zones_filter" 
                                    id="active-zones" value="1" {{ $activeZonesOnly ? 'checked' : '' }} onchange="changeFilter()">
                                <label class="form-check-label" for="active-zones">
                                    Только подготовленные зоны
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="active_zones_filter" 
                                    id="all-zones" value="0" {{ !$activeZonesOnly ? 'checked' : '' }} onchange="changeFilter()">
                                <label class="form-check-label" for="all-zones">
                                    Все перегрузки
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2 mb-md-0">
                            <label class="me-2">Cортировка маршрутов:</label>
                            <select class="form-select d-inline-block w-auto" id="mode-select" onchange="changeFilter()">
                                <option value="balance" {{ $mode === 'balance' ? 'selected' : '' }}>⚖️ Баланс</option>
                                <option value="volume" {{ $mode === 'volume' ? 'selected' : '' }}>📏 По объёму</option>
                                <option value="distance" {{ $mode === 'distance' ? 'selected' : '' }}>🏃 По расстоянию</option>
                            </select>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <span class="badge bg-info">{{ $stats['mode_name'] }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Статистика распределения -->
            <div class="row mb-4">
                <div class="col-md-3 col-6 mb-3">
                    <div class="card">
                        <div class="card-body text-center py-3">
                            <h4 class="text-primary mb-0">{{ $stats['total_routes'] }}</h4>
                            <small class="text-muted">Маршрутов</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card">
                        <div class="card-body text-center py-3">
                            <h4 class="text-info mb-0">{{ $stats['average_distance'] }} км</h4>
                            <small class="text-muted">Ср. расстояние</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card">
                        <div class="card-body text-center py-3">
                            <h4 class="text-success mb-0">{{ $stats['best_score'] }}</h4>
                            <small class="text-muted">Лучший приоритет</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card">
                        <div class="card-body text-center py-3">
                            <h4 class="text-warning mb-0">{{ $stats['avg_score'] }}</h4>
                            <small class="text-muted">Ср. приоритет</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Таблица назначенных маршрутов -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">📋 Назначенные маршруты</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Забой</th>
                                    <th>Перегрузка</th>
                                    <th>Расстояние</th>
                                    <th>Приоритет</th>
                                    <th>Раунд</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $assignment)
                                <tr>
                                    <td><strong>{{ $assignment['miner_name'] }}</strong></td>
                                    <td>{{ $assignment['dump_name'] }}</td>
                                    <td>{{ $assignment['distance'] }} км</td>
                                    <td>
                                        <span class="badge bg-{{ $assignment['score'] > 50 ? 'success' : 'secondary' }}">
                                            {{ round($assignment['score'], 1) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">{{ $assignment['round'] }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Нет назначенных маршрутов
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Вкладка: Зоны -->
        <div class="tab-pane fade" id="zones" role="tabpanel">
            @php
                $map = [
                    'вскрыша' => 'V',
                    'руда' => 'R',
                    'песчаник' => 'Kvp',
                    'руда_S' => 'Rs',
                ];
                $colorMap = [
                    'вскрыша' => 'success',
                    'руда' => 'danger',
                    'песчаник' => 'warning',
                    'руда_S' => 'info',
                ];
            @endphp

            @foreach($stats['zones_by_rock'] as $rockName => $zones)
            @php
                $deliveryCount = collect($zones)->where('delivery', 1)->count();
                $totalInRock = count($zones);
            @endphp
            <div class="card mb-3">
                <div class="card-header bg-{{ $colorMap[$rockName] ?? 'secondary' }} text-white">
                    <h5 class="mb-0">
                        🪨 {{ $rockName }} 
                        <span class="badge bg-white text-dark ms-2">{{ $totalInRock }} зон</span>
                        @if($deliveryCount > 0)
                        <span class="badge bg-light text-success ms-1">{{ $deliveryCount }} подготовлено</span>
                        @else
                        <span class="badge bg-light text-warning ms-1">⚠️ нет подготовленных</span>
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($zones as $zone)
                        <a href="{{ route('dump.edit', ['dump' => $zone->dump_id]) }}" 
                            class="badge bg-{{ $zone->delivery ? 'success' : 'secondary' }} text-decoration-none p-2"
                            style="font-size: 14px;">
                                {{ $map[$zone->name_rock] ?? $zone->name_rock }}{{ $zone->name_zone }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

</div>

<!-- Модальное окно переназначения -->
<div class="modal fade" id="reassignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🔄 Переназначить маршрут</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reassign-truck-id">
                
                <div class="mb-3">
                    <label class="form-label">Выберите маршрут:</label>
                    <select class="form-select" id="reassign-order-select">
                        <option value="">Загрузка...</option>
                    </select>
                </div>
                
                <div id="reassign-info" class="alert alert-info d-none">
                    <!-- Информация о выбранном маршруте -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="confirmReassign()">Переназначить</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// ФУНКЦИИ ГЛОБАЛЬНО

window.changeFilter = function() {
    const activeZonesOnly = document.querySelector('input[name="active_zones_filter"]:checked')?.value || '0';
    const mode = document.getElementById('mode-select')?.value || 'balance';
    
    // Показываем индикатор загрузки
    const routesTab = document.getElementById('routes');
    const originalContent = routesTab.innerHTML;
    routesTab.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="sr-only">Загрузка...</span></div></div>';
    
    // AJAX запрос
    fetch(`/dispatcher?mode=${mode}&active_zones_only=${activeZonesOnly}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(res => {
    console.log('Raw response:', res);
    return res.json();
})
.then(data => {
    
        updateRoutesTab(data);
    })
    .catch(err => {
        console.error('Error:', err);
        routesTab.innerHTML = originalContent;
        alert('Ошибка загрузки данных');
    });
};

function updateRoutesTab(data) {
    // Обновляем статистику распределения
    const statsHtml = `
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h4 class="text-primary mb-0">${data.stats.total_routes}</h4>
                        <small class="text-muted">Маршрутов</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h4 class="text-info mb-0">${data.stats.average_distance} км</h4>
                        <small class="text-muted">Ср. расстояние</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h4 class="text-success mb-0">${data.stats.best_score}</h4>
                        <small class="text-muted">Лучший приоритет</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h4 class="text-warning mb-0">${data.stats.avg_score}</h4>
                        <small class="text-muted">Ср. приоритет</small>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Обновляем таблицу маршрутов
    let tableRows = '';
    if (data.assignments && data.assignments.length > 0) {
        data.assignments.forEach(a => {
            const badgeClass = a.score > 50 ? 'success' : 'secondary';
            tableRows += `
                <tr>
                    <td><strong>${a.miner_name}</strong></td>
                    <td>${a.dump_name}</td>
                    <td>${a.distance} км</td>
                    <td><span class="badge badge-${badgeClass}">${Math.round(a.score * 10) / 10}</span></td>
                    <td><span class="badge badge-secondary">${a.round}</span></td>
                </tr>
            `;
        });
    } else {
        tableRows = '<tr><td colspan="5" class="text-center text-muted py-4">Нет назначенных маршрутов</td></tr>';
    }
    
    const routesContent = `
        <div class="card mb-4">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-4 mb-2 mb-md-0">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="active_zones_filter" 
                                id="active-zones" value="1" ${data.active_zones_only ? 'checked' : ''} onchange="changeFilter()">
                            <label class="form-check-label" for="active-zones">Только подготовленные зоны</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="active_zones_filter" 
                                id="all-zones" value="0" ${!data.active_zones_only ? 'checked' : ''} onchange="changeFilter()">
                            <label class="form-check-label" for="all-zones">Все перегрузки</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="me-2">Режим:</label>
                        <select class="form-control d-inline-block w-auto" id="mode-select" onchange="changeFilter()">
                            <option value="balance" ${data.mode === 'balance' ? 'selected' : ''}>⚖️ Баланс</option>
                            <option value="volume" ${data.mode === 'volume' ? 'selected' : ''}>📏 По объёму</option>
                            <option value="distance" ${data.mode === 'distance' ? 'selected' : ''}>🏃 По расстоянию</option>
                        </select>
                    </div>
                    <div class="col-md-4 text-md-right">
                        <span class="badge badge-info" style="max-width: 100%;">${data.stats.mode_name}</span>
                    </div>
                </div>
            </div>
        </div>
        ${statsHtml}
        <div class="card">
            <div class="card-header"><h5 class="mb-0">📋 Назначенные маршруты</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Забой</th><th>Перегрузка</th><th>Расстояние</th><th>Приоритет</th><th>Раунд</th></tr>
                        </thead>
                        <tbody>${tableRows}</tbody>
                    </table>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('routes').innerHTML = routesContent;
}

window.setBreakdown = function(truckId) {
    if (!confirm('Зафиксировать поломку?')) return;
    fetch(`/dispatcher/truck/${truckId}/breakdown`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message);
    });
};

window.setMaintenance = function(truckId) {
    if (!confirm('Назначить обслуживание?')) return;
    fetch(`/dispatcher/truck/${truckId}/maintenance`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message);
    });
};

window.setFueling = function(truckId) {
    if (!confirm('Назначить заправку?')) return;
    fetch(`/dispatcher/truck/${truckId}/fueling`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message);
    });
};

window.setFree = function(truckId) {
    if (!confirm('Готов к работе?')) return;
    fetch(`/dispatcher/truck/${truckId}/free`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message);
    });
};

window.openReassignModal = function(truckId) {
    document.getElementById('reassign-truck-id').value = truckId;
    fetch('/dispatcher/routes')
        .then(res => res.json())
        .then(data => {
            const select = document.getElementById('reassign-order-select');
            select.innerHTML = '<option value="">Выберите маршрут</option>';
            data.routes.forEach(route => {
                const option = document.createElement('option');
                option.value = route.dump_id;
                option.textContent = `${route.miner_name} → Дамп #${route.dump_id}`;
                select.appendChild(option);
            });
        });
    $('#reassignModal').modal('show');
};

window.confirmReassign = function() {
    const truckId = document.getElementById('reassign-truck-id').value;
    const orderId = document.getElementById('reassign-order-select').value;
    if (!orderId) { alert('Выберите маршрут'); return; }
    
    fetch(`/dispatcher/truck/${truckId}/reassign`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ order_id: orderId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message);
    });
};

    // Real-time
    if (window.Echo) {
        Echo.channel('dispatcher')
            .listen('DispatcherNotification', (e) => {
                console.log('Dispatcher event:', e);
                
                showNotification(e.status, e.data);
                
                // Опционально: обновляем страницу
                // setTimeout(() => location.reload(), 2000);
            });
    }

    function showNotification(status, data) {
        // Показываем toast уведомление
        const toast = document.createElement('div');
        toast.className = 'alert alert-info alert-dismissible fade show position-fixed';
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
        
        let message = '';
        switch(status) {
            case 'route_assigned':
                message = `Маршрут назначен ТС #${data.truck_id}`;
                break;
            case 'breakdown':
                message = `🚨 Поломка ТС #${data.truck_id}`;
                break;
            default:
                message = `Событие: ${status}`;
        }
        
        toast.innerHTML = `
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => toast.remove(), 5000);
    }
</script>
@endsection