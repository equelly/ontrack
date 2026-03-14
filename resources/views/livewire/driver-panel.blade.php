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

    <!-- Toast контейнер -->
    <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

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
                    <div class="card-header bg-{{ $statusColor }} text-white d-flex justify-content-between align-items-center">
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
                                            // После загрузки - порода из trip, до загрузки - из забоя
                                            $rock = $currentTrip->rock ?? $currentTrip->miner?->rocks?->first();
                                        @endphp
                                        @if($rock)
                                            <strong class="text-info">{{ $rock->name_rock }}</strong>
                                            @if($currentTrip->rock_id)
                                                <small class="text-success d-block">✓ Загружена</small>
                                            @else
                                                <small class="text-muted d-block">Текущая в забое</small>
                                            @endif
                                        @else
                                            <span class="text-warning">Не определена</span>
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
                                    <p class="mb-0 fw-bold" id="trip-time" data-started="{{ $tripStartedAt ?? '' }}">-</p>
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

                            {{-- Свободен --}}
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

                            {{-- На погрузке - ждём экскаваторщика --}}
                            @if($status === 'loading')
                                <div class="alert alert-warning mb-0 text-center py-4">
                                    <i class="bi bi-hourglass-split fs-1 d-block mb-2"></i>
                                    <strong>⏳ Ожидание завершения погрузки...</strong><br>
                                    <small class="text-muted">Экскаваторщик сообщит когда можно отправляться</small>
                                </div>
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

                            {{-- Поломка --}}
                            @if($status === 'breakdown')
                                <button
                                    wire:click="reportBreakdownResolved"
                                    class="btn btn-success btn-lg w-100">
                                    <i class="bi bi-check-circle"></i> Поломка устранена
                                </button>
                            @endif

                            {{-- Кнопка поломки для рабочих статусов --}}
                            @if(in_array($status, ['to_miner', 'loading', 'transporting', 'unloading']))
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
                                <option value="traffic">Пробки</option>
                                <option value="road_works">Дорожные работы</option>
                                <option value="weather">Погодные условия</option>
                                <option value="technical">Техническая проблема</option>
                                <option value="other">Другое</option>
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

        function updateTimer() {
            const el = document.getElementById('trip-time');
            if (!el) return;

            const startedAtStr = el.getAttribute('data-started');
            if (!startedAtStr) {
                el.innerText = '-';
                return;
            }

            const startedAt = new Date(startedAtStr);
            if (isNaN(startedAt.getTime())) {
                el.innerText = '-';
                return;
            }

            const now = new Date();
            const diff = Math.floor((now - startedAt) / 1000);

            if (diff < 0) {
                el.innerText = '-';
                return;
            }

            const min = Math.floor(diff / 60);
            const sec = diff % 60;
            el.innerText = min + ' мин ' + sec + ' сек';
        }

        function startTimer() {
            if (timerInterval) {
                clearInterval(timerInterval);
            }
            updateTimer();
            timerInterval = setInterval(updateTimer, 1000);
        }

        document.addEventListener('DOMContentLoaded', startTimer);

        // Перезапуск при обновлении Livewire
        if (typeof Livewire !== 'undefined') {
            Livewire.hook('commit', () => {
                setTimeout(startTimer, 50);
            });
        }

        // Слушаем события Livewire
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Livewire !== 'undefined') {
                Livewire.on('notify', (data) => {
                    const event = Array.isArray(data) ? data[0] : data;
                    if (!event || !event.message) return;

                    const container = document.getElementById('toast-container');
                    const toast = document.createElement('div');

                    const bgClass = event.type === 'success' ? 'alert-success' :
                                   event.type === 'error' ? 'alert-danger' :
                                   'alert-warning';

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
        });
    </script>
</div>
