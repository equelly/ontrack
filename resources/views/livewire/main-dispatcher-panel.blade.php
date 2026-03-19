<div class="dispatcher-panel-wrapper">
    <!-- Toast контейнер -->
    <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

    <!-- Статистика -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>Свободных самосвалов</h6>
                    <h2>{{ $this->free_trucks_count }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6>В работе</h6>
                    <h2>{{ $this->working_trucks_count }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning">
                <div class="card-body">
                    <h6>Активных забоев</h6>
                    <h2>{{ $this->active_miners_count }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6>Поломок</h6>
                    <h2>{{ $this->breakdown_count }}</h2>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4" id="mainTabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#trucksTab" type="button">
                Самосвалы
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#minersTab" type="button">
                Забои
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#assignTab" type="button">
                Назначить маршрут
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#zonesTab" type="button">
                Зоны разгрузки
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Самосвалы -->
        <div class="tab-pane fade show active" id="trucksTab">
            <div class="d-flex justify-content-end mb-3">
                <button
                    wire:click="loadData"
                    wire:loading.attr="disabled"
                    class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-sync-alt" wire:loading.class="fa-spin"></i>
                    Обновить
                </button>
            </div>

            <div class="row">
                @foreach($trucks as $truck)
                    @php
                        $statusLabels = [
                            'free' => ['label' => 'Свободен', 'class' => 'bg-success'],
                            'to_miner' => ['label' => 'К забою', 'class' => 'bg-info'],
                            'loading' => ['label' => 'Погрузка', 'class' => 'bg-warning'],
                            'transporting' => ['label' => 'Перевозка', 'class' => 'bg-primary'],
                            'unloading' => ['label' => 'Разгрузка', 'class' => 'bg-secondary'],
                            'breakdown' => ['label' => 'Поломка', 'class' => 'bg-danger'],
                            'delayed' => ['label' => 'Задержка', 'class' => 'bg-warning'],
                        ];
                        $status = $statusLabels[$truck->status] ?? ['label' => $truck->status, 'class' => 'bg-secondary'];
                        // После latest() в запросе - первый = последний активный trip
                        $trip = $truck->trips->first();
                        
                        // Определяем породу грузовика
                        $truckRock = null;
                        $truckRockLabel = '';
                        if ($trip) {
                            if (in_array($truck->status, ['transporting', 'unloading']) && $trip->rock) {
                                // Загруженная порода
                                $truckRock = $trip->rock;
                                $truckRockLabel = 'Загружена';
                            } elseif ($trip->miner) {
                                // Текущая порода в забое (из trip->miner, не из miningOrder!)
                                $truckRock = $trip->miner->rocks->first();
                                $truckRockLabel = 'В забое';
                            }
                        }
                    @endphp
                    <div class="col-md-4 col-lg-3 mb-3">
                        <div class="card truck-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="fw-bold">{{ $truck->number }}</span>
                                <span class="badge {{ $status['class'] }} text-white">
                                    {{ $status['label'] }}
                                </span>
                            </div>
                            <div class="card-body py-2">
                                <small class="text-muted">{{ $truck->load_capacity }} т</small>
                                @php
                                    // Получаем активную паузу для delayed/breakdown
                                    $activePause = null;
                                    if (in_array($truck->status, ['delayed', 'breakdown']) && $trip) {
                                        $activePause = $trip->pauses->first();
                                    }
                                @endphp
                                
                                @if($activePause)
                                    <div class="mt-1 mb-1">
                                        <span class="badge {{ $truck->status === 'breakdown' ? 'bg-danger' : 'bg-warning' }} text-dark">
                                            <i class="fas fa-clock"></i>
                                            {{ \App\Models\TripPause::typeLabel($activePause->type) }}
                                        </span>
                                        <small class="text-muted ms-1">{{ $activePause->getFormattedDuration() }}</small>
                                    </div>
                                @endif
                               
                                @if($truckRock)
                                    <div class="mt-1">
                                        <span class="badge bg-secondary">{{ $truckRockLabel }}:</span>
                                        <span class="badge bg-info">{{ $truckRock->name_rock }}</span>
                                    </div>
                                @endif
                                
                                @if($trip)
                                    <div class="mt-2">
                                        <small>
                                            <i class="fas fa-route"></i>
                                            {{ $trip->miner?->name_miner ?? '-' }}
                                            →
                                            {{ $trip->dump?->name_dump ?? '-' }}
                                        </small>
                                        @if($trip->zone)
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt"></i>
                                                Зона: {{ $trip->zone->name_zone }}
                                            </small>
                                            @if($trip->zone->rocks->first())
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-gem"></i>
                                                    Порода зоны: 
                                                    <span class="badge bg-info">{{ $trip->zone->rocks->first()->name_rock }}</span>
                                                </small>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Забои -->
        <div class="tab-pane fade" id="minersTab">
            <div class="row">
                @foreach($miners as $miner)
                    <div class="col-md-4 col-lg-3 mb-3">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="fw-bold">{{ $miner->name_miner }}</span>
                                @if($miner->active)
                                    <span class="badge bg-success">Активен</span>
                                @else
                                    <span class="badge bg-secondary">Неактивен</span>
                                @endif
                            </div>
                            <div class="card-body py-2">
                                @if($miner->rocks->first())
                                    <div class="mb-2">
                                        <span class="text-muted small">Порода:</span>
                                        <span class="badge bg-info">{{ $miner->rocks->first()->name_rock }}</span>
                                    </div>
                                @else
                                    <div class="mb-2">
                                        <span class="badge bg-secondary">Порода не указана</span>
                                    </div>
                                @endif
                                
                                <small class="text-muted">
                                    Производительность: {{ $miner->capacity_per_trip ?? '-' }} т/рейс
                                </small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Назначение маршрута -->
        <div class="tab-pane fade" id="assignTab">
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
        <div class="tab-pane fade" id="zonesTab">
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
    </div>

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
                    .listen('truck-updated', (data) => {
                        console.log('Truck status updated:', data);

                        // Обновляем Livewire компонент
                        const component = Livewire.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'));
                        if (component) {
                            component.call('loadData');
                        }
                    });
            }
        });
    </script>
</div>