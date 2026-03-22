<div class="dispatcher-panel-wrapper">
    <!-- Toast контейнер -->
    <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

    <!-- Статистика в строку -->
    <div class="card mb-4">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap align-items-center gap-4">
                <!-- Свободные -->
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-success bg-opacity-10 rounded px-2 py-1">
                        <i class="fas fa-parking text-success"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Свободные</small>
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
                        <strong class="text-warning fs-5">{{ $trucks->where('status', 'delayed')->count() }}</strong>
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
                        <small class="text-muted d-block">Поломки</small>
                        <strong class="text-danger fs-5">{{ $this->breakdown_count }}</strong>
                    </div>
                </div>
                <!-- Разделитель -->
                <div class="vr"></div>
                <!-- Активные забои -->
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-secondary bg-opacity-10 rounded px-2 py-1">
                        <i class="fas fa-mountain text-secondary"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Забои</small>
                        <strong class="text-secondary fs-5">{{ $this->active_miners_count }}</strong>
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
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'pausesTab' ? 'active' : '' }}" 
                    data-bs-toggle="tab" data-bs-target="#pausesTab" type="button"
                    wire:click="setActiveTab('pausesTab')">
                Простои
                @if($this->pause_stats['active_count'] > 0)
                    <span class="badge bg-danger ms-1">{{ $this->pause_stats['active_count'] }}</span>
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
                            <th style="width: 150px;">Задержка</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $statusLabels = [
                                'free' => ['label' => 'Свободен', 'color' => 'success', 'icon' => 'fa-parking'],
                                'to_miner' => ['label' => 'К забою', 'color' => 'info', 'icon' => 'fa-arrow-right'],
                                'loading' => ['label' => 'Погрузка', 'color' => 'warning', 'icon' => 'fa-truck-loading'],
                                'transporting' => ['label' => 'Перевозка', 'color' => 'primary', 'icon' => 'fa-truck'],
                                'unloading' => ['label' => 'Разгрузка', 'color' => 'secondary', 'icon' => 'fa-truck-unload'],
                                'breakdown' => ['label' => 'Поломка', 'color' => 'danger', 'icon' => 'fa-wrench'],
                                'delayed' => ['label' => 'Задержка', 'color' => 'warning', 'icon' => 'fa-clock'],
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
                                    if (in_array($truck->status, ['transporting', 'unloading']) && $trip->rock) {
                                        $truckRock = $trip->rock;
                                        $truckRockLabel = 'Загружена';
                                    } elseif ($trip->miner) {
                                        $truckRock = $trip->miner->rocks->first();
                                        $truckRockLabel = 'В забое';
                                    }
                                }
                                
                                // Активная пауза
                                $activePause = null;
                                if (in_array($truck->status, ['delayed', 'breakdown']) && $trip) {
                                    $activePause = $trip->pauses->first();
                                }
                            @endphp
                            <tr class="{{ $truck->status === 'breakdown' ? 'table-danger' : ($truck->status === 'delayed' ? 'table-warning' : ($truck->status === 'free' ? 'table-success' : '')) }}">
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
                            <th style="width: 120px;">Производительность</th>
                            <th style="width: 100px;">В работе</th>
                            <th style="width: 120px;">Статус</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($miners as $miner)
                            @php
                                // Считаем грузовики в работе у этого забоя
                                $trucksAtMiner = $trucks->filter(function($truck) use ($miner) {
                                    $trip = $truck->trips->first();
                                    return $trip && $trip->miner_id === $miner->id && 
                                           in_array($truck->status, ['to_miner', 'loading']);
                                });
                            @endphp
                            <tr class="{{ $miner->active ? '' : 'table-secondary' }}">
                                <td>
                                    <span class="fw-bold">{{ $miner->name_miner }}</span>
                                </td>
                                <td>
                                    @if($miner->rocks->first())
                                        <span class="badge bg-info">{{ $miner->rocks->first()->name_rock }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">{{ $miner->capacity_per_trip ?? '-' }} т/рейс</small>
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
                                </td>
                                <td>
                                    @if($miner->active)
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Активен
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-times me-1"></i>Неактивен
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <button 
                                        class="btn btn-sm {{ $miner->active ? 'btn-outline-danger' : 'btn-outline-success' }}" 
                                        wire:click="toggleMinerStatus({{ $miner->id }})"
                                        wire:loading.attr="disabled"
                                        title="{{ $miner->active ? 'Деактивировать' : 'Активировать' }}">
                                        <i class="fas {{ $miner->active ? 'fa-stop' : 'fa-play' }}" wire:loading.class="fa-spinner fa-spin"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Назначение маршрута -->
        <div class="tab-pane fade {{ $activeTab === 'assignTab' ? 'show active' : '' }}" id="assignTab">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Самосвал</label>
                            <select wire:model.live="selectedTruckId" class="form-select">
                                <option value="">Выберите</option>
                                @foreach($this->free_trucks as $truck)
                                    <option value="{{ $truck->id }}">
                                        {{ $truck->number }} ({{ $truck->load_capacity }}т)
                                    </option>
                                @endforeach
                            </select>
                            @error('selectedTruckId')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Забой</label>
                            <select wire:model.live="selectedMinerId" class="form-select">
                                <option value="">Выберите</option>
                                @foreach($miners as $miner)
                                    <option value="{{ $miner->id }}">
                                        {{ $miner->name_miner }}
                                        @if($miner->rocks->first())
                                            ({{ $miner->rocks->first()->name_rock }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Перегрузка</label>
                            <select wire:model.live="selectedOrderId" class="form-select">
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
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Зона</label>
                            <select wire:model.live="selectedZoneId" class="form-select">
                                <option value="">Автоматически</option>
                                @foreach($availableZones as $zone)
                                    <option value="{{ $zone['id'] }}">
                                        {{ $zone['name'] }} ({{ round($zone['volume']) }}/{{ round($zone['capacity']) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button
                            wire:click="assignRoute"
                            wire:loading.attr="disabled"
                            class="btn btn-primary">
                            <span wire:loading.remove><i class="fas fa-check-circle"></i> Назначить маршрут</span>
                            <span wire:loading><i class="fas fa-spinner fa-spin"></i> Назначение...</span>
                        </button>

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
            <div class="row">
                @foreach($zones as $zone)
                    @php
                        $fillPercent = $zone->capacity > 0 ? min($zone->volume / $zone->capacity * 100, 100) : 0;
                    @endphp
                    <div class="col-md-4 mb-3">
                        <div class="card zone-card {{ $zone->delivery ? '' : 'closed' }}">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>{{ $zone->dump?->name_dump }} - {{ $zone->name_zone }}</span>
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
                                <small class="text-muted">
                                    Породы: {{ $zone->rocks->pluck('name_rock')->join(', ') ?: 'Не указаны' }}
                                </small>
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
            @endphp

            <!-- Фильтры -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <label class="form-label small">Период:</label>
                    <select wire:model.live="pausePeriod" class="form-select form-select-sm">
                        <option value="shift">За смену</option>
                        <option value="today">За сегодня</option>
                        <option value="week">За неделю</option>
                        <option value="month">За месяц</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Тип (можно несколько):</label>
                    <select wire:model.live="pauseTypes" class="form-select form-select-sm" multiple size="5">
                        @foreach(\App\Models\TripPause::types() as $typeKey => $typeLabel)
                            <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Ctrl+клик для выбора нескольких</small>
                </div>
                <div class="col-md-6 d-flex align-items-end justify-content-end">
                    <div class="text-muted small">
                        {{ $pauseStats['period_label'] }}: 
                        <strong class="text-dark">{{ $pauseStats['total_count'] }}</strong> инцидентов,
                        <strong class="text-{{ $pauseStats['total_seconds'] > 0 ? 'danger' : 'muted' }}">
                            {{ $pauseStats['total_formatted'] }}
                        </strong> общего простоя
                    </div>
                </div>
            </div>

            <!-- Сводка по типам -->
            @if($pauseStats['by_type']->count() > 0)
            <div class="row mb-4">
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

            <!-- Таблица простоев -->
            <div class="card">
                <div class="card-header py-2">
                    <strong>
                        Детализация простоев
                        @if(!empty($pauseTypes))
                            <span class="text-muted fw-normal">по причинам:</span>
                            @foreach($pauseTypes as $i => $pt)
                                <span class="badge bg-warning text-dark">{{ \App\Models\TripPause::typeLabel($pt) }}</span>
                            @endforeach
                        @endif
                    </strong>
                </div>
                <div class="card-body p-0">
                    @if($pauseStats['pauses']->count() > 0)
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Время</th>
                                    <th>Самосвал</th>
                                    <th>Тип</th>
                                    <th>Длительность</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pauseStats['pauses'] as $pause)
                                    <tr class="{{ $pause->ended_at ? '' : 'table-warning' }}">
                                        <td>
                                            <small>{{ $pause->started_at->format('d.m H:i') }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ $pause->truck?->number ?? '—' }}</strong>
                                        </td>
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
                                        <td>
                                            @if($pause->ended_at)
                                                <span class="badge bg-success">Завершён</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Активен</span>
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
                        <h5 class="text-muted mb-1">Нет простоев за выбранный период</h5>
                        <p class="text-muted small mb-0">{{ $pauseStats['period_label'] }}</p>
                    </div>
                    @endif
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

    <style>
        .truck-card { transition: all 0.2s; }
        .truck-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .zone-card { min-height: 120px; }
        .zone-card.closed { opacity: 0.6; }
    </style>

    <script>
        // Уведомления
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Livewire !== 'undefined') {
                Livewire.on('notify', (data) => {
                    const event = Array.isArray(data) ? data[0] : data;
                    if (!event || !event.message) return;

                    const container = document.getElementById('toast-container');
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
            }

            // WebSocket для обновлений
            if (window.Echo) {
                window.Echo.channel('dispatcher')
                    .listen('.truck-updated', (data) => {
                        console.log('Dispatcher notification:', data);
                    });
            }
        });

        // Синхронизация табов после обновления Livewire
        if (typeof Livewire !== 'undefined') {
            Livewire.hook('commit', ({ succeed }) => {
                succeed(() => {
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
        }
    </script>
</div>
