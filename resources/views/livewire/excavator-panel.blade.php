<div class="excavator-panel-wrapper">
    <style>
        .bi-spin { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .stat-value { font-size: 1.5rem; font-weight: bold; }
        .stat-label { font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>

    <div class="container-fluid py-3 bg-gray-100">
        <!-- Строка 1: Выбор экскаватора + Порода -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex flex-column gap-2">
                    <!-- Экскаватор -->
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="text-muted text-nowrap">Экскаватор:</span>
                        <select wire:model.live="selectedMinerId" class="form-select form-select-sm flex-grow-1" style="min-width: 120px; max-width: 200px;">
                            <option value="">-- Выберите --</option>
                            @foreach($miners as $m)
                                <option value="{{ $m->id }}">{{ $m->name_miner }}</option>
                            @endforeach
                        </select>
                        <button wire:click="selectMiner" wire:loading.attr="disabled" class="btn btn-primary btn-sm">
                            <span wire:loading.remove>OK</span>
                            <span wire:loading><i class="bi bi-spinner bi-spin"></i></span>
                        </button>
                    </div>

                    @if($miner)
                    <!-- Порода -->
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="text-muted text-nowrap">Порода:</span>
                        @if($miner->currentRock)
                            <span class="badge bg-success">{{ $miner->currentRock->name_rock }}</span>
                        @else
                            <span class="badge bg-warning text-dark">Не выбрана</span>
                        @endif
                        <select wire:model.live="selectedRockId" class="form-select form-select-sm" style="min-width: 100px; max-width: 150px;">
                            <option value="">-- Сменить --</option>
                            @foreach($rocks as $rock)
                                <option value="{{ $rock->id }}">{{ $rock->name_rock }}</option>
                            @endforeach
                        </select>
                        <button wire:click="setRock" wire:loading.attr="disabled" class="btn btn-success btn-sm">
                            <span wire:loading.remove>OK</span>
                            <span wire:loading><i class="bi bi-spinner bi-spin"></i></span>
                        </button>
                    </div>

                    <!-- Норма погрузки -->
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="text-muted text-nowrap">Норма:</span>
                        <input type="number" wire:model.live="targetLoadTime" class="form-control form-control-sm" style="width: 60px;" min="1" max="60">
                        <span class="text-muted">мин</span>
                        <button wire:click="setTargetLoadTime" wire:loading.attr="disabled" class="btn btn-outline-primary btn-sm">
                            <span wire:loading.remove>OK</span>
                            <span wire:loading><i class="bi bi-spinner bi-spin"></i></span>
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        @if($miner)
        <!-- Строка 2: Статус забоя + Кнопки -->
        <div class="row mb-3">
            <div class="col-12">
                <!-- Текущий статус -->
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="text-muted">Статус:</span>
                    @php
                        $statusColors = [
                            'active' => '#198754',
                            'breakdown' => '#dc3545',
                            'maintenance' => '#ffc107',
                            'dismantling' => '#6c757d',
                            'access_setup' => '#6c757d',
                        ];
                        $statusTextColor = $miner->status === 'maintenance' ? '#212529' : 'white';
                    @endphp
                    <button disabled class="btn btn-sm" style="background-color: {{ $statusColors[$miner->status] ?? '#6c757d' }}; border-color: {{ $statusColors[$miner->status] ?? '#6c757d' }}; color: {{ $statusTextColor }}; opacity: 1; min-width: 120px;">
                        {{ $miner->getStatusLabel() }}
                    </button>
                    @if($miner->isDelayed() && $miner->status_changed_at)
                        <small class="text-muted">({{ $miner->getStatusDurationMinutes() }} мин)</small>
                    @endif
                </div>

                <!-- Кнопки смены статуса -->
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @if($miner->status !== 'active')
                        <button wire:click="setStatus('active')" wire:loading.attr="disabled" 
                                class="btn btn-sm" style="background-color: #198754; border-color: #198754; color: white; min-width: 130px;">
                            В работе
                        </button>
                    @endif
                    
                    @if($miner->status !== 'breakdown')
                        <button wire:click="setStatus('breakdown')" wire:loading.attr="disabled" 
                                class="btn btn-sm" style="background-color: #dc3545; border-color: #dc3545; color: white; min-width: 130px;">
                            Поломка
                        </button>
                    @endif
                    
                    @if($miner->status !== 'maintenance')
                        <button wire:click="setStatus('maintenance')" wire:loading.attr="disabled" 
                                class="btn btn-sm" style="background-color: #ffc107; border-color: #ffc107; color: #212529; min-width: 130px;">
                            Обслуживание
                        </button>
                    @endif
                    
                    @if($miner->status !== 'dismantling')
                        <button wire:click="setStatus('dismantling')" wire:loading.attr="disabled" 
                                class="btn btn-sm" style="background-color: #6c757d; border-color: #6c757d; color: white; min-width: 130px;">
                            Разбор забоя
                        </button>
                    @endif
                    
                    @if($miner->status !== 'access_setup')
                        <button wire:click="setStatus('access_setup')" wire:loading.attr="disabled" 
                                class="btn btn-sm" style="background-color: #6c757d; border-color: #6c757d; color: white; min-width: 130px;">
                            Устр. подъезда
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- ГЛАВНОЕ: Самосвалы у забоя -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h5 class="mb-0 text-uppercase" style="letter-spacing: 1px;">
                        Самосвалы в направлении забоя
                    </h5>
                    <button wire:click="loadMinerData" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise" wire:loading.class="bi-spin"></i>
                    </button>
                </div>

                @if($trucks->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px;">Номер</th>
                                <th style="width: 180px;">Действия</th>
                                <th style="width: 70px;">Груз.</th>
                                <th>Перегрузка / Зона</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trucks as $truck)
                            @php
                                $trip = $truck->trips->first();
                            @endphp
                            <tr class="{{ $truck->status === 'loading' ? 'table-warning' : '' }}">
                                <td><strong>{{ $truck->number }}</strong></td>
                                <td>
                                    @if($truck->status === 'loading')
                                        <div class="d-flex align-items-center gap-1">
                                            <input type="number" wire:model="volumes.{{ $truck->id }}"
                                                   class="form-control form-control-sm" style="width: 55px;" min="0" step="0.1">
                                            <span class="text-muted small">т</span>
                                            <button wire:click="completeLoading({{ $truck->id }})" wire:loading.attr="disabled"
                                                    class="btn btn-success btn-sm">
                                                <span wire:loading.remove>Загружен</span>
                                                <span wire:loading><i class="bi bi-spinner bi-spin"></i></span>
                                            </button>
                                        </div>
                                    @elseif($truck->status === 'to_miner')
                                        <button wire:click="confirmArrival({{ $truck->id }})" wire:loading.attr="disabled"
                                                class="btn btn-primary btn-sm w-100">
                                            <span wire:loading.remove><i class="bi bi-truck"></i> Прибыл</span>
                                            <span wire:loading><i class="bi bi-spinner bi-spin"></i></span>
                                        </button>
                                    @elseif($truck->status === 'waiting_loading')
                                        <button wire:click="confirmArrival({{ $truck->id }})" wire:loading.attr="disabled"
                                                class="btn btn-warning btn-sm w-100">
                                            <span wire:loading.remove>Начать погрузку</span>
                                            <span wire:loading><i class="bi bi-spinner bi-spin"></i></span>
                                        </button>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><small>{{ $truck->load_capacity }} т</small></td>
                                <td>
                                    @if($trip)
                                        <small>
                                            {{ $trip->dump?->name_dump ?? $trip->miningOrder?->dump?->name_dump ?? '-' }}
                                            @if($trip->zone)
                                                / <span class="text-success fw-bold">{{ $trip->zone->name_zone }}</span>
                                            @elseif($trip->miningOrder?->zone)
                                                / <span class="text-success">{{ $trip->miningOrder->zone->name_zone }}</span>
                                            @else
                                                / <span class="text-warning">Не назначена</span>
                                            @endif
                                        </small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="alert alert-info py-3 text-center mb-0">
                    <i class="bi bi-truck fs-3 d-block mb-2"></i>
                    Нет самосвалов в направлении забоя
                </div>
                @endif
            </div>
        </div>

        <!-- Показатели производительности (кратко) -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div>
                        <span class="stat-label">У забоя</span>
                        <div class="stat-value text-primary">{{ $productivityStats['current_trucks'] ?? 0 }}</div>
                    </div>
                    <div>
                        <span class="stat-label">Ожидают</span>
                        <div class="stat-value text-warning">{{ $productivityStats['waiting_trucks'] ?? 0 }}</div>
                    </div>
                    <div>
                        <span class="stat-label">На погрузке</span>
                        <div class="stat-value text-success">{{ $productivityStats['loading_trucks'] ?? 0 }}</div>
                    </div>
                    <div class="vr"></div>
                    <div>
                        <span class="stat-label">Ср. погрузка</span>
                        <div class="stat-value {{ ($productivityStats['avg_load_time'] ?? 999) > ($productivityStats['target_load_time'] ?? 999) ? 'text-danger' : 'text-success' }}">
                            {{ $productivityStats['avg_load_time'] ?? '-' }}
                            <small class="text-muted fw-normal">мин</small>
                        </div>
                    </div>
                    <div>
                        <span class="stat-label">Ср. ожидание</span>
                        <div class="stat-value text-secondary">
                            {{ $productivityStats['avg_wait_time'] ?? '-' }}
                            <small class="text-muted fw-normal">мин</small>
                        </div>
                    </div>
                    @if($productivityStats['recommended_trucks'])
                    <div class="vr"></div>
                    <div>
                        <span class="stat-label">Рекомендуется</span>
                        <div class="stat-value text-info">{{ $productivityStats['recommended_trucks'] }}</div>
                    </div>
                    @php
                        $balanceLabels = [
                            'underloaded' => ['label' => 'Недогружен', 'class' => 'warning'],
                            'balanced' => ['label' => 'Оптимально', 'class' => 'success'],
                            'overloaded' => ['label' => 'Перегружен', 'class' => 'danger'],
                        ];
                        $balance = $productivityStats['balance'] ?? 'balanced';
                        $balanceInfo = $balanceLabels[$balance] ?? $balanceLabels['balanced'];
                    @endphp
                    <span class="badge bg-{{ $balanceInfo['class'] }}">{{ $balanceInfo['label'] }}</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Статистика за смену (скрыта по умолчанию) -->
        <div class="row">
            <div class="col-12">
                <div class="accordion" id="statsAccordion">
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 px-3" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#statsCollapse">
                                <i class="bi bi-bar-chart me-2"></i>
                                Статистика за смену ({{ $stats['shift_name'] ?? '-' }})
                            </button>
                        </h2>
                        <div id="statsCollapse" class="accordion-collapse collapse" data-bs-parent="#statsAccordion">
                            <div class="accordion-body py-2">
                                <div class="d-flex flex-wrap" style="gap: 0.5rem 2rem;">
                                    <div>
                                        <span class="text-muted">Рейсов:</span>
                                        <strong class="ms-1">{{ $stats['trips_count'] ?? 0 }}</strong>
                                    </div>
                                    <div>
                                        <span class="text-muted">Добыто:</span>
                                        <strong class="ms-1">{{ number_format($stats['total_volume'] ?? 0, 1) }} т</strong>
                                    </div>
                                    <div>
                                        <span class="text-muted">Ср. время погрузки:</span>
                                        <strong class="ms-1">{{ $stats['avg_loading_time'] ?? '-' }} мин</strong>
                                    </div>
                                    <div>
                                        <span class="text-muted">Начало смены:</span>
                                        <span class="ms-1">{{ $stats['shift_start'] ?? '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($miner->isBreakdown())
        <div class="alert alert-danger mt-3 mb-0 py-2">
            <i class="bi bi-exclamation-circle me-2"></i>
            <strong>Внимание!</strong> Грузовики будут перенаправлены на другие забои.
        </div>
        @elseif($miner->isPlannedDelay())
        <div class="alert alert-warning mt-3 mb-0 py-2">
            <i class="bi bi-info-circle me-2"></i>
            Грузовики в пути доедут до забоя, новые назначаться не будут.
        </div>
        @endif

        @else
        <div class="alert alert-info text-center py-4">
            Выберите экскаватор для начала работы
        </div>
        @endif
    </div>

    <script>
        @if($miner)
        window.currentMinerId = {{ $miner->id }};
        @endif

        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Livewire !== 'undefined') {
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
                    }, 10000);
                });

                Livewire.on('set-cookie', (data) => {
                    const event = Array.isArray(data) ? data[0] : data;
                    if (!event || !event.name) return;

                    const date = new Date();
                    date.setTime(date.getTime() + (event.days * 24 * 60 * 60 * 1000));
                    document.cookie = `${event.name}=${event.value};expires=${date.toUTCString()};path=/`;
                });
            }
        });

        // =========================================
        // Echo подписка на канал экскаватора
        // =========================================
        let currentMinerChannel = null;

        function subscribeToMinerChannel(minerId) {
            if (!minerId || !window.Echo) return;

            // Отписываемся от старого канала
            if (currentMinerChannel) {
                window.Echo.leave(`private-miner.${currentMinerChannel}`);
                currentMinerChannel = null;
            }

            console.log('🚜 Подписка на канал miner.' + minerId);
            currentMinerChannel = minerId;

            window.Echo.private(`miner.${minerId}`)
                .listen('.excavator.notification', (data) => {
                    console.log('📢 excavator.notification:', data);
                    Livewire.dispatch('refresh-miner-data');
                })
                .listen('.loading.started', (data) => {
                    console.log('🚛 loading.started:', data);
                    Livewire.dispatch('refresh-miner-data');
                });
        }

        // Подписываемся при загрузке
        @if($miner)
        document.addEventListener('DOMContentLoaded', () => {
            subscribeToMinerChannel({{ $miner->id }});
        });
        @endif

        // Переподписываемся при выборе нового экскаватора
        document.addEventListener('livewire:init', () => {
            Livewire.on('miner-selected', (data) => {
                const event = Array.isArray(data) ? data[0] : data;
                if (event && event.miner_id) {
                    subscribeToMinerChannel(event.miner_id);
                }
            });
        });
    </script>
</div>