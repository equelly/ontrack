    <x-layouts.app :hideNav="true">
    <div class="driver-panel-wrapper">
    <!-- Toast контейнер для уведомлений -->
    <div id="global-toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>
</x-layouts.app>
    
    <style>
        .bi-spin { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .route-arrow { font-size: 1.5rem; color: #6c757d; }
        .route-point { text-align: center; padding: 0.75rem; }
        .route-point-label { font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
        .route-point-value { font-size: 1.1rem; font-weight: 600; }
        .timer-display { font-family: 'Courier New', monospace; font-size: 1.25rem; font-weight: bold; }
        .action-row { display: flex; gap: 0.5rem; }
        .action-row .btn { flex: 1; }
    </style>

    <div class="container-fluid py-3 bg-gray-100">
        <!-- Выбор грузовика -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select wire:model.live="selectedTruckId" class="form-select" style="max-width: 300px;">
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
                    <button wire:click="selectTruck" wire:loading.attr="disabled" class="btn btn-primary" style="min-width: 120px;">
                        <span>Выбрать</span>
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
                        <span class="text-muted">Грузовик:</span>
                        <strong class="ms-1">{{ $truck->number }}</strong>
                        @if($truck->brand)
                            <span class="text-muted ms-1">({{ $truck->brand }})</span>
                        @endif
                    </div>
                    <div>
                        <span class="text-muted">Водитель:</span>
                        <strong class="ms-1">{{ auth()->user()->name }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- МАРШРУТ + Статус -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex align-items-center" style="gap: 1rem;">
                    <h5 class="mb-0 text-uppercase" style="letter-spacing: 1px;">Маршрут</h5>
                    <span class="badge bg-{{ $statusColor }} fs-6">{{ $statusLabel }}</span>
                </div>
            </div>
        </div>

        @if($currentTrip)
        <!-- Информация о маршруте -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex align-items-center justify-content-start flex-wrap" style="gap: 0.5rem;">
                    <!-- Забой -->
                    <div class="route-point">
                        <div class="route-point-label">Забой</div>
                        <div class="route-point-value">{{ $currentTrip->miner->name_miner ?? '-' }}</div>
                    </div>

                    <i class="bi bi-arrow-right route-arrow"></i>

                    <!-- Перегрузка -->
                    <div class="route-point">
                        <div class="route-point-label">Перегрузка</div>
                        <div class="route-point-value">{{ $currentTrip->dump->name_dump ?? '-' }}</div>
                    </div>

                    <i class="bi bi-arrow-right route-arrow"></i>

                    <!-- Зона -->
                    <div class="route-point">
                        <div class="route-point-label">Зона</div>
                        @if($currentTrip->zone)
                            <div class="route-point-value text-success">{{ $currentTrip->zone->name_zone }}</div>
                        @else
                            <div class="route-point-value text-warning">Не назначена</div>
                        @endif
                    </div>

                    <i class="bi bi-arrow-right route-arrow"></i>

                    <!-- Порода -->
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
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex align-items-center flex-wrap" style="gap: 0.5rem 2rem;">
                    <div>
                        <span class="text-muted">Расстояние:</span>
                        <strong class="ms-1">{{ $currentTrip->miningOrder->distance_km ?? '-' }} км</strong>
                    </div>
                    <div>
                        <span class="text-muted">Время в пути:</span>
                        <strong class="ms-1 timer-display" id="trip-time"
                               data-started="{{ $tripStartedAt ?? '' }}"
                               data-pause-started="{{ $pauseStartedAt ?? '' }}"
                               data-pause-type="{{ $pauseType ?? '' }}"
                               data-total-pause="{{ $totalPauseSeconds }}"
                               data-truck-status="{{ $truck->status }}">-</strong>
                    </div>
                    <div>
                        <span class="text-muted">Объём:</span>
                        <strong class="ms-1">{{ $currentTrip->load_volume ?? $truck->load_capacity ?? '-' }} т</strong>
                    </div>
                </div>
            </div>
        </div>
        @else
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info py-2 mb-0">
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
                        <button wire:click="assignRoute" wire:loading.attr="disabled" class="btn btn-success btn-lg w-100 mb-2">
                            <span wire:loading.remove>Получить маршрут</span>
                            <span wire:loading><i class="bi bi-spinner bi-spin"></i> Получение...</span>
                        </button>
                    @endif
                @endif

                {{-- Рейс завершён --}}
                @if($status === 'completed')
                    <div class="alert alert-success py-2 mb-2">
                        Рейс завершён. Запросите новый маршрут или уйдите в отстой.
                    </div>
                    <button wire:click="assignRoute" class="btn btn-primary btn-lg w-100 mb-2">
                        Запросить маршрут
                    </button>
                    <button wire:click="goToStandby" class="btn btn-outline-secondary w-100">
                        Уйти в отстой
                    </button>
                @endif

                {{-- В пути к забою --}}
                @if($status === 'to_miner')
                    <button wire:click="startLoading" class="btn btn-warning btn-lg w-100 mb-2">
                        Прибыл на погрузку
                    </button>
                    <div class="action-row">
                        <button wire:click="openDelayModal" class="btn btn-outline-secondary">
                            <i class="bi bi-clock"></i> Задержка
                        </button>
                        <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="btn btn-outline-danger">
                            <i class="bi bi-exclamation-triangle"></i> Поломка
                        </button>
                    </div>
                @endif

                {{-- На погрузке --}}
                @if($status === 'loading')
                    <div class="alert alert-warning py-3 text-center mb-2">
                        <strong>Ожидание завершения погрузки...</strong>
                    </div>
                    <div class="action-row">
                        <button wire:click="openDelayModal" class="btn btn-outline-secondary">
                            <i class="bi bi-clock"></i> Задержка
                        </button>
                        <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="btn btn-outline-danger">
                            <i class="bi bi-exclamation-triangle"></i> Поломка
                        </button>
                    </div>
                @endif

                {{-- Ожидание назначения зоны --}}
                @if($status === 'waiting_unloading')
                    <div class="alert alert-warning py-3 text-center mb-2">
                        <strong>Ожидание назначения зоны разгрузки</strong>
                    </div>
                    <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="btn btn-outline-danger w-100">
                        <i class="bi bi-exclamation-triangle"></i> Поломка
                    </button>
                @endif

                {{-- Везём груз --}}
                @if($status === 'transporting')
                    <button wire:click="startUnloading" class="btn btn-info btn-lg w-100 mb-2">
                        Прибыл на выгрузку
                    </button>
                    <div class="action-row">
                        <button wire:click="openDelayModal" class="btn btn-outline-secondary">
                            <i class="bi bi-clock"></i> Задержка
                        </button>
                        <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="btn btn-outline-danger">
                            <i class="bi bi-exclamation-triangle"></i> Поломка
                        </button>
                    </div>
                @endif

                {{-- Разгрузка --}}
                @if($status === 'unloading')
                    <button wire:click="completeTrip" class="btn btn-success btn-lg w-100 mb-2">
                        Завершить рейс
                    </button>
                    <button wire:click="openZoneModal" class="btn btn-outline-primary w-100">
                        Сменить зону
                    </button>
                @endif

                {{-- Задержка --}}
                @if($status === 'delayed')
                    <div class="alert alert-warning py-2 mb-2">
                        Маршрут приостановлен: {{ \App\Models\TripPause::typeLabel($pauseType ?? 'other') }}
                    </div>
                    <button wire:click="resumeFromDelay" class="btn btn-success btn-lg w-100 mb-2">
                        Задержка окончена
                    </button>
                    <button wire:click="reportBreakdown" wire:confirm="Сообщить о поломке?" class="btn btn-outline-danger w-100">
                        <i class="bi bi-exclamation-triangle"></i> Поломка
                    </button>
                @endif

                {{-- Поломка --}}
                @if($status === 'breakdown')
                    <div class="alert alert-danger py-2 mb-2">
                        Поломка. После ремонта выберите действие.
                    </div>
                    @if($currentTrip)
                        <button wire:click="resolveBreakdownContinue" class="btn btn-success btn-lg w-100 mb-2">
                            Продолжить рейс
                        </button>
                        <button wire:click="resolveBreakdownCancel" wire:confirm="Отменить рейс?" class="btn btn-outline-danger w-100">
                            Отменить рейс
                        </button>
                    @else
                        <button wire:click="resolveBreakdownContinue" class="btn btn-success btn-lg w-100">
                            Поломка устранена
                        </button>
                    @endif
                @endif

                {{-- Обслуживание (подкачка шин, обтяжка колёс) --}}
                @if($status === 'service')
                    <div class="alert alert-warning py-3 text-center mb-2">
                        <strong>{{ $currentServiceTask['type'] ?? 'Обслуживание' }}</strong>
                        @if(!empty($currentServiceTask['post_name']))
                            <div class="mt-1">Пост: {{ $currentServiceTask['post_name'] }}</div>
                        @endif
                        @if(!empty($currentServiceTask['started_at']))
                            <div class="text-muted small">Начало: {{ $currentServiceTask['started_at'] }}</div>
                        @endif
                    </div>
                    <button wire:click="completeService" class="btn btn-success btn-lg w-100 mb-2">
                        <i class="bi bi-check-lg me-1"></i> Завершить обслуживание
                    </button>
                @endif

                {{-- Заправка --}}
                @if($status === 'fueling')
                    <div class="alert alert-info py-3 text-center mb-2">
                        <strong>Заправка</strong>
                        @if(!empty($currentServiceTask['post_name']))
                            <div class="mt-1">Пост: {{ $currentServiceTask['post_name'] }}</div>
                        @endif
                        @if(!empty($currentServiceTask['started_at']))
                            <div class="text-muted small">Начало: {{ $currentServiceTask['started_at'] }}</div>
                        @endif
                    </div>
                    <button wire:click="completeService" class="btn btn-success btn-lg w-100 mb-2">
                        <i class="bi bi-check-lg me-1"></i> Завершить заправку
                    </button>
                @endif

                {{-- Техническое обслуживание (ТО) --}}
                @if($status === 'maintenance')
                    <div class="alert alert-warning py-3 text-center mb-2">
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
                    <button wire:click="completeService" class="btn btn-success btn-lg w-100 mb-2">
                        <i class="bi bi-check-lg me-1"></i> Завершить ТО
                    </button>
                @endif
            </div>
        </div>

        <!-- Запланированные ТО и заправка на смену -->
        @if(count($plannedShiftServices) > 0)
        <div class="row mb-3">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark py-2">
                        <i class="bi bi-calendar-check me-2"></i>
                        <strong>Запланировано на смену</strong>
                    </div>
                    <div class="card-body py-2">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Тип обслуживания</th>
                                        <th>Позиция в очереди</th>
                                        <th>Прогноз времени</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($plannedShiftServices as $task)
                                        <tr class="{{ $task['started'] ? 'table-success' : '' }}">
                                            <td>
                                                <strong>{{ $task['type'] }}</strong>
                                            </td>
                                            <td>
                                                @if($task['started'])
                                                    <span class="badge bg-success">В работе</span>
                                                @elseif($task['queue_position'] > 0)
                                                    <span class="badge bg-secondary">{{ $task['queue_position'] }}</span>
                                                @else
                                                    <span class="badge bg-info">Ожидает</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($task['forecast'])
                                                    <span class="text-muted">{{ $task['forecast'] }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($task['started'])
                                                    <span class="text-success"><i class="bi bi-check-circle"></i> Выполняется</span>
                                                @else
                                                    <span class="text-warning"><i class="bi bi-clock"></i> В очереди</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Статистика (скрыта по умолчанию) -->
        <div class="row">
            <div class="col-12">
                <div class="accordion" id="statsAccordion" style="background-color: #f8f9fa; border: 1px solid #dee2e6;">
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#statsCollapse">
                                <i class="bi bi-bar-chart me-2"></i>
                                Статистика за смену ({{ $stats['shift_name'] ?? '-' }})
                            </button>
                        </h2>
                        <div id="statsCollapse" class="accordion-collapse collapse" data-bs-parent="#statsAccordion">
                            <div class="accordion-body py-2">
                                <div class="d-flex flex-wrap" style="gap: 0.5rem 2rem;">
                                    <div>
                                        <span class="text-muted">Рейсов:</span>
                                        <strong class="ms-1">{{ $stats['today_trips'] ?? 0 }}</strong>
                                    </div>
                                    <div>
                                        <span class="text-muted">Объём:</span>
                                        <strong class="ms-1">{{ number_format($stats['today_volume'] ?? 0, 1) }} т</strong>
                                    </div>
                                    <div>
                                        <span class="text-muted">Ср. скорость:</span>
                                        <strong class="ms-1">{{ $stats['avg_speed'] ?? '-' }} @if($stats['avg_speed']) км/ч @endif</strong>
                                    </div>
                                    <div>
                                        <span class="text-muted">Всего рейсов:</span>
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
                <div class="accordion" id="serviceAccordion" style="background-color: #f8f9fa; border: 1px solid #dee2e6;">
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#serviceCollapse">
                                <i class="bi bi-tools me-2"></i>
                                Обслуживание
                                @if(count($pendingServiceTasks) > 0)
                                    <span class="badge bg-warning text-dark ms-2">{{ count($pendingServiceTasks) }}</span>
                                @endif
                            </button>
                        </h2>
                        <div id="serviceCollapse" class="accordion-collapse collapse" data-bs-parent="#serviceAccordion">
                            <div class="accordion-body py-2">
                                <!-- Показатели обслуживания -->
                                <div class="mb-3">
                                    <div class="d-flex flex-wrap" style="gap: 0.5rem 2rem;">
                                        <div>
                                            <span class="text-muted">Пробег с заправки:</span>
                                            <strong class="ms-1 {{ $serviceStats['mileage_since_fuel'] >= $serviceStats['fueling_threshold'] ? 'text-danger' : '' }}">
                                                {{ $serviceStats['mileage_since_fuel'] }} / {{ $serviceStats['fueling_threshold'] }} км
                                            </strong>
                                        </div>
                                        <div>
                                            <span class="text-muted">Мото-часы с ТО:</span>
                                            <strong class="ms-1">
                                                {{ $serviceStats['moto_hours_since_to'] }} ч
                                            </strong>
                                            <small class="text-muted">(след. {{ $serviceStats['next_to_type'] }})</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Запланированные задачи -->
                                @if(count($pendingServiceTasks) > 0)
                                    <div class="mb-3">
                                        <h6 class="text-muted mb-2">Запланировано:</h6>
                                        @foreach($pendingServiceTasks as $task)
                                            <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-1">
                                                <div>
                                                    <strong>{{ $task['type'] }}</strong>
                                                    @if($task['post_name'])
                                                        <span class="badge bg-primary ms-1">{{ $task['post_name'] }}</span>
                                                    @elseif($task['queue_position'])
                                                        <span class="badge bg-secondary ms-1">Очередь: {{ $task['queue_position'] }}</span>
                                                    @endif
                                                    @if($task['started_at'])
                                                        <span class="badge bg-success ms-1">Начато: {{ $task['started_at'] }}</span>
                                                    @endif
                                                </div>
                                                @if(!$task['started_at'])
                                                    <button wire:click="cancelServiceTask({{ $task['id'] }})"
                                                            wire:confirm="Отменить заявку?"
                                                            class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <!-- Кнопки запроса обслуживания -->
                                <div class="row g-2">
                                    <div class="col-6">
                                        <button wire:click="requestTireInflation"
                                                class="btn btn-outline-primary w-100"
                                                @if(in_array($truck->status, ['loading', 'unloading', 'maintenance', 'fueling'])) disabled @endif>
                                            <i class="bi bi-disc me-1"></i>
                                            Подкачка шин
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button wire:click="requestWheelTightening"
                                                class="btn btn-outline-primary w-100"
                                                @if(in_array($truck->status, ['loading', 'unloading', 'maintenance', 'fueling'])) disabled @endif>
                                            <i class="bi bi-nut me-1"></i>
                                            Обтяжка колёс
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-center mt-4 mb-4">
                    <a href="{{ route('order.index') }}" class="accordion-button collapsed py-2 px-3" style="text-decoration: none; background-color: #f8f9fa; border: 1px solid #dee2e6;">
                        <i class="bi bi-tools me-2"></i>
                        Заявки
                    </a>
                </div>                
            </div>
        </div>
        @else
        <div class="alert alert-info text-center py-4">
            Выберите грузовик для начала работы
        </div>
        @endif

        <!-- Модальные окна -->
        @if($showZoneModal)
        <div class="modal fade show d-block" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Выбор зоны разгрузки</h5>
                        <button type="button" class="btn-close" wire:click="closeZoneModal"></button>
                    </div>
                    <div class="modal-body">
                        @forelse($availableZones as $zone)
                            <div class="border rounded p-2 mb-2" style="cursor: pointer;" wire:click="selectZone({{ $zone['id'] }})">
                                <strong>{{ $zone['name'] }}</strong>
                                <small class="text-muted d-block">{{ $zone['dump_name'] }} | Свободно: {{ $zone['available_capacity'] }} м³</small>
                            </div>
                        @empty
                            <div class="alert alert-warning mb-0">Нет доступных зон</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($showDelayModal)
        <div class="modal fade show d-block" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Задержка</h5>
                        <button type="button" class="btn-close" wire:click="closeDelayModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Причина:</label>
                            <select class="form-select" wire:model="delayReason">
                                <option value="traffic">Пробки</option>
                                <option value="road_works">Дорожные работы</option>
                                <option value="waiting_loading">Ожидание погрузки</option>
                                <option value="waiting_unloading">Ожидание выгрузки</option>
                                <option value="weather">Погодные условия</option>
                                <option value="other">Другое</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ожидаемое время (мин):</label>
                            <input type="number" class="form-control" wire:model="delayMinutes" min="1" max="120">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeDelayModal">Отмена</button>
                        <button type="button" class="btn btn-warning" wire:click="confirmDelay">Подтвердить</button>
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