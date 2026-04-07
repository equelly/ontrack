<div class="excavator-panel-wrapper">
    <!-- Toast контейнер -->
    <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

    <div class="container py-4">
        <div class="row">
            <!-- Выбор экскаватора -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog"></i> Выбор экскаватора</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <select wire:model.live="selectedMinerId" class="form-control form-control-lg">
                                    <option value="">-- Выберите экскаватор --</option>
                                    @foreach($miners as $m)
                                        <option value="{{ $m->id }}">{{ $m->name_miner }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button
                                    wire:click="selectMiner"
                                    wire:loading.attr="disabled"
                                    class="btn btn-primary w-100">
                                    <span wire:loading.remove>Выбрать</span>
                                    <span wire:loading><i class="fas fa-spinner fa-spin"></i>...</span>
                                </button>
                            </div>
                        </div>

                        @if($miner)
                            <div class="mt-3">
                                <span class="text-muted">Текущий экскаватор:</span>
                                <span class="badge bg-primary" style="font-size: 1rem;">{{ $miner->name_miner }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Установка породы -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-layer-group"></i> Порода в забое</h5>
                    </div>
                    <div class="card-body">
                        @if($miner)
                            <div class="mb-3">
                                <span class="text-muted">Текущая порода:</span>
                                @if($miner->currentRock)
                                    <span class="badge bg-success" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                        {{ $miner->currentRock->name_rock }}
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                        Не выбрана
                                    </span>
                                @endif
                            </div>

                            <hr>

                            @if($rocks->count() > 0)
                                <div class="row">
                                    <div class="col-md-8">
                                        <select wire:model.live="selectedRockId" class="form-control">
                                            <option value="">-- Выберите породу --</option>
                                            @foreach($rocks as $rock)
                                                <option value="{{ $rock->id }}" {{ $rock->id == $selectedRockId ? 'selected' : '' }}>
                                                    {{ $rock->name_rock }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Выберите добываемую породу</small>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button
                                            wire:click="setRock"
                                            wire:loading.attr="disabled"
                                            class="btn btn-success w-100">
                                            <span wire:loading.remove>Установить</span>
                                            <span wire:loading><i class="fas fa-spinner fa-spin"></i>...</span>
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-warning mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Нет пород в системе!</strong><br>
                                    Обратитесь к администратору для добавления пород.
                                </div>
                            @endif
                        @else
                            <div class="text-center text-muted py-3">
                                Сначала выберите экскаватор
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($miner)
        <!-- Управление статусом забоя -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cogs"></i> Статус забоя</h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <span class="text-muted me-2">Текущий статус:</span>
                                    <span class="badge bg-{{ $miner->getStatusClass() }}" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                        {{ $miner->getStatusLabel() }}
                                    </span>
                                </div>
                                @if($miner->isDelayed() && $miner->status_changed_at)
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        Время в статусе: {{ $miner->getStatusDurationMinutes() }} мин.
                                    </small>
                                @endif
                            </div>
                            <div class="col-md-8">
                                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                                    @if($miner->status !== 'active')
                                        <button
                                            wire:click="setStatus('active')"
                                            wire:loading.attr="disabled"
                                            class="btn btn-success">
                                            <i class="fas fa-play me-1"></i> В работе
                                        </button>
                                    @endif

                                    @if($miner->status !== 'breakdown')
                                        <button
                                            wire:click="setStatus('breakdown')"
                                            wire:loading.attr="disabled"
                                            class="btn btn-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i> Поломка
                                        </button>
                                    @endif

                                    @if($miner->status !== 'maintenance')
                                        <button
                                            wire:click="setStatus('maintenance')"
                                            wire:loading.attr="disabled"
                                            class="btn btn-warning">
                                            <i class="fas fa-wrench me-1"></i> Обслуживание
                                        </button>
                                    @endif

                                    @if($miner->status !== 'dismantling')
                                        <button
                                            wire:click="setStatus('dismantling')"
                                            wire:loading.attr="disabled"
                                            class="btn btn-info">
                                            <i class="fas fa-hammer me-1"></i> Разбор забоя
                                        </button>
                                    @endif

                                    @if($miner->status !== 'access_setup')
                                        <button
                                            wire:click="setStatus('access_setup')"
                                            wire:loading.attr="disabled"
                                            class="btn btn-secondary">
                                            <i class="fas fa-road me-1"></i> Устр. подъезда
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($miner->isBreakdown())
                            <div class="alert alert-danger mt-3 mb-0">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Внимание!</strong> Грузовики будут перенаправлены на другие забои.
                            </div>
                        @elseif($miner->isPlannedDelay())
                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Грузовики в пути доедут до забоя, новые назначаться не будут.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($miner)
        <div class="row">
            <!-- Статистика за смену -->
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Статистика за смену</h5>
                        <span class="badge bg-info">{{ $stats['shift_name'] ?? '' }} (с {{ $stats['shift_start'] ?? '' }})</span>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div style="font-size: 2rem; font-weight: bold; color: #007bff;">
                                    {{ $stats['trips_count'] ?? 0 }}
                                </div>
                                <div class="text-muted">Рейсов за смену</div>
                            </div>
                            <div class="col-md-4">
                                <div style="font-size: 2rem; font-weight: bold; color: #28a745;">
                                    {{ $stats['total_volume'] ?? 0 }}
                                </div>
                                <div class="text-muted">Тонн добыто</div>
                            </div>
                            <div class="col-md-4">
                                <div style="font-size: 2rem; font-weight: bold; color: #ffc107;">
                                    {{ $stats['avg_loading_time'] ?? 0 }}
                                </div>
                                <div class="text-muted">Мин. среднее время погрузки</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Самосвалы у забоя -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-truck"></i> Самосвалы в направлении забоя</h5>
                        <button
                            wire:click="loadMinerData"
                            class="btn btn-sm btn-outline-primary"
                            title="Обновить">
                            <i class="fas fa-sync-alt" wire:loading.class="fa-spin"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        @if($trucks->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Номер</th>
                                            <th>Статус</th>
                                            <th>Грузоподъёмность</th>
                                            <th>Дамп / Порода</th>
                                            <th>Зона</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                       @foreach($trucks as $truck)
                                        @php
                                            $trip = $truck->trips->first();
                                            $statusLabels = [
                                                'to_miner' => ['label' => 'Едет к забою', 'class' => 'bg-info'],
                                                'loading' => ['label' => 'На погрузке', 'class' => 'bg-warning'],
                                                'waiting_loading' => ['label' => 'Ожидает', 'class' => 'bg-secondary']
                                            ];
                                            $status = $statusLabels[$truck->status] ?? ['label' => $truck->status, 'class' => 'bg-secondary'];
                                        @endphp
                                        <tr>
                                            <td><strong>{{ $truck->number }}</strong></td>
                                            <td>
                                                <span class="badge {{ $status['class'] }}" style="min-width: 100px;">
                                                    {{ $status['label'] }}
                                                </span>
                                            </td>
                                            <td>{{ $truck->load_capacity }} т</td>
                                            <td>
                                                {{ $trip?->miningOrder?->dump?->name_dump ?? '-' }}
                                                @if($trip?->miningOrder?->rock)
                                                    <span class="badge bg-info ms-1">{{ $trip->miningOrder->rock->name_rock }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $trip?->miningOrder?->zone?->name_zone ?? '-' }}</td>
                                            <td>
                                                @if($truck->status === 'loading')
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input
                                                            type="number"
                                                            wire:model="volumes.{{ $truck->id }}"
                                                            class="form-control form-control-sm"
                                                            min="0" step="0.1"
                                                            style="width: 80px;">
                                                        <span>т</span>
                                                        <button
                                                            wire:click="completeLoading({{ $truck->id }})"
                                                            wire:loading.attr="disabled"
                                                            class="btn btn-sm btn-success">
                                                            <span wire:loading.remove><i class="fas fa-check"></i> Загружен</span>
                                                            <span wire:loading><i class="fas fa-spinner fa-spin"></i></span>
                                                        </button>
                                                    </div>
                                                @elseif($truck->status === 'to_miner')
                                                    <button
                                                        wire:click="confirmArrival({{ $truck->id }})"
                                                        wire:loading.attr="disabled"
                                                        class="btn btn-sm btn-primary">
                                                        <span wire:loading.remove><i class="fas fa-truck-loading"></i> Прибыл</span>
                                                        <span wire:loading><i class="fas fa-spinner fa-spin"></i></span>
                                                    </button>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-truck" style="font-size: 3rem;"></i>
                                <p class="mt-3">Нет самосвалов в направлении забоя</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <script>
        // Устанавливаем ID экскаватора для Echo
        @if($miner)
        window.currentMinerId = {{ $miner->id }};
        @endif

        // Функция подключения к каналу
        let currentChannel = null;

        function subscribeToMinerChannel(minerId) {
            if (!window.Echo) {
                console.warn('Echo not initialized');
                return;
            }

            // Отписываемся от старого канала
            if (currentChannel) {
                console.log('Leaving old channel');
                window.Echo.leave(currentChannel);
            }

            console.log('🚜 Подключение к каналу miner.' + minerId);
            currentChannel = 'private-miner.' + minerId;

            window.Echo.private('miner.' + minerId)
                .listen('.loading.started', (e) => {
                    console.log('🚛 Событие loading.started:', e);

                    // Показываем уведомление
                    const container = document.getElementById('toast-container');
                    const toast = document.createElement('div');
                    toast.className = 'alert alert-info alert-dismissible fade show';
                    toast.innerHTML = `
                        <strong>🚛 ${e.message || 'Самосвал прибыл на погрузку'}</strong>
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                    `;
                    container.appendChild(toast);

                    // Обновляем Livewire компонент
                    const component = Livewire.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'));
                    if (component) {
                        component.call('loadMinerData');
                    }

                    setTimeout(() => {
                        toast.classList.remove('show');
                        setTimeout(() => toast.remove(), 300);
                    }, 10000);
                });
        }

        // Слушаем события Livewire
        document.addEventListener('DOMContentLoaded', () => {
            // Уведомления
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
                    }, 10000);
                });

                // Установка cookie
                Livewire.on('set-cookie', (data) => {
                    const event = Array.isArray(data) ? data[0] : data;
                    if (!event || !event.name) return;

                    const date = new Date();
                    date.setTime(date.getTime() + (event.days * 24 * 60 * 60 * 1000));
                    document.cookie = `${event.name}=${event.value};expires=${date.toUTCString()};path=/`;
                });

                // Переподключение к каналу при выборе экскаватора
                Livewire.on('miner-selected', (data) => {
                    const event = Array.isArray(data) ? data[0] : data;
                    if (!event || !event.miner_id) return;
                    console.log('📍 Miner selected event, subscribing to channel', event.miner_id);
                    subscribeToMinerChannel(event.miner_id);
                });
            }

            // Начальное подключение к каналу
            @if($miner)
            subscribeToMinerChannel({{ $miner->id }});
            @endif
        });
    </script>
</div>
