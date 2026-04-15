<div class="dispatcher-panel-wrapper">
    <!-- Статистика в строку -->
    <div class="card mb-4">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap align-items-center gap-4">
                <!-- Готовы к назначению -->
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-success bg-opacity-10 rounded px-2 py-1">
                        <i class="fas fa-check-circle text-success"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Доступны для назначения</small>
                        <strong class="text-success fs-5">{{ $this->free_trucks_count }}</strong>
                    </div>
                </div>
                <!-- Разделитель -->
                <div class="vr"></div>
                <!-- В работе -->
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-primary bg-opacity-10 rounded px-2 py-1">
                        <i class="fas fa-truck text-primary"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">В работе</small>
                        <strong class="text-primary fs-5">{{ $this->working_trucks_count }}</strong>
                    </div>
                </div>
                <!-- Разделитель -->
                <div class="vr"></div>
                <!-- Задержки -->
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-warning bg-opacity-10 rounded px-2 py-1">
                        <i class="fas fa-clock text-warning"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Задержки</small>
                        <strong class="text-warning fs-5">{{ $trucks->whereIn('status', ['delayed', 'waiting_unloading'])->count() }}</strong>
                    </div>
                </div>
                <!-- Разделитель -->
                <div class="vr"></div>
                <!-- Поломки -->
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-danger bg-opacity-10 rounded px-2 py-1">
                        <i class="fas fa-wrench text-danger"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Поломки ТС</small>
                        <strong class="text-danger fs-5">{{ $this->breakdown_count }}</strong>
                    </div>
                </div>
                <!-- Разделитель -->
                <div class="vr"></div>
                <!-- Поломки забоев -->
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-danger bg-opacity-10 rounded px-2 py-1">
                        <i class="fas fa-mountain text-danger"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Поломки забоев</small>
                        <strong class="text-danger fs-5">{{ $this->miner_breakdown_count }}</strong>
                    </div>
                </div>
                <!-- Разделитель -->
                <div class="vr"></div>
                <!-- Активные забои -->
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-secondary bg-opacity-10 rounded px-2 py-1">
                        <i class="fas fa-mountain text-success"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Забои в работе</small>
                        <strong class="text-success fs-5">{{ $this->active_miners_count }}</strong>
                    </div>
                </div>
                <!-- Разделитель -->
                <div class="vr"></div>
                <!-- Перегруженные зоны -->
                @php $overloadedZonesCount = $this->overloaded_zones_count; @endphp
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-{{ $overloadedZonesCount > 0 ? 'danger' : 'secondary' }} bg-opacity-10 rounded px-2 py-1">
                        <i class="fas fa-map-marker-alt text-{{ $overloadedZonesCount > 0 ? 'danger' : 'secondary' }}"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Перегрузка зон</small>
                        <strong class="text-{{ $overloadedZonesCount > 0 ? 'danger' : 'secondary' }} fs-5">{{ $overloadedZonesCount }}</strong>
                    </div>
                </div>
                <!-- Разделитель -->
                <div class="vr"></div>
                <!-- Среднее расстояние (расчётное) -->
                @php $distStats = $this->planned_distance_stats; @endphp
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-info bg-opacity-10 rounded px-2 py-1">
                        <i class="fas fa-route text-info"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Ср. расстояние</small>
                        <strong class="text-info fs-5">{{ $distStats['avg_distance'] }}</strong>
                        <small class="text-muted">км</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4" id="mainTabs">
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'trucksTab' ? 'active' : '' }}" 
                    data-bs-toggle="tab" data-bs-target="#trucksTab" type="button"
                    wire:click="setActiveTab('trucksTab')">
                Самосвалы
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'minersTab' ? 'active' : '' }}" 
                    data-bs-toggle="tab" data-bs-target="#minersTab" type="button"
                    wire:click="setActiveTab('minersTab')">
                Забои
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'routesTab' ? 'active' : '' }}" 
                    data-bs-toggle="tab" data-bs-target="#routesTab" type="button"
                    wire:click="setActiveTab('routesTab')">
                Маршруты
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'assignTab' ? 'active' : '' }}" 
                    data-bs-toggle="tab" data-bs-target="#assignTab" type="button"
                    wire:click="setActiveTab('assignTab')">
                Назначить маршрут
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'zonesTab' ? 'active' : '' }}" 
                    data-bs-toggle="tab" data-bs-target="#zonesTab" type="button"
                    wire:click="setActiveTab('zonesTab')">
                Зоны разгрузки
                @php $overloadedCount = $this->overloaded_zones_count; @endphp
                @if($overloadedCount > 0)
                    <span class="badge bg-danger ms-1">{{ $overloadedCount }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'pausesTab' ? 'active' : '' }}" 
                    data-bs-toggle="tab" data-bs-target="#pausesTab" type="button"
                    wire:click="setActiveTab('pausesTab')">
                Простои
                @php
                    $waitingUnloadingCount = $trucks->where('status', 'waiting_unloading')->count();
                    $totalDelays = $this->pause_stats['active_count'] + $this->miner_delays['total_count'] + $waitingUnloadingCount;
                @endphp
                @if($totalDelays > 0)
                    <span class="badge bg-danger ms-1">{{ $totalDelays }}</span>
                @endif
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Самосвалы -->
        <div class="tab-pane fade {{ $activeTab === 'trucksTab' ? 'show active' : '' }}" id="trucksTab">
            <div class="d-flex justify-content-end mb-3">
                <button
                    wire:click="loadData"
                    wire:loading.attr="disabled"
                    class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-sync-alt" wire:loading.class="fa-spin"></i>
                    Обновить
                </button>
            </div>

            <!-- Табличный вид для большого экрана -->
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 100px;">Номер</th>
                            <th style="width: 80px;">Груз.</th>
                            <th style="width: 120px;">Статус</th>
                            <th style="width: 100px;">Порода</th>
                            <th>Маршрут</th>
                            <th style="width: 80px;">Время</th>
                            <th style="width: 150px;">Задержка</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $statusLabels = [
                                'free' => ['label' => 'В отстое', 'color' => 'secondary', 'icon' => 'fa-parking'],
                                'completed' => ['label' => 'Ожидает назначения', 'color' => 'success', 'icon' => 'fa-check-circle'],
                                'to_miner' => ['label' => 'К забою', 'color' => 'info', 'icon' => 'fa-arrow-right'],
                                'loading' => ['label' => 'Погрузка', 'color' => 'warning', 'icon' => 'fa-truck-loading'],
                                'transporting' => ['label' => 'Перевозка', 'color' => 'primary', 'icon' => 'fa-truck'],
                                'unloading' => ['label' => 'Разгрузка', 'color' => 'secondary', 'icon' => 'fa-truck-unload'],
                                'breakdown' => ['label' => 'Поломка', 'color' => 'danger', 'icon' => 'fa-wrench'],
                                'delayed' => ['label' => 'Задержка', 'color' => 'warning', 'icon' => 'fa-clock'],
                                'waiting_loading' => ['label' => 'Ожидание погрузки', 'color' => 'warning', 'icon' => 'fa-hourglass-half'],
                                'waiting_unloading' => ['label' => 'Ожидание назначения для разгрузки', 'color' => 'danger', 'icon' => 'fa-exclamation-triangle'],
                            ];
                        @endphp
                        @foreach($trucks as $truck)
                            @php
                                $status = $statusLabels[$truck->status] ?? ['label' => $truck->status, 'color' => 'secondary', 'icon' => 'fa-question'];
                                $trip = $truck->trips->first();
                                
                                // Определяем породу грузовика
                                $truckRock = null;
                                $truckRockLabel = '';
                                if ($trip) {
                                    // Если грузовик загружен - берём породу из рейса
                                    if (in_array($truck->status, ['transporting', 'unloading', 'waiting_unloading']) && $trip->rock) {
                                        $truckRock = $trip->rock;
                                        $truckRockLabel = 'Загружена';
                                    } elseif ($trip->rock_id) {
                                        // Порода указана в рейсе
                                        $truckRock = $trip->rock;
                                        $truckRockLabel = 'В рейсе';
                                    } elseif ($trip->miningOrder && $trip->miningOrder->rock) {
                                        // Порода из заказа (плановая)
                                        $truckRock = $trip->miningOrder->rock;
                                        $truckRockLabel = 'По маршруту';
                                    } elseif ($trip->miner && $trip->miner->currentRock) {
                                        // Текущая порода в забое
                                        $truckRock = $trip->miner->currentRock;
                                        $truckRockLabel = 'В забое';
                                    }
                                }
                                
                                // Активная пауза
                                $activePause = null;
                                if (in_array($truck->status, ['delayed', 'breakdown']) && $trip) {
                                    $activePause = $trip->pauses->first();
                                }
                            @endphp
                            <tr class="{{ $truck->status === 'breakdown' ? 'table-danger' : ($truck->status === 'delayed' ? 'table-warning' : ($truck->status === 'completed' ? 'table-success' : ($truck->status === 'free' ? 'table-secondary' : ($truck->status === 'waiting_unloading' ? 'table-danger' : '')))) }}">
                                <td>
                                    <span class="fw-bold">{{ $truck->number }}</span>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $truck->load_capacity }} т</small>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $status['color'] }} text-white">
                                        <i class="fas {{ $status['icon'] }} me-1"></i>{{ $status['label'] }}
                                    </span>
                                </td>
                                <td>
                                    @if($truckRock)
                                        <span class="badge bg-info">{{ $truckRock->name_rock }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($trip)
                                        <small>
                                            <i class="fas fa-route text-muted me-1"></i>
                                            {{ $trip->miner?->name_miner ?? '-' }}
                                            <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                            {{ $trip->dump?->name_dump ?? '-' }}
                                            @if($trip->zone)
                                                <span class="badge bg-secondary ms-1">{{ $trip->zone->name_zone }}</span>
                                            @endif
                                        </small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($trip && $trip->started_at)
                                        @php
                                            // Считаем только завершённые паузы (фиксированное время)
                                            $completedPauseSeconds = 0;
                                            $activePauseStart = null;
                                            foreach ($trip->pauses as $pause) {
                                                if ($pause->ended_at) {
                                                    $completedPauseSeconds += $pause->duration_seconds ?? 0;
                                                } else {
                                                    // Активная пауза - запоминаем время начала
                                                    $activePauseStart = $pause->started_at;
                                                }
                                            }
                                        @endphp
                                        <small class="font-monospace trip-timer {{ $activePause ? 'text-warning' : '' }}"
                                               data-started-at="{{ $trip->started_at->toISOString() }}"
                                               data-pause-seconds="{{ $completedPauseSeconds }}"
                                               @if($activePauseStart)
                                               data-active-pause-start="{{ $activePauseStart->toISOString() }}"
                                               @endif
                                               data-truck-id="{{ $truck->id }}">
                                            {{ $trip->getFormattedTripDuration() }}
                                        </small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($activePause)
                                        <span class="badge {{ $truck->status === 'breakdown' ? 'bg-danger' : 'bg-warning text-dark' }}">
                                            {{ \App\Models\TripPause::typeLabel($activePause->type) }}
                                        </span>
                                        <small class="text-muted ms-1">{{ $activePause->getFormattedDuration() }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <button 
                                        class="btn btn-sm btn-outline-secondary" 
                                        wire:click="openForceStatusModal({{ $truck->id }})"
                                        title="Принудительно изменить статус">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Забои -->
        <div class="tab-pane fade {{ $activeTab === 'minersTab' ? 'show active' : '' }}" id="minersTab">
            <!-- Табличный вид для большого экрана -->
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 150px;">Забой</th>
                            <th style="width: 100px;">Порода</th>
                            <th style="width: 100px;">Цель (мин)</th>
                            <th style="width: 100px;">Факт (мин)</th>
                            <th style="width: 100px;">Ожидание</th>
                            <th style="width: 100px;">В работе</th>
                            <th style="width: 120px;">Рекомендация</th>
                            <th style="width: 140px;">Статус</th>
                            <th style="width: 80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($miners as $miner)
                            @php
                                // Считаем грузовики в работе у этого забоя
                                $trucksAtMiner = $trucks->filter(function($truck) use ($miner) {
                                    $trip = $truck->trips->first();
                                    return $trip && $trip->miner_id === $miner->id && 
                                           in_array($truck->status, ['to_miner', 'loading', 'waiting_loading']);
                                });
                                
                                // Грузовики в ожидании назначения для разгрузки (нет зоны для породы)
                                $trucksWaitingUnloading = $trucks->filter(function($truck) use ($miner) {
                                    $trip = $truck->trips->first();
                                    return $trip && $trip->miner_id === $miner->id && 
                                           $truck->status === 'waiting_unloading';
                                });
                                
                                // Рекомендации по производительности
                                $recommendations = $miner->getRecommendedTruckCount();
                                
                                // CSS класс для строки в зависимости от статуса
                                $rowClass = match($miner->status) {
                                    'active' => '',
                                    'breakdown' => 'table-danger',
                                    'maintenance' => 'table-warning',
                                    'dismantling' => 'table-info',
                                    'access_setup' => 'table-secondary',
                                    default => ''
                                };
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td>
                                    <span class="fw-bold">{{ $miner->name_miner }}</span>
                                    @if($miner->status !== 'active' && $miner->status_changed_at)
                                        <br><small class="text-muted">
                                            {{ $miner->status_changed_at->diffForHumans() }}
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    @if($miner->currentRock)
                                        <span class="badge bg-info">{{ $miner->currentRock->name_rock }}</span>
                                    @elseif($miner->rocks->first())
                                        <span class="badge bg-secondary">{{ $miner->rocks->first()->name_rock }}</span>
                                        <small class="text-muted">(истор.)</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($miner->target_load_time)
                                        <span class="badge bg-primary">{{ $miner->target_load_time }} мин</span>
                                    @else
                                        <span class="text-muted small">Не задано</span>
                                    @endif
                                </td>
                                <td>
                                    @php $avgLoadTime = $miner->getAvgLoadTime(5); @endphp
                                    @if($avgLoadTime)
                                        @if($miner->target_load_time)
                                            @php 
                                                $diff = $avgLoadTime - $miner->target_load_time;
                                                $percent = round(($avgLoadTime / $miner->target_load_time) * 100);
                                            @endphp
                                            @if($diff <= 0)
                                                <span class="badge bg-success">{{ $avgLoadTime }} мин</span>
                                            @else
                                                <span class="badge bg-warning text-dark" title="Превышение на {{ $diff }} мин">
                                                    {{ $avgLoadTime }} мин ({{ $percent }}%)
                                                </span>
                                            @endif
                                        @else
                                            <span class="badge bg-secondary">{{ $avgLoadTime }} мин</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @php $avgWaitTime = $miner->getAvgWaitTime(5); @endphp
                                    @if($avgWaitTime && $avgWaitTime > 0)
                                        <span class="badge {{ $avgWaitTime > 3 ? 'bg-warning text-dark' : 'bg-secondary' }}">
                                            {{ $avgWaitTime }} мин
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($trucksAtMiner->count() > 0)
                                        <span class="badge bg-primary">{{ $trucksAtMiner->count() }}</span>
                                        <small class="text-muted ms-1">
                                            {{ $trucksAtMiner->map(fn($t) => $t->number)->join(', ') }}
                                        </small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                    @if($trucksWaitingUnloading->count() > 0)
                                        <br>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            {{ $trucksWaitingUnloading->count() }} ожидает назначения!
                                        </span>
                                        <small class="text-muted ms-1">
                                            {{ $trucksWaitingUnloading->map(fn($t) => $t->number)->join(', ') }}
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    @if($recommendations)
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-bold">{{ $recommendations['current'] }}</span>
                                            <span class="text-muted">/</span>
                                            <span class="text-info">{{ $recommendations['recommended'] }}</span>
                                            @php
                                                $balanceLabels = [
                                                    'underloaded' => ['label' => 'Недогружен', 'class' => 'warning'],
                                                    'balanced' => ['label' => 'ОК', 'class' => 'success'],
                                                    'overloaded' => ['label' => 'Перегруз', 'class' => 'danger'],
                                                ];
                                                $balanceInfo = $balanceLabels[$recommendations['balance']] ?? $balanceLabels['balanced'];
                                            @endphp
                                            <span class="badge bg-{{ $balanceInfo['class'] }}">
                                                {{ $balanceInfo['label'] }}
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            Рекомендуется: {{ $recommendations['recommended'] }} самосвалов
                                        </small>
                                    @else
                                        <span class="text-muted small">Задайте норму</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $miner->getStatusClass() }}">
                                        {{ $miner->getStatusLabel() }}
                                    </span>
                                    @if($miner->isDelayed())
                                        <small class="text-muted d-block">
                                            {{ $miner->getStatusDurationMinutes() }} мин
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    <button 
                                        class="btn btn-sm btn-outline-primary" 
                                        wire:click="openMinerStatusModal({{ $miner->id }})"
                                        wire:loading.attr="disabled"
                                        title="Изменить статус">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Маршруты (управление mining_orders) -->
        <div class="tab-pane fade {{ $activeTab === 'routesTab' ? 'show active' : '' }}" id="routesTab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="mb-0">Маршруты по забоям</h5>
                    <!-- Индикатор и переключатель режима -->
                    <div class="btn-group btn-group-sm" role="group">
                        <button 
                            type="button"
                            class="btn {{ $this->routeMode === 'auto' ? 'btn-primary' : 'btn-outline-primary' }}"
                            wire:click="setRouteMode('auto')"
                            wire:loading.attr="disabled">
                            <i class="fas fa-robot"></i> Авто
                        </button>
                        <button 
                            type="button"
                            class="btn {{ $this->routeMode === 'manual' ? 'btn-warning' : 'btn-outline-warning' }}"
                            wire:click="setRouteMode('manual')"
                            wire:loading.attr="disabled">
                            <i class="fas fa-hand-paper"></i> Ручной
                        </button>
                    </div>
                    <small class="text-muted">
                        @if($this->routeMode === 'auto')
                            <i class="fas fa-info-circle"></i> Система автоматически выбирает маршруты
                        @else
                            <i class="fas fa-exclamation-triangle"></i> Управление маршрутами вручную
                        @endif
                    </small>
                </div>
                <div>
                    @if($this->routeMode === 'auto')
                        <button class="btn btn-primary btn-sm" wire:click="optimizeRoutes" wire:loading.attr="disabled">
                            <span wire:loading.remove><i class="fas fa-magic"></i> Оптимизировать</span>
                            <span wire:loading><i class="fas fa-spinner fa-spin"></i> Оптимизация...</span>
                        </button>
                    @else
                        <button class="btn btn-outline-secondary btn-sm" wire:click="optimizeRoutes" wire:loading.attr="disabled" title="Принудительная оптимизация (ручной режим)">
                            <i class="fas fa-magic"></i> Оптимизировать
                        </button>
                    @endif
                </div>
            </div>

            <div class="alert alert-{{ $this->routeMode === 'auto' ? 'info' : 'warning' }} py-2 small mb-2">
                @if($this->routeMode === 'auto')
                    <i class="fas fa-robot"></i> 
                    <strong>Автоматический режим:</strong> Система сама выбирает лучшие маршруты при оптимизации.
                @else
                    <i class="fas fa-hand-paper"></i> 
                    <strong>Ручной режим:</strong> Диспетчер управляет маршрутами вручную.
                @endif
            </div>
            
            <div class="alert alert-light py-2 small mb-0">
                <i class="fas fa-info-circle"></i>
                <strong>Подсказки:</strong>
                <span class="badge bg-info ms-1">Порода в забое</span> — текущая добываемая порода.
                <span class="badge bg-success ms-1">Зелёная</span> — порода в зоне совместима с породой забоя.
                <span class="badge bg-warning text-dark ms-1">Жёлтая строка</span> — порода забоя не принимается на отвал.
                <span class="badge bg-danger ms-1">Красная строка</span> — все зоны отвала закрыты.
            </div>

            @php
                $ordersGrouped = $this->ordersGroupedByMiner;
            @endphp

            @foreach($ordersGrouped as $minerName => $orders)
                @php
                    $firstOrder = $orders->first();
                    $currentRock = $firstOrder?->current_rock;
                    $minerId = $firstOrder?->miner?->id;
                    $activeCount = $orders->where('active', true)->count();
                @endphp
                <div class="card mb-3">
                    <div class="card-header bg-secondary text-white py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <strong>
                                    <i class="fas fa-mountain me-2"></i>{{ $minerName }}
                                </strong>
                                @if($currentRock)
                                    <span class="badge bg-info">
                                        <i class="fas fa-gem me-1"></i>{{ $currentRock->name_rock }}
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-exclamation-triangle me-1"></i>Нет породы
                                    </span>
                                @endif
                            </div>
                            <div>
                                <span class="badge bg-light text-dark me-2">
                                    {{ $orders->count() }} маршрутов
                                </span>
                                @if($activeCount > 0)
                                    <span class="badge bg-success">
                                        {{ $activeCount }} активен
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Перегрузка</th>
                                    <th style="width: 150px;">Породы в зонах</th>
                                    <th style="width: 80px;">Расст.</th>
                                    <th style="width: 100px;">Вес</th>
                                    <th style="width: 100px;">Доступные зоны</th>
                                    <th style="width: 80px;">Статус</th>
                                    <th style="width: 80px;">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $order)
                                    @php
                                        // Получаем только ОТКРЫТЫЕ зоны этого отвала
                                        $openZones = $order->dump?->zones?->filter(fn($z) => $z->delivery);
                                        // Породы только открытых зон
                                        $dumpRocks = $openZones?->flatMap(fn($z) => $z->rocks)->unique('id');
                                        // Проверяем совместимость с породой забоя
                                        $isCompatible = $currentRock && $dumpRocks?->contains('id', $currentRock->id);
                                    @endphp
                                    <tr class="{{ $order->active ? '' : 'table-secondary' }} {{ !$isCompatible ? 'table-warning' : '' }} {{ $openZones?->isEmpty() ? 'table-danger' : '' }}" style="{{ $order->active ? '' : 'opacity: 0.6' }}">
                                        <td>
                                            <strong>{{ $order->dump?->name_dump ?? '—' }}</strong>
                                            @if($openZones?->isEmpty())
                                                <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Все зоны закрыты</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($dumpRocks && $dumpRocks->count() > 0)
                                                @foreach($dumpRocks->take(3) as $rock)
                                                    <span class="badge {{ $currentRock && $rock->id === $currentRock->id ? 'bg-success' : 'bg-secondary' }} me-1">
                                                        {{ $rock->name_rock }}
                                                    </span>
                                                @endforeach
                                                @if($dumpRocks->count() > 3)
                                                    <small class="text-muted">+{{ $dumpRocks->count() - 3 }}</small>
                                                @endif
                                            @else
                                                <small class="text-muted">Не указаны</small>
                                            @endif
                                        </td>
                                        <td>
                                            <small>{{ $order->distance_km ?? '—' }} км</small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <button 
                                                    class="btn btn-sm btn-outline-secondary py-0 px-1"
                                                    wire:click="adjustWeight({{ $order->id }}, -10)"
                                                    title="Уменьшить вес"
                                                    style="font-size: 10px;">−</button>
                                                <span class="badge {{ $order->active ? 'bg-primary' : 'bg-secondary' }}" style="min-width: 35px;">{{ $order->weight }}</span>
                                                <button 
                                                    class="btn btn-sm btn-outline-secondary py-0 px-1"
                                                    wire:click="adjustWeight({{ $order->id }}, 10)"
                                                    title="Увеличить вес"
                                                    style="font-size: 10px;">+</button>
                                            </div>
                                        </td>
                                        <td>
                                            @if($order->available_zones->count() > 0)
                                                @foreach($order->available_zones->take(2) as $zone)
                                                    <span class="badge bg-info me-1" title="Заполнено: {{ $zone['fill'] }}%">
                                                        {{ $zone['name'] }}
                                                    </span>
                                                @endforeach
                                                @if($order->available_zones->count() > 2)
                                                    <small class="text-muted">+{{ $order->available_zones->count() - 2 }}</small>
                                                @endif
                                            @elseif($openZones->isNotEmpty())
                                                @if(!$isCompatible)
                                                    <small class="text-warning" title="Нет зон для породы: {{ $currentRock?->name_rock }}">
                                                        <i class="fas fa-exclamation-triangle"></i> Порода не принимается
                                                    </small>
                                                @else
                                                    <small class="text-muted" title="Все зоны переполнены">
                                                        <i class="fas fa-database"></i> Зоны заполнены
                                                    </small>
                                                @endif
                                            @else
                                                <small class="text-danger"><i class="fas fa-times-circle"></i> Нет открытых зон</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($order->active && $order->has_zones)
                                                <span class="badge bg-success">✓ Активен</span>
                                            @elseif($order->active)
                                                <span class="badge bg-warning text-dark">⚠️ Нет зон</span>
                                            @else
                                                <span class="badge bg-secondary">Резерв</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                @if($order->active)
                                                    <button 
                                                        class="btn btn-outline-warning"
                                                        wire:click="deactivateOrder({{ $order->id }})"
                                                        title="Деактивировать">
                                                        <i class="fas fa-pause"></i>
                                                    </button>
                                                @else
                                                    <button 
                                                        class="btn btn-outline-success"
                                                        wire:click="activateOrder({{ $order->id }})"
                                                        title="Активировать">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                @endif
                                                <button 
                                                    class="btn btn-outline-primary"
                                                    wire:click="openEditOrder({{ $order->id }})"
                                                    title="Редактировать">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button 
                                                    class="btn btn-outline-danger"
                                                    wire:click="deleteOrder({{ $order->id }})"
                                                    wire:confirm="Удалить маршрут?"
                                                    title="Удалить">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            @if($ordersGrouped->isEmpty())
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Нет настроенных маршрутов. Создайте маршруты для забоев.
                </div>
            @endif
        </div>

        <!-- Назначение маршрута -->
        <div class="tab-pane fade {{ $activeTab === 'assignTab' ? 'show active' : '' }}" id="assignTab">
            <div class="card">
                <div class="card-body">
                    @php
                        $isSelectedTruckLoaded = $this->isSelectedTruckLoaded();
                        $loadedTruckInfo = $this->loaded_truck_info;
                    @endphp

                    @if($isSelectedTruckLoaded && $loadedTruckInfo)
                        <!-- Информация о загруженном грузовике -->
                        <div class="alert alert-warning py-2 mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Грузовик уже загружен</strong> — выберите только зону разгрузки.
                            При необходимости можно изменить породу.
                            <br>
                            <span class="me-3">
                                <i class="fas fa-mountain text-muted me-1"></i>
                                Забой: <strong>{{ $loadedTruckInfo['miner_name'] }}</strong>
                            </span>
                            <span class="me-3">
                                <i class="fas fa-gem text-info me-1"></i>
                                Загрузка: <strong>{{ $loadedTruckInfo['load_volume'] ?? '?' }} т</strong>
                            </span>
                        </div>
                    @endif
                    
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Самосвал</label>
                            <select wire:model.live="selectedTruckId" class="form-select">
                                <option value="">Выберите</option>
                                @foreach($this->free_trucks as $truck)
                                    <option value="{{ $truck->id }}">
                                        {{ $truck->number }} ({{ $truck->load_capacity }}т) — {{ $truck->getStatusLabel() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('selectedTruckId')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                            @if($selectedTruckId && !$isSelectedTruckLoaded)
                                @php
                                    $selectedTruck = $this->free_trucks->firstWhere('id', $selectedTruckId);
                                @endphp
                                @if($selectedTruck && !in_array($selectedTruck->status, ['free', 'completed']))
                                    <small class="text-warning">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Переназначение маршрута (текущий будет отменён)
                                    </small>
                                @endif
                            @endif
                        </div>

                        @if(!$isSelectedTruckLoaded)
                            <!-- Выбор забоя - только для незагруженных грузовиков -->
                            <div class="col-md-3">
                                <label class="form-label">Забой</label>
                                <select wire:model.live="selectedMinerId" class="form-select">
                                    <option value="">Выберите</option>
                                    @foreach($this->active_miners_with_rock as $miner)
                                        <option value="{{ $miner->id }}">
                                            {{ $miner->name_miner }} (@if($miner->currentRock){{ $miner->currentRock->name_rock }}@elseif($miner->rocks->first()){{ $miner->rocks->first()->name_rock }}@else—@endif)
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Маршрут</label>
                                <select wire:model.live="selectedOrderId" class="form-select" @if(!$selectedMinerId) disabled @endif>
                                    <option value="">Выберите забой сначала</option>
                                    @foreach($availableOrders as $order)
                                        <option value="{{ $order['id'] }}">
                                            {{ $order['dump_name'] }} ({{ $order['distance'] }} км)
                                        </option>
                                    @endforeach
                                </select>
                                @error('selectedOrderId')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                                @if($availableOrders && count($availableOrders) === 0 && $selectedMinerId)
                                    <small class="text-warning">Нет маршрутов с доступными зонами</small>
                                @endif
                            </div>
                        @else
                            <!-- Для загруженного грузовика - информационные поля -->
                            <div class="col-md-3">
                                <label class="form-label text-muted">Забой (авто)</label>
                                <input type="text" class="form-control"
                                    value="{{ $loadedTruckInfo['miner_name'] ?? '—' }}"
                                    disabled>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Порода <small class="text-muted">(можно изменить)</small></label>
                                <select wire:model.live="loadedTruckRockId" class="form-select">
                                    <option value="">Выберите породу</option>
                                    @foreach($rocks as $rock)
                                        <option value="{{ $rock->id }}">{{ $rock->name_rock }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="col-md-3">
                            <label class="form-label">Зона разгрузки</label>
                            <select wire:model.live="selectedZoneId" class="form-select" @if(!$isSelectedTruckLoaded && !$selectedOrderId) disabled @endif>
                                <option value="">Автоматически (наименее заполненная)</option>
                                @foreach($availableZones as $zone)
                                    <option value="{{ $zone['id'] }}">
                                        {{ $zone['dump_name'] ?? '' }} - {{ $zone['name'] }} ({{ $zone['fill'] }}%)
                                    </option>
                                @endforeach
                            </select>
                            @if($availableZones && count($availableZones) > 0)
                                <small class="text-muted">Зоны отсортированы по заполнению</small>
                            @elseif($isSelectedTruckLoaded && count($availableZones) === 0)
                                <small class="text-danger">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    Нет доступных зон для данной породы!
                                </small>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3">
                        @if($isSelectedTruckLoaded)
                            <button
                                wire:click="assignRoute"
                                wire:loading.attr="disabled"
                                class="btn btn-warning"
                                @if(!$selectedTruckId) disabled @endif>
                                <span wire:loading.remove><i class="fas fa-check-circle"></i> Назначить зону разгрузки</span>
                                <span wire:loading><i class="fas fa-spinner fa-spin"></i> Назначение...</span>
                            </button>
                        @else
                            <button
                                wire:click="assignRoute"
                                wire:loading.attr="disabled"
                                class="btn btn-primary"
                                @if(!$selectedTruckId || !$selectedOrderId) disabled @endif>
                                <span wire:loading.remove><i class="fas fa-check-circle"></i> Назначить маршрут</span>
                                <span wire:loading><i class="fas fa-spinner fa-spin"></i> Назначение...</span>
                            </button>
                        @endif

                        <button
                            wire:click="assignAllFree"
                            wire:loading.attr="disabled"
                            class="btn btn-outline-primary ms-2">
                            <span wire:loading.remove><i class="fas fa-sync"></i> Назначить всем свободным</span>
                            <span wire:loading><i class="fas fa-spinner fa-spin"></i> Назначение...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Зоны разгрузки -->
        <div class="tab-pane fade {{ $activeTab === 'zonesTab' ? 'show active' : '' }}" id="zonesTab">
            @php
                $zonesLoad = $this->zones_load;
                $overloadedZones = $this->overloaded_zones;
            @endphp

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="mb-0">Мониторинг зон разгрузки</h5>
                    @if(count($overloadedZones) > 0)
                        <span class="badge bg-danger">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            {{ count($overloadedZones) }} перегружено
                        </span>
                    @endif
                </div>
                <div class="d-flex gap-2">
                    @if(count($overloadedZones) > 0)
                        <button class="btn btn-warning btn-sm" wire:click="balanceZones" wire:loading.attr="disabled">
                            <span wire:loading.remove><i class="fas fa-balance-scale"></i> Балансировка</span>
                            <span wire:loading><i class="fas fa-spinner fa-spin"></i> Балансировка...</span>
                        </button>
                    @endif
                    <a href="{{ route('dump.index') }}" class="btn btn-outline-primary btn-sm" target="_blank">
                        <i class="fas fa-cog"></i> Настроить породы
                    </a>
                </div>
            </div>

            <!-- Предупреждение о перегрузке -->
            @if(count($overloadedZones) > 0)
                <div class="alert alert-warning py-2 mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Внимание!</strong> Обнаружены перегруженные зоны. Рекомендуется запустить балансировку для перенаправления грузовиков на недозагруженные маршруты.
                </div>
            @endif

            <!-- Статистика по зонам -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body py-2 text-center">
                            <small class="text-muted d-block">Всего зон</small>
                            <strong class="fs-4">{{ count($zonesLoad) }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success bg-opacity-10">
                        <div class="card-body py-2 text-center">
                            <small class="text-muted d-block">Доступны</small>
                            <strong class="fs-4 text-success">{{ count(array_filter($zonesLoad, fn($z) => $z['status'] === 'available')) }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning bg-opacity-10">
                        <div class="card-body py-2 text-center">
                            <small class="text-muted d-block">Заняты</small>
                            <strong class="fs-4 text-warning">{{ count(array_filter($zonesLoad, fn($z) => $z['status'] === 'busy')) }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger bg-opacity-10">
                        <div class="card-body py-2 text-center">
                            <small class="text-muted d-block">Перегружены</small>
                            <strong class="fs-4 text-danger">{{ count(array_filter($zonesLoad, fn($z) => $z['status'] === 'overloaded')) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Карточки зон -->
            <div class="row">
                @foreach($zones as $zone)
                    @php
                        $fillPercent = $zone->capacity > 0 ? min($zone->volume / $zone->capacity * 100, 100) : 0;
                        $loadStats = $zone->getLoadStats();
                        $isOverloaded = $loadStats['is_overloaded'];
                        $statusColors = [
                            'available' => 'success',
                            'busy' => 'warning',
                            'overloaded' => 'danger',
                            'full' => 'dark',
                        ];
                        $statusLabels = [
                            'available' => 'Доступна',
                            'busy' => 'Занята',
                            'overloaded' => 'ПЕРЕГРУЖЕНА',
                            'full' => 'Заполнена',
                        ];
                        $statusColor = $statusColors[$loadStats['status']] ?? 'secondary';
                        $statusLabel = $statusLabels[$loadStats['status']] ?? $loadStats['status'];
                    @endphp
                    <div class="col-md-4 mb-3">
                        <div class="card zone-card {{ $zone->delivery ? '' : 'closed' }} {{ $isOverloaded ? 'border-danger' : '' }}">
                            <div class="card-header d-flex justify-content-between align-items-center {{ $isOverloaded ? 'bg-danger text-white' : '' }}">
                                <div>
                                    <span class="fw-bold">{{ $zone->dump?->name_dump }} - {{ $zone->name_zone }}</span>
                                    @if($isOverloaded)
                                        <i class="fas fa-exclamation-triangle ms-2"></i>
                                    @endif
                                </div>
                                <div class="form-check form-switch">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        {{ $zone->delivery ? 'checked' : '' }}
                                        wire:click="toggleZone({{ $zone->id }}, {{ $zone->delivery ? 'false' : 'true' }})"
                                        wire:loading.attr="disabled">
                                    <label class="form-check-label small">
                                        {{ $zone->delivery ? 'Открыта' : 'Закрыта' }}
                                    </label>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Статус нагрузки -->
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-{{ $statusColor }}">{{ $statusLabel }}</span>
                                    @if($loadStats['total_trucks'] > 0)
                                        <span class="badge bg-primary">
                                            <i class="fas fa-truck me-1"></i>{{ $loadStats['total_trucks'] }} ТС
                                        </span>
                                    @endif
                                </div>

                                <!-- Статистика грузовиков -->
                                @if($loadStats['total_trucks'] > 0)
                                    <div class="row g-2 mb-2 text-center small">
                                        <div class="col-4">
                                            <div class="bg-light rounded p-1">
                                                <div class="fw-bold text-info">{{ $loadStats['transporting_count'] }}</div>
                                                <div class="text-muted">В пути</div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="bg-light rounded p-1">
                                                <div class="fw-bold text-warning">{{ $loadStats['unloading_count'] }}</div>
                                                <div class="text-muted">Разгрузка</div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="{{ $loadStats['waiting_count'] > 0 ? 'bg-danger bg-opacity-10' : 'bg-light' }} rounded p-1">
                                                <div class="fw-bold {{ $loadStats['waiting_count'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $loadStats['waiting_count'] }}</div>
                                                <div class="text-muted">Ожидание</div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <!-- Породы -->
                                <small class="text-muted d-block mb-2">
                                    <i class="fas fa-gem me-1"></i>
                                    {{ $zone->rocks->pluck('name_rock')->join(', ') ?: 'Не указаны' }}
                                </small>

                                <!-- Заполнение -->
                                <div class="mt-2">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>Заполнение</span>
                                        <span>{{ number_format($zone->volume, 0) }} / {{ number_format($zone->capacity, 0) }}</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div
                                            class="progress-bar {{ $fillPercent > 90 ? 'bg-danger' : ($fillPercent > 70 ? 'bg-warning' : 'bg-success') }}"
                                            style="width: {{ $fillPercent }}%">
                                        </div>
                                    </div>
                                </div>

                                <!-- Кнопка перенаправления для перегруженной зоны -->
                                @if($isOverloaded && $loadStats['waiting_count'] > 0)
                                    <div class="mt-3">
                                        <button
                                            wire:click="redirectFromZone({{ $zone->id }})"
                                            wire:loading.attr="disabled"
                                            class="btn btn-sm btn-warning w-100">
                                            <span wire:loading.remove><i class="fas fa-route me-1"></i> Перенаправить ТС</span>
                                            <span wire:loading><i class="fas fa-spinner fa-spin"></i> Перенаправление...</span>
                                        </button>
                                        <small class="text-muted d-block mt-1 text-center">
                                            Перенаправить ожидающие ТС на другие маршруты
                                        </small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Простои и задержки -->
        <div class="tab-pane fade {{ $activeTab === 'pausesTab' ? 'show active' : '' }}" id="pausesTab">
            @php
                $pauseStats = $this->pause_stats;
                $minerDelays = $this->miner_delays;
            @endphp

            <!-- Фильтры -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <label class="form-label small">Период:</label>
                    <select wire:model.live="pausePeriod" class="form-select form-select-sm">
                        <option value="shift">За смену</option>
                        <option value="today">За сегодня</option>
                        <option value="week">За неделю</option>
                        <option value="month">За месяц</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small"><i class="fas fa-truck me-1"></i>Тип простоев ТС:</label>
                    <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y: auto;">
                        @foreach(\App\Models\TripPause::types() as $typeKey => $typeLabel)
                            <div class="form-check form-check-sm mb-1">
                                <input
                                    type="checkbox"
                                    id="pauseType_{{ $typeKey }}"
                                    value="{{ $typeKey }}"
                                    wire:model.live="pauseTypes"
                                    class="form-check-input">
                                <label for="pauseType_{{ $typeKey }}" class="form-check-label small">
                                    {{ $typeLabel }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted">Выберите типы для фильтрации</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label small"><i class="fas fa-mountain me-1"></i>Тип простоев забоев:</label>
                    <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y: auto;">
                        @foreach(\App\Models\MinerPause::types() as $typeKey => $typeLabel)
                            <div class="form-check form-check-sm mb-1">
                                <input
                                    type="checkbox"
                                    id="minerPauseType_{{ $typeKey }}"
                                    value="{{ $typeKey }}"
                                    wire:model.live="minerPauseTypes"
                                    class="form-check-input">
                                <label for="minerPauseType_{{ $typeKey }}" class="form-check-label small">
                                    {{ $typeLabel }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted">Выберите типы для фильтрации</small>
                </div>
                <div class="col-md-4 d-flex align-items-end justify-content-end">
                    <div class="text-muted small">
                        {{ $pauseStats['period_label'] }}: 
                        <strong class="text-dark">{{ $pauseStats['total_count'] }}</strong> инцидентов ТС,
                        <strong class="text-{{ $pauseStats['total_seconds'] > 0 ? 'danger' : 'muted' }}">
                            {{ $pauseStats['total_formatted'] }}
                        </strong> простоя ТС
                        <br>
                        <strong class="text-dark">{{ $minerDelays['total_count'] }}</strong> задержек забоев,
                        <strong class="text-{{ $minerDelays['total_minutes'] > 0 ? 'danger' : 'muted' }}">
                            {{ $minerDelays['total_formatted'] }}
                        </strong> простоя забоев
                        @php
                            $waitingUnloadingCount = $trucks->where('status', 'waiting_unloading')->count();
                        @endphp
                        @if($waitingUnloadingCount > 0)
                            <br>
                            <strong class="text-danger">{{ $waitingUnloadingCount }}</strong> ожидают назначения!
                        @endif
                    </div>
                </div>
            </div>

            <!-- Сводка по типам простоев ТС -->
            @if($pauseStats['by_type']->count() > 0)
            <div class="row mb-3">
                <div class="col-12">
                    <h6 class="text-muted mb-2"><i class="fas fa-truck me-1"></i>Простои самосвалов</h6>
                </div>
                @foreach($pauseStats['by_type'] as $typeData)
                    <div class="col-md-3 col-lg-2 mb-2">
                        <div class="card {{ $typeData['type'] === 'breakdown' ? 'border-danger' : 'border-warning' }}">
                            <div class="card-body py-2 px-3">
                                <small class="text-muted d-block">{{ $typeData['label'] }}</small>
                                <strong class="text-danger">{{ $typeData['total_formatted'] }}</strong>
                                <small class="text-muted">({{ $typeData['count'] }})</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @endif

            <!-- Сводка по простоям забоев -->
            @if($minerDelays['total_count'] > 0)
            <div class="row mb-4">
                <div class="col-12">
                    <h6 class="text-muted mb-2"><i class="fas fa-mountain me-1"></i>Простои забоев</h6>
                </div>
                @foreach($minerDelays['by_status'] as $statusData)
                    <div class="col-md-3 col-lg-2 mb-2">
                        <div class="card {{ $statusData['status'] === 'breakdown' ? 'border-danger' : 'border-warning' }}">
                            <div class="card-body py-2 px-3">
                                <small class="text-muted d-block">{{ $statusData['label'] }}</small>
                                <strong class="text-danger">{{ $statusData['total_formatted'] }}</strong>
                                <small class="text-muted">({{ $statusData['count'] }})</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @endif

            <!-- Грузовики, ожидающие назначения зоны -->
            @php
                $trucksWaitingUnloading = $trucks->where('status', 'waiting_unloading');
            @endphp
            @if($trucksWaitingUnloading->count() > 0)
            <div class="row mb-4">
                <div class="col-12">
                    <h6 class="text-danger mb-2">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Ожидают назначения зоны разгрузки
                    </h6>
                </div>
                @foreach($trucksWaitingUnloading as $truck)
                    @php
                        $trip = $truck->trips->first();
                    @endphp
                    <div class="col-md-3 col-lg-2 mb-2">
                        <div class="card border-danger">
                            <div class="card-body py-2 px-3">
                                <strong class="text-dark">{{ $truck->number }}</strong>
                                <small class="text-muted d-block">
                                    {{ $trip?->rock?->name_rock ?? '—' }}
                                    <br>
                                    из {{ $trip?->miner?->name_miner ?? '—' }}
                                </small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @endif

            <div class="row">
                <!-- Таблица простоев ТС -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <strong>
                                <i class="fas fa-truck me-1"></i>
                                Самосвалы
                                @if($pauseStats['active_count'] > 0)
                                    <span class="badge bg-danger ms-1">{{ $pauseStats['active_count'] }}</span>
                                @endif
                            </strong>
                            @if(!empty($pauseTypes))
                                <small class="text-muted">
                                    @foreach($pauseTypes as $pt)
                                        <span class="badge bg-warning text-dark me-1">{{ \App\Models\TripPause::typeLabel($pt) }}</span>
                                    @endforeach
                                </small>
                            @endif
                        </div>
                        <div class="card-body p-0">
                            @if($pauseStats['pauses']->count() > 0)
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Время</th>
                                            <th>ТС</th>
                                            <th>Тип</th>
                                            <th>Длит.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pauseStats['pauses']->take(20) as $pause)
                                            <tr class="{{ $pause->ended_at ? '' : 'table-warning' }}">
                                                <td><small>{{ $pause->started_at->format('H:i') }}</small></td>
                                                <td><strong>{{ $pause->truck?->number ?? '—' }}</strong></td>
                                                <td>
                                                    <span class="badge {{ $pause->type === 'breakdown' ? 'bg-danger' : 'bg-warning text-dark' }}">
                                                        {{ \App\Models\TripPause::typeLabel($pause->type) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($pause->ended_at)
                                                        <span class="text-muted">{{ $pause->getFormattedDuration() }}</span>
                                                    @else
                                                        <span class="text-danger fw-bold">
                                                            {{ $pause->getFormattedDuration() }}
                                                            <i class="fas fa-spinner fa-spin ms-1"></i>
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <div class="p-4 text-center">
                                <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                <h6 class="text-muted mb-1">Нет простоев ТС</h6>
                                <p class="text-muted small mb-0">{{ $pauseStats['period_label'] }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Таблица простоев забоев -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <strong>
                                <i class="fas fa-mountain me-1"></i>
                                Забои
                                @if($minerDelays['total_count'] > 0)
                                    <span class="badge bg-danger ms-1">{{ $minerDelays['total_count'] }}</span>
                                @endif
                            </strong>
                            <div class="d-flex align-items-center gap-2">
                                @if(!empty($minerPauseTypes))
                                    @foreach($minerPauseTypes as $mpType)
                                        <span class="badge bg-warning text-dark">
                                            {{ \App\Models\MinerPause::typeLabel($mpType) }}
                                        </span>
                                    @endforeach
                                @endif
                                @if($minerDelays['total_count'] > 0)
                                    <small class="text-muted">
                                        Всего: {{ $minerDelays['total_formatted'] }}
                                    </small>
                                @endif
                            </div>
                        </div>
                        <div class="card-body p-0">
                            @if($minerDelays['total_count'] > 0)
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Забой</th>
                                            <th>Тип</th>
                                            <th>Начало</th>
                                            <th>Длит.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($minerDelays['pauses']->take(20) as $pause)
                                            <tr class="{{ $pause->type === 'breakdown' ? 'table-danger' : ($pause->ended_at ? '' : 'table-warning') }}">
                                                <td>
                                                    <strong>{{ $pause->miner?->name_miner ?? '—' }}</strong>
                                                </td>
                                                <td>
                                                    <span class="badge {{ $pause->type === 'breakdown' ? 'bg-danger' : 'bg-warning text-dark' }}">
                                                        {{ $pause->getTypeLabel() }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <small>{{ $pause->started_at->format('d.m H:i') }}</small>
                                                </td>
                                                <td>
                                                    @if($pause->ended_at)
                                                        <span class="text-muted">{{ $pause->getFormattedDuration() }}</span>
                                                    @else
                                                        <span class="text-danger fw-bold">
                                                            {{ $pause->getFormattedDuration() }}
                                                            <i class="fas fa-spinner fa-spin ms-1"></i>
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <div class="p-4 text-center">
                                <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                <h6 class="text-muted mb-1">Все забои в работе</h6>
                                <p class="text-muted small mb-0">Нет задержек за выбранный период</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно принудительной смены статуса -->
    @if($forceStatusTruckId)
        @php
            $truckToEdit = \App\Models\Truck::find($forceStatusTruckId);
            $statusLabels = [
                'free' => ['label' => 'Свободен', 'color' => 'success'],
                'to_miner' => ['label' => 'К забою', 'color' => 'info'],
                'loading' => ['label' => 'Погрузка', 'color' => 'warning'],
                'transporting' => ['label' => 'Перевозка', 'color' => 'primary'],
                'unloading' => ['label' => 'Разгрузка', 'color' => 'secondary'],
                'breakdown' => ['label' => 'Поломка', 'color' => 'danger'],
                'delayed' => ['label' => 'Задержка', 'color' => 'warning'],
            ];
            $currentStatus = $truckToEdit ? ($statusLabels[$truckToEdit->status] ?? ['label' => $truckToEdit->status, 'color' => 'secondary']) : null;
            
            // Предыдущий статус (до поломки/задержки)
            $previousStatusKey = $truckToEdit?->before_breakdown;
            $previousStatus = $previousStatusKey ? ($statusLabels[$previousStatusKey] ?? null) : null;
        @endphp
        <div class="modal fade show d-block" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Принудительное изменение статуса
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeForceStatusModal"></button>
                    </div>
                    <div class="modal-body">
                        @if($truckToEdit)
                            <p class="mb-3">
                                Самосвал: <strong>{{ $truckToEdit->number }}</strong><br>
                                Текущий статус: 
                                @if($currentStatus)
                                    <span class="badge bg-{{ $currentStatus['color'] }}">{{ $currentStatus['label'] }}</span>
                                @endif
                                @if($previousStatus)
                                    <br><small class="text-muted">(до этого: {{ $previousStatus['label'] }})</small>
                                @endif
                            </p>
                            
                            <label class="form-label">Изменить статус:</label>
                            <select wire:model.live="forceStatusNew" class="form-select">
                                @if($previousStatus)
                                    <option value="{{ $previousStatusKey }}">-- Вернуться к "{{ $previousStatus['label'] }}" --</option>
                                @endif
                                <option value="">-- Оставить "{{ $currentStatus['label'] }}" --</option>
                                @foreach($this->available_statuses as $statusKey => $statusLabel)
                                    @if($truckToEdit->status !== $statusKey && $statusKey !== $previousStatusKey)
                                        <option value="{{ $statusKey }}">{{ $statusLabel }}</option>
                                    @endif
                                @endforeach
                            </select>
                            
                            @if($forceStatusNew)
                                <div class="alert alert-warning mt-3 mb-0">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <strong>Внимание!</strong> Это действие будет записано в лог.
                                    @if($forceStatusNew === 'free')
                                        <br>Текущий рейс будет завершён.
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeForceStatusModal">
                            Отмена
                        </button>
                        <button 
                            type="button" 
                            class="btn btn-warning"
                            wire:click="forceChangeStatus"
                            wire:loading.attr="disabled"
                            @if(!$forceStatusNew) disabled @endif>
                            <span wire:loading.remove><i class="fas fa-check"></i> Изменить статус</span>
                            <span wire:loading><i class="fas fa-spinner fa-spin"></i> Изменение...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Модальное окно редактирования маршрута -->
    @if($editOrderId)
        @php
            $orderToEdit = \App\Models\MiningOrder::with(['miner.rocks', 'dump'])->find($editOrderId);
            $currentDistance = $editDistances[$editDumpId] ?? $orderToEdit?->distance_km;
        @endphp
        <div class="modal fade show d-block" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-route me-2"></i>
                            Редактирование маршрута #{{ $editOrderId }}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="closeEditOrder"></button>
                    </div>
                    <div class="modal-body">
                        @if($orderToEdit)
                            <div class="mb-3">
                                <label class="form-label">Забой</label>
                                <input type="text" class="form-control" value="{{ $orderToEdit->miner?->name_miner }}" disabled>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Перегрузка</label>
                                <select wire:model.live="editDumpId" class="form-select">
                                    @foreach($dumps as $dump)
                                        @php
                                            $dumpDistance = $editDistances[$dump->id] ?? null;
                                        @endphp
                                        <option value="{{ $dump->id }}">
                                            {{ $dump->name_dump }}
                                            @if($dumpDistance)
                                                ({{ $dumpDistance }} км)
                                            @else
                                                (расст. не указано)
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">В скобках указано расстояние от забоя до перегрузки</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Расстояние</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="{{ $currentDistance ?? '—' }}" disabled>
                                    <span class="input-group-text">км</span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Статус</label>
                                <div class="form-check form-switch">
                                    <input 
                                        type="checkbox" 
                                        class="form-check-input" 
                                        id="editActive"
                                        wire:model.live="editActive"
                                        {{ $editActive ? 'checked' : '' }}>
                                    <label class="form-check-label" for="editActive">
                                        {{ $editActive ? 'Активен' : 'Неактивен' }}
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeEditOrder">
                            Отмена
                        </button>
                        <button 
                            type="button" 
                            class="btn btn-primary"
                            wire:click="saveOrder"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove><i class="fas fa-save"></i> Сохранить</span>
                            <span wire:loading><i class="fas fa-spinner fa-spin"></i> Сохранение...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Модальное окно смены статуса забоя -->
    @if($editMinerStatusId)
        @php
            $currentMiner = \App\Models\Miner::find($editMinerStatusId);
        @endphp
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered {{ $showMinerWarning ? 'modal-lg' : '' }}">
                <div class="modal-content">
                    <div class="modal-header bg-{{ $showMinerWarning ? 'danger' : '' }}">
                        <h5 class="modal-title">
                            <i class="fas fa-mountain me-2"></i>
                            Статус забоя: {{ $currentMiner?->name_miner }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeMinerStatusModal"></button>
                    </div>
                    <div class="modal-body">
                        @if($showMinerWarning)
                            <!-- Предупреждение о перегрузке -->
                            <div class="alert alert-danger mb-3">
                                <h5 class="alert-heading">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Внимание! Возможна перегрузка!
                                </h5>
                                <p class="mb-0">{{ $minerStatusWarning }}</p>
                            </div>

                            @if($minerSafetyCheck)
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h6 class="text-muted">Грузовиков на забое</h6>
                                                <h3 class="mb-0">{{ $minerSafetyCheck['trucks_to_redirect'] ?? 0 }}</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h6 class="text-muted">Можно принять</h6>
                                                <h3 class="mb-0 {{ ($minerSafetyCheck['total_capacity'] ?? 0) < ($minerSafetyCheck['trucks_to_redirect'] ?? 0) ? 'text-danger' : 'text-success' }}">
                                                    {{ $minerSafetyCheck['total_capacity'] ?? 0 }}
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if(!empty($minerSafetyCheck['alternatives']))
                                    <h6 class="fw-bold">Альтернативные забои:</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Забой</th>
                                                    <th>Порода</th>
                                                    <th class="text-center">Оптим.</th>
                                                    <th class="text-center">Текущ.</th>
                                                    <th class="text-center">Вместимость</th>
                                                    <th class="text-center">Статус</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($minerSafetyCheck['alternatives'] as $alt)
                                                    <tr>
                                                        <td>{{ $alt['name'] }}</td>
                                                        <td>{{ $alt['rock'] ?? '—' }}</td>
                                                        <td class="text-center">{{ $alt['recommended'] }}</td>
                                                        <td class="text-center">{{ $alt['current'] }}</td>
                                                        <td class="text-center">
                                                            @if($alt['capacity'] > 0)
                                                                <span class="badge bg-success">+{{ $alt['capacity'] }}</span>
                                                            @elseif($alt['capacity'] == 0)
                                                                <span class="badge bg-secondary">0</span>
                                                            @else
                                                                <span class="badge bg-danger">{{ $alt['capacity'] }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">
                                                            @if($alt['balance'] === 'balanced')
                                                                <span class="badge bg-success">Норма</span>
                                                            @elseif($alt['balance'] === 'underloaded')
                                                                <span class="badge bg-info">Недогружен</span>
                                                            @else
                                                                <span class="badge bg-warning text-dark">Перегружен</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif

                                @if(!empty($minerSafetyCheck['suggested_delay_minutes']))
                                    <div class="alert alert-info py-2 small">
                                        <i class="fas fa-clock me-1"></i>
                                        Рекомендация: отложите работы на <strong>{{ $minerSafetyCheck['suggested_delay_minutes'] }} мин</strong>,
                                        пока часть грузовиков завершит рейсы.
                                    </div>
                                @endif
                            @endif

                            <div class="d-flex gap-2 justify-content-end mt-3">
                                <button type="button" class="btn btn-secondary" wire:click="cancelMinerStatusChange">
                                    <i class="fas fa-times me-1"></i> Отменить
                                </button>
                                <button type="button" class="btn btn-danger" wire:click="forceMinerStatus">
                                    <i class="fas fa-exclamation-triangle me-1"></i> Всё равно остановить
                                </button>
                            </div>
                        @else
                            <!-- Обычная форма выбора статуса -->
                            <div class="mb-3">
                                <label class="form-label">Текущий статус:</label>
                                <span class="badge bg-{{ $currentMiner?->getStatusClass() }} fs-6">
                                    {{ $currentMiner?->getStatusLabel() }}
                                </span>
                                @if($currentMiner?->isDelayed())
                                    <small class="text-muted ms-2">
                                        ({{ $currentMiner->getStatusDurationMinutes() }} мин)
                                    </small>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Новый статус:</label>
                                <select class="form-select" wire:model.live="editMinerStatusNew">
                                    @foreach($this->miner_statuses as $status => $label)
                                        <option value="{{ $status }}" {{ $editMinerStatusNew === $status ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            @if($editMinerStatusNew === 'breakdown')
                                <div class="alert alert-danger py-2 small">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <strong>Поломка:</strong> Грузовики будут перенаправлены на другие забои.
                                    Маршруты деактивируются.
                                </div>
                            @elseif(in_array($editMinerStatusNew, ['maintenance', 'dismantling', 'access_setup']))
                                <div class="alert alert-warning py-2 small">
                                    <i class="fas fa-clock me-1"></i>
                                    <strong>Плановая остановка:</strong> Грузовики в пути доедут до забоя.
                                    Новые назначения будут перенаправлены на другие забои.
                                </div>
                            @elseif($editMinerStatusNew === 'active')
                                <div class="alert alert-success py-2 small">
                                    <i class="fas fa-check me-1"></i>
                                    <strong>В работу:</strong> Забой готов к работе, маршруты активируются.
                                </div>
                            @endif
                        @endif
                    </div>
                    @if(!$showMinerWarning)
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeMinerStatusModal">
                                Отмена
                            </button>
                            <button 
                                type="button" 
                                class="btn btn-{{ $editMinerStatusNew === 'active' ? 'success' : ($editMinerStatusNew === 'breakdown' ? 'danger' : 'warning') }}"
                                wire:click="setMinerStatus"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove><i class="fas fa-check"></i> Подтвердить</span>
                                <span wire:loading><i class="fas fa-spinner fa-spin"></i> Сохранение...</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <style>
        .truck-card { transition: all 0.2s; }
        .truck-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .zone-card { min-height: 120px; }
        .zone-card.closed { opacity: 0.6; }
    </style>

    <script>
        // ==========================================
        // ЛОКАЛЬНЫЙ ТАЙМЕР ВРЕМЕНИ РЕЙСА
        // ==========================================
        function updateTripTimers() {
            document.querySelectorAll('.trip-timer').forEach(el => {
                const startedAtStr = el.dataset.startedAt;
                if (!startedAtStr) return;

                const startedAt = new Date(startedAtStr);
                const now = new Date();

                // Общее время рейса в секундах
                const totalSeconds = Math.floor((now - startedAt) / 1000);
                if (totalSeconds < 0) return;

                // Время завершённых пауз (фиксированное)
                const completedPauseSeconds = parseInt(el.dataset.pauseSeconds) || 0;

                // Время активной паузы (вычисляем динамически)
                let activePauseSeconds = 0;
                const activePauseStartStr = el.dataset.activePauseStart;
                if (activePauseStartStr) {
                    const activePauseStart = new Date(activePauseStartStr);
                    activePauseSeconds = Math.floor((now - activePauseStart) / 1000);
                }

                // Чистое время = общее - паузы
                const pauseSeconds = completedPauseSeconds + activePauseSeconds;
                const netSeconds = Math.max(0, totalSeconds - pauseSeconds);

                const hours = Math.floor(netSeconds / 3600);
                const minutes = Math.floor((netSeconds % 3600) / 60);
                const seconds = netSeconds % 60;

                let timeStr;
                if (hours > 0) {
                    timeStr = `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                } else {
                    timeStr = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                }

                el.textContent = timeStr;
            });
        }

        // Запускаем таймер каждую секунду
        setInterval(updateTripTimers, 1000);

        // Обновляем таймеры после обновления Livewire
        document.addEventListener('livewire:update', updateTripTimers);

        // Синхронизация табов после обновления Livewire
        document.addEventListener('livewire:init', () => {
            // Запускаем таймеры сразу
            updateTripTimers();

            // Уведомления
            Livewire.on('notify', (data) => {
                const event = Array.isArray(data) ? data[0] : data;
                if (!event || !event.message) return;

                const container = document.getElementById('global-toast-container');
                const toast = document.createElement('div');

                const bgClass = event.type === 'success' ? 'alert-success' :
                               event.type === 'error' ? 'alert-danger' :
                               event.type === 'warning' ? 'alert-warning' :
                               'alert-info';

                toast.className = `alert ${bgClass} alert-dismissible fade show`;
                toast.innerHTML = `
                    ${event.message}
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                `;
                container.appendChild(toast);

                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
            });

            // WebSocket для обновлений
            console.log('🔧 Setting up Echo listeners...', { Echo: !!window.Echo });
            if (window.Echo) {
                console.log('📡 Subscribing to dispatcher channel...');
                window.Echo.channel('dispatcher')
                    .listen('.truck-updated', (data) => {
                        console.log('🔔 Dispatcher notification:', data);
                        // В Livewire v3 используем Livewire.dispatch
                        Livewire.dispatch('refresh-dispatcher-data');
                        console.log('✅ Dispatched refresh-dispatcher-data');
                    })
                    .listen('.miner-productivity-updated', (data) => {
                        console.log('📊 Miner productivity updated:', data);
                    });
                console.log('✅ Echo listeners set up');
            } else {
                console.warn('⚠️ Echo not available');
            }

            Livewire.on('sync-tabs', () => {
                setTimeout(() => {
                    // Находим активный таб по классу от сервера и активируем через Bootstrap
                    const activeTabButton = document.querySelector('.nav-link.active[data-bs-target]');
                    if (activeTabButton) {
                        const targetId = activeTabButton.getAttribute('data-bs-target');
                        const tabContent = document.querySelector(targetId);
                        if (tabContent && !tabContent.classList.contains('active')) {
                            // Убираем active со всех табов
                            document.querySelectorAll('.tab-pane').forEach(el => {
                                el.classList.remove('show', 'active');
                            });
                            // Добавляем active нужному
                            tabContent.classList.add('show', 'active');
                        }
                    }
                }, 50);
            });
        });
    </script>
</div>