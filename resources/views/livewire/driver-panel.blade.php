<div class="driver-panel-wrapper">
    <style>
        .bi-spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>

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

        <div class="row">
            <!-- Текущий маршрут + Управление -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <!-- Заголовок со статусом -->
                    <div class="card-header bg-{{ $statusColor }} text-white d-flex justify-content-between align-items-center"
                         data-truck-status="{{ $truck->status }}">
                        <h5 class="mb-0">📍 Текущий маршрут</h5>
                        <span class="badge bg-white text-dark fs-6">{{ $statusLabel }}</span>
                    </div>

                    <div class="card-body">
                        @if($currentTrip)
                            <!-- Информация о маршруте -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Откуда (Забой)</small>
                                        <strong>{{ $currentTrip->miner->name_miner ?? 'Забой #' . $currentTrip->miner_id }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Куда (Дамп)</small>
                                        <strong>{{ $currentTrip->dump->name_dump ?? 'Дамп #' . $currentTrip->dump_id }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-2 @if($currentTrip->zone) border-success @endif">
                                        <small class="text-muted d-block">Зона разгрузки</small>
                                        @if($currentTrip->zone)
                                            <strong class="text-success">{{ $currentTrip->zone->name_zone }}</strong>
                                        @else
                                            <span class="text-warning">Не назначена</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-2 border-info">
                                        <small class="text-muted d-block">Порода</small>
                                        @php
                                            $isLoaded = in_array($truck->status, ['transporting', 'unloading']);
                                            if ($isLoaded && $currentTrip->rock_id) {
                                                $rock = $currentTrip->rock;
                                            } else {
                                                $rock = $currentTrip->miningOrder?->rock;
                                            }
                                        @endphp
                                        @if($rock)
                                            <strong class="text-info">{{ $rock->name_rock }}</strong>
                                            @if($isLoaded)
                                                <small class="text-success d-block">✓ Загружена</small>
                                            @else
                                                <small class="text-muted d-block">По маршруту</small>
                                            @endif
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
                                    <small class="text-muted">Объём</small>
                                    <p class="mb-0 fw-bold">{{ $truck->load_capacity ?? '-' }} м³</p>
                                </div>
                                <div class="col-3">
                                    <small class="text-muted">Начало</small>
                                    <p class="mb-0 fw-bold">{{ $currentTrip->started_at?->format('H:i') ?? '-' }}</p>
                                </div>
                                <div class="col-3">
                                    <small class="text-muted">Время</small>
                                    <p class="mb-0 fw-bold" id="trip-time"
                                       data-started="{{ $tripStartedAt ?? '' }}"
                                       data-pause-started="{{ $pauseStartedAt ?? '' }}"
                                       data-pause-type="{{ $pauseType ?? '' }}"
                                       data-total-pause="{{ $totalPauseSeconds }}">-</p>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-info mb-4">
                                <i class="bi bi-info-circle me-2"></i> Нет активного маршрута
                            </div>
                        @endif

                        <hr>

                        <!-- Управление статусами -->
                        <div id="status-container">
                            @php
                                $status = $truck->status;
                            @endphp

                            {{-- Свободен - может запросить маршрут --}}
                            @if($status === 'free')
                                @if(!$currentTrip)
                                    <button
                                        wire:click="assignRoute"
                                        wire:loading.attr="disabled"
                                        class="btn btn-success btn-lg w-100">
                                        <span wire:loading.remove><i class="bi bi-arrow-right-circle"></i> Получить маршрут</span>
                                        <span wire:loading><i class="bi bi-spinner bi-spin"></i> Получение...</span>
                                    </button>
                                @endif
                            @endif

                            {{-- Рейс завершён - ожидает назначения --}}
                            @if($status === 'completed')
                                <div class="alert alert-success mb-3">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <strong>Рейс завершён!</strong><br>
                                    <small>Ожидание назначения нового маршрута от диспетчера.</small>
                                </div>
                                <button
                                    wire:click="assignRoute"
                                    wire:loading.attr="disabled"
                                    class="btn btn-primary btn-lg w-100 mb-2">
                                    <span wire:loading.remove><i class="bi bi-arrow-right-circle"></i> Запросить маршрут</span>
                                    <span wire:loading><i class="bi bi-spinner bi-spin"></i> Получение...</span>
                                </button>
                                <button
                                    wire:click="goToStandby"
                                    class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-pause-circle"></i> Уйти в отстой
                                </button>
                            @endif

                            {{-- В пути к забою --}}
                            @if($status === 'to_miner')
                                <button
                                    wire:click="startLoading"
                                    class="btn btn-warning btn-lg w-100 mb-2">
                                    <i class="bi bi-hourglass-split"></i> Прибыл на погрузку
                                </button>
                                <button
                                    wire:click="openDelayModal"
                                    class="btn btn-outline-warning w-100">
                                    <i class="bi bi-clock"></i> Сообщить о задержке
                                </button>
                            @endif

                            {{-- На погрузке --}}
                            @if($status === 'loading')
                                <div class="alert alert-warning mb-0 text-center py-4">
                                    <i class="bi bi-hourglass-split fs-1 d-block mb-2"></i>
                                    <strong>⏳ Ожидание завершения погрузки...</strong><br>
                                    <small class="text-muted">Экскаваторщик сообщит когда можно отправляться</small>
                                </div>
                            @endif

                            {{-- Ожидание назначения зоны разгрузки --}}
                            @if($status === 'waiting_unloading')
                                <div class="alert alert-danger mb-3 text-center py-4">
                                    <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                                    <strong>⚠️ Ожидание назначения зоны разгрузки</strong><br>
                                    <small class="text-muted">Диспетчер назначит зону для выгрузки. Ожидайте.</small>
                                </div>
                                @if($currentTrip && $currentTrip->rock)
                                    <div class="alert alert-info mb-3">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Загруженная порода:</small><br>
                                                <strong class="text-info">{{ $currentTrip->rock->name_rock }}</strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Объём:</small><br>
                                                <strong>{{ $currentTrip->load_volume ?? $truck->load_capacity }} т</strong>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <button
                                    wire:click="reportBreakdown"
                                    wire:confirm="Сообщить о поломке?"
                                    class="btn btn-outline-danger w-100">
                                    <i class="bi bi-exclamation-triangle"></i> Поломка
                                </button>
                            @endif

                            {{-- Везём груз --}}
                            @if($status === 'transporting')
                                <button
                                    wire:click="startUnloading"
                                    class="btn btn-info btn-lg w-100 mb-2">
                                    <i class="bi bi-box-arrow-down"></i> Прибыл на выгрузку
                                </button>
                                <button
                                    wire:click="openDelayModal"
                                    class="btn btn-outline-warning w-100">
                                    <i class="bi bi-clock"></i> Сообщить о задержке
                                </button>
                            @endif

                            {{-- Разгрузка --}}
                            @if($status === 'unloading')
                                <button
                                    wire:click="completeTrip"
                                    class="btn btn-success btn-lg w-100 mb-2">
                                    <i class="bi bi-check-circle"></i> Завершить рейс
                                </button>
                                <button
                                    wire:click="openZoneModal"
                                    class="btn btn-outline-primary w-100">
                                    <i class="bi bi-arrow-repeat"></i> Сменить зону
                                </button>
                            @endif

                            {{-- Задержка --}}
                            @if($status === 'delayed')
                                @if($currentTrip)
                                    <div class="alert alert-warning mb-3">
                                        <i class="bi bi-clock-history me-2"></i>
                                        <strong>Маршрут приостановлен</strong><br>
                                        <small>Тип: {{ \App\Models\TripPause::typeLabel($pauseType ?? 'other') }}</small>
                                    </div>
                                @endif
                                <button
                                    wire:click="resumeFromDelay"
                                    class="btn btn-success btn-lg w-100 mb-2">
                                    <i class="bi bi-play-circle"></i> Задержка окончена
                                </button>
                                <button
                                    wire:click="reportBreakdown"
                                    wire:confirm="Сообщить о поломке?"
                                    class="btn btn-outline-danger w-100">
                                    <i class="bi bi-exclamation-triangle"></i> Поломка
                                </button>
                            @endif

                            {{-- Поломка --}}
                            @if($status === 'breakdown')
                                <div class="alert alert-danger mb-3">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Поломка</strong><br>
                                    <small>Время рейса остановлено. После ремонта выберите действие.</small>
                                </div>

                                @if($currentTrip)
                                    <button
                                        wire:click="resolveBreakdownContinue"
                                        class="btn btn-success btn-lg w-100 mb-2">
                                        <i class="bi bi-play-circle"></i> Продолжить рейс
                                    </button>
                                    <button
                                        wire:click="resolveBreakdownCancel"
                                        wire:confirm="Отменить текущий рейс?"
                                        class="btn btn-outline-danger w-100 mb-2">
                                        <i class="bi bi-x-circle"></i> Отменить рейс (серьёзная поломка)
                                    </button>
                                @else
                                    <button
                                        wire:click="resolveBreakdownContinue"
                                        class="btn btn-success btn-lg w-100">
                                        <i class="bi bi-check-circle"></i> Поломка устранена
                                    </button>
                                @endif
                            @endif

                            {{-- Кнопка поломки для рабочих статусов --}}
                            @if(in_array($status, ['to_miner', 'loading', 'transporting', 'unloading', 'waiting_unloading']))
                                <hr>
                                <button
                                    wire:click="reportBreakdown"
                                    wire:confirm="Сообщить о поломке?"
                                    class="btn btn-outline-danger w-100">
                                    <i class="bi bi-exclamation-triangle"></i> Поломка
                                </button>
                            @endif
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
                            <div class="col-12">
                                <div class="border rounded p-3">
                                    <h4 class="text-info mb-0">{{ number_format($stats['total_volume'] ?? 0, 1) }}</h4>
                                    <small class="text-muted">Объём м³</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно выбора зоны -->
        @if($showZoneModal)
        <div class="modal fade show d-block" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">🔄 Выберите зону разгрузки</h5>
                        <button type="button" class="btn-close" wire:click="closeZoneModal"></button>
                    </div>
                    <div class="modal-body">
                        @forelse($availableZones as $zone)
                            <div
                                class="border rounded p-3 mb-2"
                                style="cursor: pointer;"
                                wire:click="selectZone({{ $zone['id'] }})">
                                <strong>{{ $zone['name'] }}</strong>
                                <small class="text-muted d-block">{{ $zone['dump_name'] }}</small>
                                <small class="text-success">Свободно: {{ $zone['available_capacity'] }} м³</small>
                            </div>
                        @empty
                            <div class="alert alert-warning mb-0">Нет доступных зон</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Модальное окно задержки -->
        @if($showDelayModal)
        <div class="modal fade show d-block" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">⚠️ Укажите причину задержки</h5>
                        <button type="button" class="btn-close" wire:click="closeDelayModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Причина:</label>
                            <select class="form-select" wire:model="delayReason">
                                <option value="traffic">🚗 Пробки</option>
                                <option value="road_works">🚧 Дорожные работы</option>
                                <option value="waiting_loading">⏳ Ожидание погрузки</option>
                                <option value="waiting_unloading">⏳ Ожидание выгрузки</option>
                                <option value="weather">🌧️ Погодные условия</option>
                                <option value="other">❓ Другое</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ожидаемое время задержки (мин):</label>
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
        // Глобальная переменная для app.js
        window.truckId = {{ $truck->id }};

        // Таймер времени в пути
        let timerInterval = null;

        // Форматирование времени
        function formatTime(seconds, prefix = '') {
            if (seconds === null || seconds < 0) return '-';
            const hours = Math.floor(seconds / 3600);
            const min = Math.floor((seconds % 3600) / 60);
            const sec = seconds % 60;
            if (hours > 0) {
                return prefix + hours + ' ч ' + min + ' мин ' + sec + ' сек';
            }
            return prefix + min + ' мин ' + sec + ' сек';
        }

        // Получить текущий статус
        function getCurrentStatus() {
            const el = document.querySelector('[data-truck-status]');
            return el ? el.getAttribute('data-truck-status') : 'free';
        }

        // Вычислить чистое время в пути (без пауз)
        function calculateTripSeconds() {
            const el = document.getElementById('trip-time');
            if (!el) return null;

            const startedAtStr = el.getAttribute('data-started');
            if (!startedAtStr) return null;

            const startedAt = new Date(startedAtStr);
            if (isNaN(startedAt.getTime())) return null;

            const now = new Date();
            let totalSeconds = Math.floor((now - startedAt) / 1000);

            // Вычитаем время завершённых пауз
            const totalPause = parseInt(el.getAttribute('data-total-pause') || '0', 10);
            totalSeconds -= totalPause;

            // Вычитаем время текущей активной паузы
            const pauseStartedStr = el.getAttribute('data-pause-started');
            if (pauseStartedStr) {
                const pauseStarted = new Date(pauseStartedStr);
                if (!isNaN(pauseStarted.getTime())) {
                    const currentPauseSeconds = Math.floor((now - pauseStarted) / 1000);
                    totalSeconds -= currentPauseSeconds;
                }
            }

            return totalSeconds;
        }

        // Вычислить "замороженное" время на момент начала паузы
        function calculateFrozenSeconds() {
            const el = document.getElementById('trip-time');
            if (!el) return null;

            const startedAtStr = el.getAttribute('data-started');
            const pauseStartedStr = el.getAttribute('data-pause-started');

            if (!startedAtStr || !pauseStartedStr) return null;

            const startedAt = new Date(startedAtStr);
            const pauseStarted = new Date(pauseStartedStr);

            if (isNaN(startedAt.getTime()) || isNaN(pauseStarted.getTime())) return null;

            // Время до паузы
            let frozenSeconds = Math.floor((pauseStarted - startedAt) / 1000);

            // Вычитаем завершённые паузы
            const totalPause = parseInt(el.getAttribute('data-total-pause') || '0', 10);
            frozenSeconds -= totalPause;

            return frozenSeconds;
        }

        // Обновление таймера
        function updateTimer() {
            const el = document.getElementById('trip-time');
            if (!el) return;

            const status = getCurrentStatus();
            const pauseType = el.getAttribute('data-pause-type');
            let seconds;
            let prefix = '';

            // Статусы с активной паузой - показываем замороженное время
            if (status === 'breakdown' || status === 'delayed') {
                seconds = calculateFrozenSeconds();

                // Иконка по типу паузы
                if (pauseType === 'breakdown') {
                    prefix = '🔧 ';
                } else {
                    prefix = '⏸ ';
                }
            } else if (status === 'free') {
                el.innerText = '-';
                return;
            } else {
                // Рабочие статусы - показываем чистое время
                seconds = calculateTripSeconds();
            }

            el.innerText = formatTime(seconds, prefix);
        }

        // Запуск таймера
        function startTimer() {
            const el = document.getElementById('trip-time');
            if (!el) return;

            console.log('startTimer:', {
                status: getCurrentStatus(),
                started: el.getAttribute('data-started'),
                pauseStarted: el.getAttribute('data-pause-started'),
                pauseType: el.getAttribute('data-pause-type'),
                totalPause: el.getAttribute('data-total-pause'),
            });

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

        document.addEventListener('DOMContentLoaded', startTimer);

        // Слушаем событие перезапуска таймера от Livewire
        document.addEventListener('livewire:init', () => {
            Livewire.on('restart-timer', () => {
                setTimeout(startTimer, 50);
            });
        });

        // Уведомления обрабатываются глобально в layout (showNotification)
    </script>
</div>
