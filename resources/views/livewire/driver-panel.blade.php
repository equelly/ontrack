<div class="driver-panel-wrapper">
    <!-- Toast контейнер для уведомлений -->
    <div id="global-toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

    <div class="container-fluid p-2 p-md-4">
        <!-- Выбор грузовика -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select wire:model.live="selectedTruckId" class="form-control industrial-input" style="max-width: 300px;">
                        <option value="">-- Выберите грузовик --</option>
                        @foreach($trucks as $t)
                            <option value="{{ $t['id'] }}" {{ $t['id'] == $selectedTruckId ? 'selected' : '' }}>
                                {{ $t['number'] }}
                                @if($t['is_mine'] && $t['is_breakdown'])
                                    (на ремонте)
                                @elseif(!$t['is_free'] && !$t['is_mine'])
                                    ({{ $t['driver_name'] ?? 'занят' }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <button wire:click="selectTruck" wire:loading.attr="disabled" class="btn btn-industrial-primary" style="min-width: 120px; position: relative;">
                        <span wire:loading.remove>Выбрать</span>
                        <span wire:loading class="position-absolute top-50 start-50 translate-middle"><i class="bi bi-spinner bi-spin"></i></span>
                    </button>
                </div>
            </div>
        </div>

        @if($truck)
        <!-- Заголовок: Грузовик | Водитель -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex align-items-center flex-wrap" style="gap: 0.5rem 2rem;">
                    <div>
                        <span class="industrial-label d-inline">Грузовик:</span>
                        <strong class="ms-1">{{ $truck->number }}</strong>
                        @if($truck->brand)
                            <span class="text-muted ms-1">({{ $truck->brand }})</span>
                        @endif
                    </div>
                    <div>
                        <span class="industrial-label d-inline">Водитель:</span>
                        <strong class="ms-1">{{ auth()->user()->name }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- МАРШРУТ + Статус -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex align-items-center" style="gap: 1rem;">
                    <h5 class="mb-0 industrial-label text-dark" style="font-size: 1rem;">Маршрут</h5>
                    <span class="badge bg-{{ $statusColor }} fs-6" style="border-radius: 0;">{{ $statusLabel }}</span>
                </div>
            </div>
        </div>

        @if($currentTrip)
        <!-- Информация о маршруте -->
        <div class="card industrial-card">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-start flex-wrap" style="gap: 0.5rem;">
                    <div class="route-point">
                        <div class="route-point-label">Забой</div>
                        <div class="route-point-value">{{ $currentTrip->miner->name_miner ?? '-' }}</div>
                    </div>
                    <i class="bi bi-arrow-right route-arrow"></i>
                    <div class="route-point">
                        <div class="route-point-label">Перегрузка</div>
                        <div class="route-point-value">{{ $currentTrip->dump->name_dump ?? '-' }}</div>
                    </div>
                    <i class="bi bi-arrow-right route-arrow"></i>
                    <div class="route-point">
                        <div class="route-point-label">Зона</div>
                        @if($currentTrip->zone)
                            <div class="route-point-value text-success">{{ $currentTrip->zone->name_zone }}</div>
                        @else
                            <div class="route-point-value text-warning">Не назначена</div>
                        @endif
                    </div>
                    <i class="bi bi-arrow-right route-arrow"></i>
                    <div class="route-point">
                        <div class="route-point-label">Порода</div>
                        @php
                            $isLoaded = in_array($truck->status, ['transporting', 'unloading', 'waiting_unloading']);
                            $rock = $isLoaded && $currentTrip->rock_id ? $currentTrip->rock : $currentTrip->miningOrder?->rock;
                        @endphp
                        <div class="route-point-value">{{ $rock?->name_rock ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Показатели: Расстояние | Время | Скорость -->
        <div class="row mb-4 mt-3">
            <div class="col-12">
                <div class="d-flex align-items-center flex-wrap" style="gap: 0.5rem 2rem;">
                    <div>
                        <span class="industrial-label d-inline">Расстояние:</span>
                        <strong class="ms-1">{{ $currentTrip->miningOrder->distance_km ?? '-' }} км</strong>
                    </div>
                    <div>
                        <span class="industrial-label d-inline">Время в пути:</span>
                        <strong class="ms-1 timer-display" id="trip-time"
                               data-started="{{ $tripStartedAt ?? '' }}"
                               data-pause-started="{{ $pauseStartedAt ?? '' }}"
                               data-pause-type="{{ $pauseType ?? '' }}"
                               data-total-pause="{{ $totalPauseSeconds }}"
                               data-truck-status="{{ $truck->status }}">-</strong>
                    </div>
                    <div>
                        <span class="industrial-label d-inline">Объём:</span>
                        <strong class="ms-1">{{ $currentTrip->load_volume ?? $truck->load_capacity ?? '-' }} т</strong>
                    </div>
                </div>
            </div>
        </div>
        @else
        <div class="row mb-4">
            <div class="col-12">
                <div class="industrial-alert border-info text-info">
                    Нет активного маршрута
                </div>
            </div>
        </div>
        @endif

        <!-- Управление статусами -->
        <div class="row mb-4" data-truck-status="{{ $truck->status }}">
            <div class="col-12">
                @php $status = $truck->status; @endphp

                {{-- Свободен --}}
                @if($status === 'free')
                    @if(!$currentTrip)
                        <button wire:click="assignRoute" wire:loading.attr="disabled" style="flex: 1 1 300px; min-width: 0; width: 100%; display: flex; align-items-center justify-content-center gap: 0.5rem; padding: 0.5rem 1rem;" class="btn btn-industrial-primary btn-lg w-100 mb-2">
                            <span wire:loading.remove>Получить маршрут</span>
                            <span wire:loading><i class="bi bi-spinner bi-spin"></i> Получение...</span>
                        </button>
                    @endif
                @endif

                {{-- Рейс завершён --}}
                @if($status === 'completed')
                    <div class="industrial-alert border-success text-success">
                        Рейс завершён. Запросите новый маршрут или уйдите в отстой.
                    </div>
                    <button wire:click="assignRoute" class="btn btn-industrial-primary btn-lg w-100 mb-2">
                        Запросить маршрут
                    </button>
                    <button wire:click="goToStandby" class="btn btn-industrial-secondary w-100">
                        Уйти в отстой
                    </button>
                @endif

                {{-- В пути к забою --}}
                @if($status === 'to_miner')
                    <button wire:click="startLoading" class="btn btn-industrial-primary btn-lg w-100 mb-2">
                        Прибыл на погрузку
                    </button>
                    <div class="action-row">
                        <button wire:click="openDelayModal" class="btn btn-industrial-secondary">
                            <i class="bi bi-clock"></i> Задержка
                        </button>
                        <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="btn btn-remove-zone">
                            <i class="bi bi-exclamation-triangle"></i> Поломка
                        </button>
                    </div>
                @endif

                {{-- На погрузке --}}
                @if($status === 'loading')
                    <div class="industrial-alert border-warning text-warning text-center mb-2">
                        <strong>Ожидание завершения погрузки...</strong>
                    </div>
                    <div class="action-row">
                        <button wire:click="openDelayModal" class="btn btn-industrial-secondary">
                            <i class="bi bi-clock"></i> Задержка
                        </button>
                        <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="btn btn-remove-zone">
                            <i class="bi bi-exclamation-triangle"></i> Поломка
                        </button>
                    </div>
                @endif

                {{-- Ожидание назначения зоны --}}
                @if($status === 'waiting_unloading')
                    <div class="industrial-alert border-warning text-warning text-center mb-2">
                        <strong>Ожидание назначения зоны разгрузки</strong>
                    </div>
                    <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="btn btn-remove-zone w-100">
                        <i class="bi bi-exclamation-triangle"></i> Поломка
                    </button>
                @endif

                {{-- Везём груз --}}
                @if($status === 'transporting')
                    <button wire:click="startUnloading" class="btn btn-industrial-primary btn-lg w-100 mb-2">
                        Прибыл на выгрузку
                    </button>
                    <div class="action-row">
                        <button wire:click="openDelayModal" class="btn btn-industrial-secondary">
                            <i class="bi bi-clock"></i> Задержка
                        </button>
                        <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="btn btn-remove-zone">
                            <i class="bi bi-exclamation-triangle"></i> Поломка
                        </button>
                    </div>
                @endif

                {{-- Разгрузка --}}
                @if($status === 'unloading')
                    <button wire:click="completeTrip" class="btn btn-industrial-primary btn-lg w-100 mb-2">
                        Завершить рейс
                    </button>
                    <button wire:click="openZoneModal" class="btn btn-industrial-secondary w-100">
                        Сменить зону
                    </button>
                @endif

                {{-- Задержка --}}
                @if($status === 'delayed')
                    <div class="industrial-alert border-warning text-warning">
                        Маршрут приостановлен: {{ \App\Models\TripPause::typeLabel($pauseType ?? 'other') }}
                    </div>
                    <button wire:click="resumeFromDelay" class="btn btn-industrial-primary btn-lg w-100 mb-2">
                        Задержка окончена
                    </button>
                    <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="btn btn-remove-zone w-100">
                        <i class="bi bi-exclamation-triangle"></i> Поломка
                    </button>
                @endif

                {{-- Поломка --}}
                @if($status === 'breakdown')
                    <div class="industrial-alert border-danger text-danger">
                        Поломка. После ремонта выберите действие.
                    </div>
                    @if($currentTrip)
                        <button wire:click="resolveBreakdownContinue" class="btn btn-industrial-primary btn-lg w-100 mb-2">
                            Продолжить рейс
                        </button>
                        <button wire:click="resolveBreakdownCancel" wire:confirm="Отменить рейс?" class="btn btn-remove-zone w-100">
                            Отменить рейс
                        </button>
                    @else
                        <button wire:click="resolveBreakdownContinue" class="btn btn-industrial-primary btn-lg w-100">
                            Поломка устранена
                        </button>
                    @endif
                @endif

                {{-- Обслуживание (подкачка шин, обтяжка колёс) --}}
                @if($status === 'service')
                    <div class="industrial-alert border-warning text-warning text-center mb-2">
                        <strong>{{ $currentServiceTask['type'] ?? 'Обслуживание' }}</strong>
                        @if(!empty($currentServiceTask['post_name']))
                            <div class="mt-1">Пост: {{ $currentServiceTask['post_name'] }}</div>
                        @endif
                        @if(!empty($currentServiceTask['started_at']))
                            <div class="text-muted small">Начало: {{ $currentServiceTask['started_at'] }}</div>
                        @endif
                    </div>
                    <button wire:click="completeService" style="flex: 1 1 300px; min-width: 0; width: 100%; display: flex; align-items-center justify-content-center gap: 0.5rem; padding: 0.5rem 1rem;" class="btn btn-industrial-primary btn-lg w-100 mb-2">
                        <i class="bi bi-check-lg me-1"></i> Завершить обслуживание
                    </button>
                @endif

                {{-- Заправка --}}
                @if($status === 'fueling')
                    <div class="industrial-alert border-info text-info text-center mb-2">
                        <strong>Заправка</strong>
                        @if(!empty($currentServiceTask['post_name']))
                            <div class="mt-1">Пост: {{ $currentServiceTask['post_name'] }}</div>
                        @endif
                        @if(!empty($currentServiceTask['started_at']))
                            <div class="text-muted small">Начало: {{ $currentServiceTask['started_at'] }}</div>
                        @endif
                    </div>
                    <button wire:click="completeService" class="btn btn-industrial-primary btn-lg w-100 mb-2">
                        <i class="bi bi-check-lg me-1"></i> Завершить заправку
                    </button>
                @endif

                {{-- Техническое обслуживание (ТО) --}}
                @if($status === 'maintenance')
                    <div class="industrial-alert border-warning text-warning text-center mb-2">
                        <strong>{{ $currentServiceTask['type'] ?? 'Техническое обслуживание' }}</strong>
                        @if(!empty($currentServiceTask['post_name']))
                            <div class="mt-1">Пост: {{ $currentServiceTask['post_name'] }}</div>
                        @endif
                        @if(!empty($currentServiceTask['started_at']))
                            <div class="text-muted small">Начало: {{ $currentServiceTask['started_at'] }}</div>
                        @endif
                        @if(!empty($currentServiceTask['duration']))
                            <div class="text-muted small">Плановая длительность: {{ $currentServiceTask['duration'] }} мин</div>
                        @endif
                    </div>
                    <button wire:click="completeService" class="btn btn-industrial-primary btn-lg w-100 mb-2">
                        <i class="bi bi-check-lg me-1"></i> Завершить ТО
                    </button>
                @endif
            </div>
        </div>

        <!-- Запланированные ТО и заправка на смену -->
        @if(count($plannedShiftServices) > 0)
        <div class="row mb-3">
            <div class="col-12">
                <div class="card industrial-card border-warning">
                    <div class="card-header industrial-header" style="color: #1f2937;">
                        <i class="bi bi-calendar-check me-2"></i>
                        Запланировано ТО
                    </div>
                    <div class="card-body py-2">
<!-- width: 100% заставляет растягиваться, а flex-shrink разрешает сжиматься в flex-родителях -->
<div class="table-responsive" style="width: 100%; flex-shrink: 1; overflow-x: auto;">
    <table class="table table-sm table-hover mb-0" style="width: 100%; table-layout: auto;">
        <thead>
            <tr>
                <th class="industrial-label">Тип</th>
                <th class="industrial-label">Позиция</th>
                <th class="industrial-label">Время</th>
                <th class="industrial-label">Статус</th>
            </tr>
        </thead>
        <tbody>
            <!--[if BLOCK]><![endif]-->                                        
            <tr class="table-success">
                <!-- Ограничиваем только максимальную ширину ячейки с текстом для узких экранов -->
                <td style="max-width: 150px; white-space: normal;"><strong>Подкачка шин</strong></td>
                <td>
                    <!--[if BLOCK]><![endif]-->                                                    
                    <span class="badge bg-success" style="border-radius: 0;">В работе</span>
                    <!--[if ENDBLOCK]><![endif]-->                                            
                </td>
                <td>
                    <!--[if BLOCK]><![endif]-->                                                    
                    <span class="text-muted">0 мин</span>
                    <!--[if ENDBLOCK]><![endif]-->                                            
                </td>
                <td>
                    <!--[if BLOCK]><![endif]-->                                                    
                    <span class="text-success" style="white-space: nowrap;"><i class="bi bi-check-circle"></i> Выполняется</span>
                    <!--[if ENDBLOCK]><![endif]-->                                            
                </td>
            </tr>
            <!--[if ENDBLOCK]><![endif]-->                                
        </tbody>
    </table>
</div>

                    </div>
                </div>
            </div>
        </div>
        @endif
        <!-- Вкладка Топливо -->
        <div class="row">
            <div class="col-12">
                <div class="accordion industrial-accordion" id="fuelAccordion">
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed industrial-accordion-button py-2 px-3" 
                                    style="width: 100%; display: flex; text-align: left;" 
                                    type="button" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#fuelCollapse">
                                <i class="bi bi-fuel-pump me-2"></i>
                                Топливо: @if($this->fuelStats['fuel_percent'] ?? null) {{ round($this->fuelStats['fuel_percent']) }}% @else - @endif
                            </button>
                        </h2>
                        <div id="fuelCollapse" class="accordion-collapse collapse" data-bs-parent="#fuelAccordion">
                            <div class="accordion-body py-2">
                                <div class="mb-3">
                                    <div class="d-flex flex-wrap" style="gap: 0.5rem 2rem;">
                                        <div>
                                            <span class="industrial-label d-inline">Текущий остаток:</span>
                                            <strong class="ms-1">{{ $truck->fuel_level }} л / {{ $truck->truckModel->fuel_capacity }} л</strong>
                                        </div>
                                        <div>
                                            <span class="industrial-label d-inline">Примерно рейсов:</span>
                                            <strong class="ms-1">{{ $this->fuelStats['estimated_trips'] ?? 0 }} рейсов</strong>
                                            <small class="text-muted">(ср. расстояние: {{ $this->fuelStats['avg_distance'] ?? '-' }} км)</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <input type="number" 
                                        class="form-control industrial-input" 
                                        style="width: 120px;"
                                        wire:model.defer="addedFuel"
                                        placeholder="Литры"
                                        @if($truck->status !== 'fueling') disabled @endif>
                                    <button class="btn btn-industrial-primary" 
                                            wire:click="updateFuelLevel"
                                            @if($truck->status !== 'fueling') disabled @endif>
                                        Залить топливо
                                    </button>
                                    <small class="text-muted ms-2">
                                        Доступно для заправки: {{ $truck->truckModel->fuel_capacity - $truck->fuel_level }} л
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Ограничения для техники -->
        <div class="row">
            <div class="col-12">
                <div class="accordion industrial-accordion" id="restrictionsAccordion">
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed industrial-accordion-button py-2 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#restrictionsCollapse">
                                <i class="bi bi-slash-circle me-2"></i>
                                Ограничения для автомобиля
                            </button>
                        </h2>
                        <div id="restrictionsCollapse" class="accordion-collapse collapse" data-bs-parent="#restrictionsAccordion">
                            <div class="accordion-body py-2">
                                <!-- Текущая грузоподъемность -->
                                <div class="mb-4">
                                    <h6 class="industrial-label mb-2">Текущая грузоподъемность</h6>
                                    <div class="d-flex align-items-center gap-3">
                                        <div>
                                            <span class="text-muted">Паспортная:</span>
                                            <strong class="ms-2">{{ $truck->truckModel->load_capacity }} т</strong>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="number" 
                                                class="form-control industrial-input" 
                                                style="width: 100px;"
                                                wire:model.defer="newLoadCapacity" 
                                                min="1" 
                                                max="{{ $truck->truckModel->load_capacity }}">
                                            <button class="btn btn-industrial-primary" 
                                                    wire:click="updateLoadCapacity">
                                                Сохранить
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Запрет на перевозку пород -->
                                <div>
                                    <h6 class="industrial-label mb-2">Запрет на перевозку пород</h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($rocks as $rock)
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                    type="checkbox" 
                                                    id="rock-{{ $rock['id'] }}"
                                                    wire:click="toggleRockRestriction({{ $rock['id'] }})"
                                                    @if($truck->restrictions->contains('rock_id', $rock['id'])) checked @endif>
                                                <label class="form-check-label" for="rock-{{ $rock['id'] }}">
                                                    {{ $rock['name_rock'] }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Статистика (скрыта по умолчанию) -->
        <div class="row">
            <div class="col-12">
                <div class="accordion industrial-accordion" id="statsAccordion">
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed industrial-accordion-button py-2 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#statsCollapse">
                                <i class="bi bi-bar-chart me-2"></i>
                                Статистика за смену ({{ $stats['shift_name'] ?? '-' }})
                            </button>
                        </h2>
                        <div id="statsCollapse" class="accordion-collapse collapse" data-bs-parent="#statsAccordion">
                            <div class="accordion-body py-2">
                                <div class="d-flex flex-wrap" style="gap: 0.5rem 2rem;">
                                    <div>
                                        <span class="industrial-label d-inline">Рейсов:</span>
                                        <strong class="ms-1">{{ $stats['today_trips'] ?? 0 }}</strong>
                                    </div>
                                    <div>
                                        <span class="industrial-label d-inline">Объём:</span>
                                        <strong class="ms-1">{{ number_format($stats['today_volume'] ?? 0, 1) }} т</strong>
                                    </div>
                                    <div>
                                        <span class="industrial-label d-inline">Ср. скорость:</span>
                                        <strong class="ms-1">{{ $stats['avg_speed'] ?? '-' }} @if($stats['avg_speed']) км/ч @endif</strong>
                                    </div>
                                    <div>
                                        <span class="industrial-label d-inline">Всего рейсов:</span>
                                        <span class="ms-1">{{ $stats['total_trips'] ?? 0 }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Секция обслуживания -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="accordion industrial-accordion" id="serviceAccordion">
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed industrial-accordion-button py-2 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#serviceCollapse">
                                <i class="bi bi-tools me-2"></i>
                                Обслуживание
                                @if(count($pendingServiceTasks) > 0)
                                    <span class="badge bg-warning text-dark ms-2" style="border-radius: 0;">{{ count($pendingServiceTasks) }}</span>
                                @endif
                            </button>
                        </h2>
                        <div id="serviceCollapse" class="accordion-collapse collapse" data-bs-parent="#serviceAccordion">
                            <div class="accordion-body py-2">
                                <!-- Показатели обслуживания -->
                                <div class="mb-3">
                                    <div class="d-flex flex-wrap" style="gap: 0.5rem 2rem;">
                                        <div>
                                            <span class="industrial-label d-inline">Пробег с заправки:</span>
                                            <strong class="ms-1 {{ $serviceStats['mileage_since_fuel'] >= $serviceStats['fueling_threshold'] ? 'text-danger' : '' }}">
                                                {{ $serviceStats['mileage_since_fuel'] }} / {{ $serviceStats['fueling_threshold'] }} км
                                            </strong>
                                        </div>
                                        <div>
                                            <span class="industrial-label d-inline">Мото-часы с ТО:</span>
                                            <strong class="ms-1">
                                                {{ $serviceStats['moto_hours_since_to'] }} ч
                                            </strong>
                                            <small class="text-muted">(след. {{ $serviceStats['next_to_type'] }})</small>
                                        </div>
                                    </div>
                                </div>

                                @if(count($pendingServiceTasks) > 0)
                                    <div class="mb-3">
                                        <h6 class="industrial-label mb-2">Запланировано:</h6>
                                        @foreach($pendingServiceTasks as $task)
                                            <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-1" style="border-color: #cbd5e1 !important; border-radius: 4px !important;">
                                                <div>
                                                    <strong>{{ $task['type'] }}</strong>
                                                    @if($task['post_name'])
                                                        <span class="badge bg-primary ms-1" style="border-radius: 0;">{{ $task['post_name'] }}</span>
                                                    @elseif($task['queue_position'])
                                                        <span class="badge bg-secondary ms-1" style="border-radius: 0;">Очередь: {{ $task['queue_position'] }}</span>
                                                    @endif
                                                    @if($task['started_at'])
                                                        <span class="badge bg-success ms-1" style="border-radius: 0;">Начато: {{ $task['started_at'] }}</span>
                                                    @endif
                                                </div>
                                                @if(!$task['started_at'])
                                                    <button wire:click="cancelServiceTask({{ $task['id'] }})"
                                                            wire:confirm="Отменить заявку?"
                                                            class="btn btn-sm btn-remove-zone">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                    <!-- style="..." здесь полностью заменяет row/col сетку и гарантирует перенос -->
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; width: 100%;">
                        
                        <!-- Кнопка 1 -->
                        <button wire:click="requestTireInflation" 
                                style="flex: 1 1 300px; min-width: 0; width: 100%; display: flex; align-items-center justify-content-center gap: 0.5rem; padding: 0.5rem 1rem;" 
                                class="btn btn-industrial-secondary text-wrap">
                            <svg xmlns="http://w3.org" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="flex-shrink: 0;">
                                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                                <path d="M8 10a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                            </svg>
                            <span style="white-space: normal;">Подкачка шин</span>
                        </button>
                        <!-- Кнопка 2 -->
                        <button wire:click="requestWheelTightening" 
                            style="flex: 1 1 300px; min-width: 0; width: 100%; display: flex; align-items-center justify-content-center gap: 0.5rem; padding: 0.5rem 1rem;" 
                            class="btn btn-industrial-secondary text-wrap">
                                <svg xmlns="http://w3.org" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="flex-shrink: 0;">
                                    <path d="M11.42 16a3 3 0 0 0 2.29-1.05l1.72-2.06a3 3 0 0 0 .57-2.39l-.65-3.67a3 3 0 0 0-1.57-2.18L10.51.57a3 3 0 0 0-2.42 0L4.82 2.65a3 3 0 0 0-1.57 2.18l-.65 3.67a3 3 0 0 0 .57 2.39l1.72 2.06A3 3 0 0 0 7.18 16zM8 1a2 2 0 0 1 1.21.38l3.27 2.08a2 2 0 0 1 .79 1.09l.65 3.67a2 2 0 0 1-.29 1.2l-1.73 2.06a2 2 0 0 1-1.52.7H7.18a2 2 0 0 1-1.52-.7l-1.73-2.06a2 2 0 0 1-.29-1.2l.65-3.67a2 2 0 0 1 .79-1.09L6.79 1.38A2 2 0 0 1 8 1"/>
                                    <path d="M8 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6m0 1a4 4 0 1 0 0-8 4 4 0 0 0 0 8"/>
                                </svg>
                                    <span style="white-space: normal;">Обтяжка колёс</span>
                        </button>
                        <button style="flex: 1 1 300px; min-width: 0; width: 100%; display: flex; align-items-center justify-content-center gap: 0.5rem; padding: 0.5rem 1rem;" 
                            class="btn btn-industrial-secondary text-wrap">
                            <a href="{{ route('order.index') }}" style="text-decoration: none;">
                                <i class="bi bi-tools me-2"></i>
                                Заявки
                            </a>
                        </button> 
                                    
                    </div>

                    </div>
                </div>
            </div>
        </div>               
        </div>
        </div>
        @else
        <div class="industrial-alert border-info text-info text-center py-4">
            Выберите грузовик для начала работы
        </div>
        @endif

        <!-- Модальные окна -->
        @if($showZoneModal)
        <div class="modal fade show d-block" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content industrial-card">
                    <div class="modal-header industrial-header">
                        <h5 class="modal-title">Выбор зоны разгрузки</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="closeZoneModal"></button>
                    </div>
                    <div class="modal-body">
                        @forelse($availableZones as $zone)
                            <div class="border rounded p-2 mb-2" style="cursor: pointer; border-color: #cbd5e1 !important; border-radius: 4px !important;" wire:click="selectZone({{ $zone['id'] }})">
                                <strong>{{ $zone['name'] }}</strong>
                                <small class="text-muted d-block">{{ $zone['dump_name'] }} | Свободно: {{ $zone['available_capacity'] }} м³</small>
                            </div>
                        @empty
                            <div class="industrial-alert border-warning text-warning mb-0">Нет доступных зон</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($showDelayModal)
        <div class="modal fade show d-block" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content industrial-card">
                    <div class="modal-header industrial-header">
                        <h5 class="modal-title">Задержка</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="closeDelayModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="industrial-label">Причина:</label>
                            <select class="form-control industrial-input" wire:model="delayReason">
                                <option value="traffic">Пробки</option>
                                <option value="road_works">Дорожные работы</option>
                                <option value="waiting_loading">Ожидание погрузки</option>
                                <option value="waiting_unloading">Ожидание выгрузки</option>
                                <option value="weather">Погодные условия</option>
                                <option value="other">Другое</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="industrial-label">Ожидаемое время (мин):</label>
                            <input type="number" class="form-control industrial-input" wire:model="delayMinutes" min="1" max="120">
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-industrial-secondary" wire:click="closeDelayModal">Отмена</button>
                        <button type="button" class="btn btn-industrial-primary" wire:click="confirmDelay">Подтвердить</button>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <script>
        @if($truck)
        window.truckId = {{ $truck->id }};
        window.currentTruckId = {{ $truck->id }};
        @else
        window.currentTruckId = null;
        @endif

        let timerInterval = null;

        function formatTime(seconds, prefix = '') {
            if (seconds === null || seconds < 0) return '-';
            const hours = Math.floor(seconds / 3600);
            const min = Math.floor((seconds % 3600) / 60);
            const sec = seconds % 60;
            if (hours > 0) {
                return prefix + hours + ':' + String(min).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
            }
            return prefix + min + ':' + String(sec).padStart(2, '0');
        }

        function getCurrentStatus() {
            const el = document.querySelector('[data-truck-status]');
            return el ? el.getAttribute('data-truck-status') : 'free';
        }

        function calculateTripSeconds() {
            const el = document.getElementById('trip-time');
            if (!el) return null;
            const startedAtStr = el.getAttribute('data-started');
            if (!startedAtStr) return null;
            const startedAt = new Date(startedAtStr);
            if (isNaN(startedAt.getTime())) return null;
            const now = new Date();
            let totalSeconds = Math.floor((now - startedAt) / 1000);
            const totalPause = parseInt(el.getAttribute('data-total-pause') || '0', 10);
            totalSeconds -= totalPause;
            const pauseStartedStr = el.getAttribute('data-pause-started');
            if (pauseStartedStr) {
                const pauseStarted = new Date(pauseStartedStr);
                if (!isNaN(pauseStarted.getTime())) {
                    totalSeconds -= Math.floor((now - pauseStarted) / 1000);
                }
            }
            return totalSeconds;
        }

        function calculateFrozenSeconds() {
            const el = document.getElementById('trip-time');
            if (!el) return null;
            const startedAtStr = el.getAttribute('data-started');
            const pauseStartedStr = el.getAttribute('data-pause-started');
            if (!startedAtStr || !pauseStartedStr) return null;
            const startedAt = new Date(startedAtStr);
            const pauseStarted = new Date(pauseStartedStr);
            if (isNaN(startedAt.getTime()) || isNaN(pauseStarted.getTime())) return null;
            let frozenSeconds = Math.floor((pauseStarted - startedAt) / 1000);
            const totalPause = parseInt(el.getAttribute('data-total-pause') || '0', 10);
            frozenSeconds -= totalPause;
            return frozenSeconds;
        }

        function updateTimer() {
            const el = document.getElementById('trip-time');
            if (!el) return;
            const status = getCurrentStatus();
            const pauseType = el.getAttribute('data-pause-type');
            let seconds;
            let prefix = '';

            if (status === 'breakdown' || status === 'delayed') {
                seconds = calculateFrozenSeconds();
                prefix = pauseType === 'breakdown' ? '⏸ ' : '⏸ ';
            } else if (status === 'free') {
                el.innerText = '-';
                return;
            } else {
                seconds = calculateTripSeconds();
            }
            el.innerText = formatTime(seconds, prefix);
        }

        function startTimer() {
            const el = document.getElementById('trip-time');
            if (!el) return;
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            const status = getCurrentStatus();
            const started = el.getAttribute('data-started');
            if (status === 'free' && !started) {
                el.innerText = '-';
                return;
            }
            updateTimer();
            timerInterval = setInterval(updateTimer, 1000);
        }

        let echoChannels = [];

        function subscribeToTruckChannels(truckId) {
            echoChannels.forEach(ch => {
                if (window.Echo) window.Echo.leave(ch);
            });
            echoChannels = [];
            if (!truckId || !window.Echo) return;

            window.Echo.private(`driver.${truckId}`)
                .listen('.route.updated', (eventData) => {
                    Livewire.dispatch('route-updated', { data: { ...eventData, truck_id: truckId } });
                })
                .listen('.zone.changed', (eventData) => {
                    Livewire.dispatch('zone-changed');
                });
            echoChannels.push(`driver.${truckId}`);

            window.Echo.private(`truck.${truckId}`)
                .listen('.loading.completed', (eventData) => {
                    Livewire.dispatch('loading-completed', { data: { ...eventData, truck_id: truckId } });
                });
            echoChannels.push(`truck.${truckId}`);
        }

        document.addEventListener('livewire:init', () => {
            // Запускаем таймер и подписываемся на каналы
            startTimer();
            @if($truck)
            subscribeToTruckChannels({{ $truck->id }});
            @endif

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
                toast.style.borderRadius = '0';
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

            Livewire.on('restart-timer', () => setTimeout(startTimer, 50));
            Livewire.on('set-cookie', (data) => {
                const event = Array.isArray(data) ? data[0] : data;
                if (!event || !event.name) return;
                const date = new Date();
                date.setTime(date.getTime() + (event.days * 24 * 60 * 60 * 1000));
                document.cookie = `${event.name}=${event.value};expires=${date.toUTCString()};path=/`;
            });
            Livewire.on('truck-selected', (data) => {
                const event = Array.isArray(data) ? data[0] : data;
                if (event && event.truck_id) subscribeToTruckChannels(event.truck_id);
            });
        });
    </script>
</div> 