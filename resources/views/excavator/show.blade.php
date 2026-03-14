@extends('layouts.app')

@section('title', 'Панель машиниста экскаватора')

@section('content')
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
                        <select id="minerSelect" class="form-control form-control-lg">
                            <option value="">-- Выберите экскаватор --</option>
                            @foreach($miners as $m)
                                <option value="{{ $m->id }}" {{ $m->id == $miner?->id ? 'selected' : '' }}>
                                    {{ $m->name_miner }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-primary w-100" onclick="selectMiner()">
                            Выбрать
                        </button>
                    </div>
                </div>

                @if($miner)
                    <div class="mt-3">
                        <span class="text-muted">Текущий экскаватор:</span>
                        <span class="badge badge-primary" style="font-size: 1rem;">{{ $miner->name_miner }}</span>
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
                        @if($miner->rocks->first())
                            <span id="currentRockBadge" class="badge badge-success" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                {{ $miner->rocks->first()->name_rock }}
                            </span>
                        @else
                            <span id="currentRockBadge" class="badge badge-secondary" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                Не установлена
                            </span>
                        @endif
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-8">
                            <select id="rockSelect" class="form-control">
                                <option value="">-- Выберите породу --</option>
                                @foreach($rocks as $rock)
                                    <option value="{{ $rock->id }}">
                                        {{ $rock->name_rock }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-success w-100" onclick="setRock()">
                                Установить
                            </button>
                        </div>
                    </div>
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
<div class="row">
    <!-- Статистика за смену -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Статистика за смену</h5>
                <span class="badge badge-info">{{ $stats['shift_name'] ?? '' }} (с {{ $stats['shift_start'] ?? '' }})</span>
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
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-truck"></i> Самосвалы в направлении забоя</h5>
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
                                    <th>Перегрузка</th>
                                    <th>Зона</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                               @foreach($trucks as $truck)
                                @php
                                    $trip = $truck->trips->first();
                                    $statusLabels = [
                                        'to_miner' => ['label' => 'Едет к забою', 'class' => 'badge-info'],
                                        'loading' => ['label' => 'На погрузке', 'class' => 'badge-warning'],
                                        'waiting_loading' => ['label' => 'Ожидает', 'class' => 'badge-secondary']
                                    ];
                                    $status = $statusLabels[$truck->status] ?? ['label' => $truck->status, 'class' => 'badge-secondary'];
                                @endphp
                                <tr>
                                    <td><strong>{{ $truck->number }}</strong></td>
                                    <td>
                                        <span class="badge {{ $status['class'] }}" style="min-width: 100px;">
                                            {{ $status['label'] }}
                                        </span>
                                    </td>
                                    <td>{{ $truck->load_capacity }} т</td>
                                    <td>{{ $trip?->miningOrder?->dump?->name_dump ?? '-' }}</td>
                                    <td>{{ $trip?->miningOrder?->zone?->name_zone ?? '-' }}</td>
                                    <td>
                                        @if($truck->status === 'loading')
                                            <div class="d-flex align-items-center gap-2">
                                                <input type="number" class="form-control form-control-sm" 
                                                       id="volume-{{ $truck->id }}" 
                                                       value="{{ $truck->load_capacity }}" 
                                                       min="0" step="0.1" style="width: 80px;">
                                                <span>т</span>
                                                <button class="btn btn-sm btn-success" onclick="completeLoading({{ $truck->id }})">
                                                    <i class="fas fa-check"></i> Загружен
                                                </button>
                                            </div>
                                        @elseif($truck->status === 'to_miner')
                                            <button class="btn btn-sm btn-primary" onclick="confirmArrival({{ $truck->id }})">
                                                <i class="fas fa-truck-loading"></i> Прибыл
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
@endsection

@section('scripts')
<script>
    window.currentMinerId = {{ $miner?->id ?? 'null' }};
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const currentMinerId = {{ $miner?->id ?? 'null' }};

    function showToast(message, type = 'info') {
        alert(message);
    }

    // Выбор экскаватора
    function selectMiner() {
        const minerId = document.getElementById('minerSelect').value;
        if (!minerId) {
            showToast('Выберите экскаватор', 'warning');
            return;
        }

        fetch('/excavator/set-miner', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ miner_id: minerId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Экскаватор выбран', 'success');
                location.reload();
            } else {
                showToast(data.message || 'Ошибка', 'error');
            }
        });
    }

    // Установка породы
    function setRock() {
        const rockId = document.getElementById('rockSelect').value;
        if (!rockId) {
            showToast('Выберите породу', 'warning');
            return;
        }

        if (!currentMinerId) {
            showToast('Сначала выберите экскаватор', 'warning');
            return;
        }

        fetch('/excavator/set-rock', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                miner_id: currentMinerId,
                rock_id: rockId 
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Порода установлена', 'success');
                // Обновляем badge без перезагрузки
                const badge = document.getElementById('currentRockBadge');
                const rockSelect = document.getElementById('rockSelect');
                const rockName = rockSelect.options[rockSelect.selectedIndex].text;
                badge.textContent = rockName;
                badge.className = 'badge badge-success';
                badge.style.fontSize = '1rem';
                badge.style.padding = '0.5rem 1rem';
            } else {
                showToast('Ошибка при установке породы', 'error');
            }
        });
    }

    // Подтвердить прибытие самосвала
    function confirmArrival(truckId) {
        fetch(`/excavator/truck/${truckId}/confirm`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json'
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                location.reload();
            } else {
                showToast(data.message || 'Ошибка', 'error');
            }
        });
    }

    // Завершить погрузку
    function completeLoading(truckId) {
        const volumeInput = document.getElementById('volume-' + truckId);
        const volume = volumeInput ? volumeInput.value : 30;
        
        fetch(`/excavator/truck/${truckId}/complete`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ volume: parseFloat(volume) })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                location.reload();
            } else {
                showToast(data.message || 'Ошибка', 'error');
            }
        });
    }
</script>
@endsection
