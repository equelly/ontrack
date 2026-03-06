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
                            <span class="badge badge-success" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                {{ $miner->rocks->first()->name_rock }}
                            </span>
                        @else
                            <span class="badge badge-secondary" style="font-size: 1rem; padding: 0.5rem 1rem;">
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
@endif
@endsection

@section('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const currentMinerId = {{ $miner?->id ?? 'null' }};

    // Функция показа уведомлений
    function showToast(message, type = 'info') {
        alert(message); // Простая версия - можно заменить на toast
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
                location.reload();
            } else {
                showToast('Ошибка при установке породы', 'error');
            }
        });
    }
</script>
@endsection