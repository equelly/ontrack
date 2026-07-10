@extends('layouts.app')

@section('title', 'Редактирование зон и пород')

@php
    // Константа: 1 вертушка = 380 м³
    $VERTUSHKA = 380;
@endphp

@section('content')
<div class="row mb-3 mt-4 px-1 px-md-4">
    <div class="col-12">
        <a href="{{ route('dump.index') }}" class="btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Назад к зонам разгрузки
        </a>
    </div>
</div>

{{-- Информация о единицах измерения --}}
<div class="alert alert-info py-2">
    <i class="fas fa-info-circle"></i> 
    <strong>Единица измерения:</strong> вертушка = 380 м³ (железнодорожный состав)
</div>

<div class="row">
    <div class="col-lg-10">
        <h3 class="mb-0">
            {{ $dump->name_dump }} - Управление данными зон
        </h3>
    <div class="card-body">
                @foreach($dump->zones as $zone)
                    @php
                        $fillPercent = $zone->capacity > 0 ? min($zone->volume / $zone->capacity * 100, 100) : 0;
                        $volumeVertushki = $zone->volume / $VERTUSHKA;
                        $capacityVertushki = $zone->capacity / $VERTUSHKA;
                    @endphp
                    <div class="card industrial-card mb-3 {{ $zone->delivery ? 'border-success' : 'border-secondary' }}" id="zone-card-{{ $zone->id }}">
                        <div class="card-header d-flex justify-content-between align-items-center {{ $zone->delivery ? 'bg-success text-white industrial-header' : 'bg-secondary text-white industrial-header' }}">
                            <h5 class="mb-0">
                                <i class="fas fa-layer-group me-2"></i>{{ $zone->name_zone }}
                            </h5>
                            <div class="form-check form-switch">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="delivery_{{ $zone->id }}"
                                       {{ $zone->delivery ? 'checked' : '' }}
                                       onchange="toggleDelivery({{ $zone->id }}, this.checked)">
                                <label class="form-check-label" for="delivery_{{ $zone->id }}">
                                    {{ $zone->delivery ? 'Принимает' : 'Закрыта' }}
                                </label>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Породы -->
                                <div class="col-md-5">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-cubes me-1"></i>Породы в зоне:
                                    </label>
                                    <select class="form-select" id="rock-select-{{ $zone->id }}" onchange="updateRocks({{ $zone->id }})">
                                        <option value="">-- Выберите породу --</option>
                                        @foreach($allRocks as $rock)
                                            <option value="{{ $rock->id }}" {{ $zone->rocks->contains($rock->id) ? 'selected' : '' }}>
                                                {{ $rock->name_rock }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted mt-2 d-block">
                                        <i class="fas fa-info-circle"></i> Выберите породы для разгрузки
                                    </small>
                                </div>
                                
                                <!-- Заполнение -->
                                <div class="col-md-7">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-chart-pie me-1"></i>Заполнение зоны:
                                    </label>
                                    
                                    <!-- Прогресс-бар -->
                                    <div class="mb-3" id="fill-section-{{ $zone->id }}">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted">Текущий:</span>
                                            <span class="fw-bold" id="volume-{{ $zone->id }}" data-volume-m3="{{ $zone->volume }}">
                                                {{ number_format($volumeVertushki, 1) }}
                                            </span>
                                            <span class="text-muted">вертушек</span>
                                        </div>
                                        <div class="progress" style="height: 30px;">
                                            <div class="progress-bar {{ $fillPercent > 90 ? 'bg-danger' : ($fillPercent > 70 ? 'bg-warning' : 'bg-success') }}"
                                                 id="progress-bar-{{ $zone->id }}"
                                                 style="width: {{ $fillPercent }}%">
                                                <span id="progress-text-{{ $zone->id }}">{{ number_format($fillPercent, 1) }}%</span>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-1">
                                            <small class="text-muted">0%</small>
                                            <small class="fw-bold" id="capacity-display-{{ $zone->id }}" data-capacity-m3="{{ $zone->capacity }}">
                                                Макс: {{ number_format($capacityVertushki, 1) }} вертушек
                                            </small>
                                            <small class="text-muted">100%</small>
                                        </div>
                                    </div>
                                    
                                    <!-- Редактирование вместимости -->
                                    <div class="row">
                                        <div class="col-12">
                                            <label class="form-label small">Вместимость (вертушек):</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number"
                                                       class="form-control"
                                                       id="capacity-input-{{ $zone->id }}"
                                                       value="{{ number_format($capacityVertushki, 1) }}"
                                                       min="0" step="0.5">
                                                <button class="btn btn-outline-primary" type="button" onclick="updateCapacity({{ $zone->id }})">
                                                    <i class="fas fa-save"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small">Текущий объём (вертушек):</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number"
                                                       class="form-control"
                                                       id="volume-input-{{ $zone->id }}"
                                                       value="{{ number_format($volumeVertushki, 1) }}"
                                                       min="0" step="0.5">
                                                <button class="btn btn-outline-primary" type="button" onclick="updateVolume({{ $zone->id }})">
                                                    <i class="fas fa-save"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    @if(floatval($zone->capacity) <= 0)
                                        <div class="alert alert-warning mt-2 mb-0 py-2 px-3" id="capacity-warning-{{ $zone->id }}">
                                            <i class="fas fa-exclamation-triangle"></i> <strong>Укажите вместимость зоны!</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                @if($dump->zones->count() === 0)
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        У этого отвала нет зон. Сначала создайте зоны для отвала.
                    </div>
                @endif
            </div>
        
    </div>
</div>

<!-- Toast контейнер -->
<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>
@endsection

@section('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const VERTUSHKA = 380; // 1 вертушка = 380 м³

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast show align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function toggleDelivery(zoneId, enabled) {
    fetch(`/user/dump/zone/${zoneId}`, {
        method: 'PUT',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ delivery: enabled })
    })
    .then(response => response.json())
    .then(data => {
        showToast(enabled ? 'Зона открыта для приёма' : 'Зона закрыта', 'success');
        const card = document.getElementById('zone-card-' + zoneId);
        if (card) {
            if (enabled) {
                card.classList.remove('border-secondary');
                card.classList.add('border-success');
                card.querySelector('.card-header').classList.remove('bg-secondary');
                card.querySelector('.card-header').classList.add('bg-success');
            } else {
                card.classList.remove('border-success');
                card.classList.add('border-secondary');
                card.querySelector('.card-header').classList.remove('bg-success');
                card.querySelector('.card-header').classList.add('bg-secondary');
            }
        }
    })
    .catch(error => {
        showToast('Ошибка обновления статуса', 'error');
        console.error(error);
    });
}

function updateRocks(zoneId) {
    const select = document.getElementById(`rock-select-${zoneId}`);
    const rockId = select.value ? [parseInt(select.value)] : [];

    fetch(`/user/dump/zone/${zoneId}`, {
        method: 'PUT',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ rock_ids: rockId })
    })
    .then(response => response.json())
    .then(data => {
        showToast(rockId.length ? 'Порода обновлена' : 'Порода сброшена', 'success');
    })
    .catch(error => {
        showToast('Ошибка обновления породы', 'error');
        console.error(error);
    });
}

function updateVolume(zoneId) {
    const input = document.getElementById('volume-input-' + zoneId);
    const volumeVertushki = parseFloat(input.value) || 0;
    const volumeM3 = volumeVertushki * VERTUSHKA; // Конвертируем в м³ для базы
    
    fetch(`/user/dump/zone/${zoneId}`, {
        method: 'PUT',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ volume: volumeM3 })
    })
    .then(response => response.json())
    .then(data => {
        showToast('Текущий объём обновлён', 'success');
        
        // Обновляем отображение
        const volumeEl = document.getElementById('volume-' + zoneId);
        const progressBar = document.getElementById('progress-bar-' + zoneId);
        const progressText = document.getElementById('progress-text-' + zoneId);
        const capacityDisplay = document.getElementById('capacity-display-' + zoneId);
        
        const capacityM3 = parseFloat(capacityDisplay.dataset.capacityM3) || 0;
        const fillPercent = capacityM3 > 0 ? Math.min(volumeM3 / capacityM3 * 100, 100) : 0;
        
        volumeEl.textContent = volumeVertushki.toFixed(1);
        volumeEl.dataset.volumeM3 = volumeM3;
        progressBar.style.width = fillPercent + '%';
        progressText.textContent = fillPercent.toFixed(1) + '%';
        
        // Обновляем цвет
        progressBar.classList.remove('bg-success', 'bg-warning', 'bg-danger');
        if (fillPercent > 90) {
            progressBar.classList.add('bg-danger');
        } else if (fillPercent > 70) {
            progressBar.classList.add('bg-warning');
        } else {
            progressBar.classList.add('bg-success');
        }
    })
    .catch(error => {
        showToast('Ошибка обновления объёма', 'error');
        console.error(error);
    });
}

function updateCapacity(zoneId) {
    const input = document.getElementById('capacity-input-' + zoneId);
    const capacityVertushki = parseFloat(input.value) || 0;
    const capacityM3 = capacityVertushki * VERTUSHKA; // Конвертируем в м³ для базы
    
    fetch(`/user/dump/zone/${zoneId}`, {
        method: 'PUT',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ capacity: capacityM3 })
    })
    .then(response => response.json())
    .then(data => {
        showToast('Вместимость обновлена', 'success');
        
        // Скрываем предупреждение если вместимость установлена
        const warning = document.getElementById('capacity-warning-' + zoneId);
        if (warning && capacityVertushki > 0) {
            warning.style.display = 'none';
        }
        
        // Обновляем отображение
        const volumeEl = document.getElementById('volume-' + zoneId);
        const progressBar = document.getElementById('progress-bar-' + zoneId);
        const progressText = document.getElementById('progress-text-' + zoneId);
        const capacityDisplay = document.getElementById('capacity-display-' + zoneId);
        
        const volumeM3 = parseFloat(volumeEl.dataset.volumeM3) || 0;
        const fillPercent = capacityM3 > 0 ? Math.min(volumeM3 / capacityM3 * 100, 100) : 0;
        
        progressBar.style.width = fillPercent + '%';
        progressText.textContent = fillPercent.toFixed(1) + '%';
        capacityDisplay.textContent = 'Макс: ' + capacityVertushki.toFixed(1) + ' вертушек';
        capacityDisplay.dataset.capacityM3 = capacityM3;
        
        // Обновляем цвет
        progressBar.classList.remove('bg-success', 'bg-warning', 'bg-danger');
        if (fillPercent > 90) {
            progressBar.classList.add('bg-danger');
        } else if (fillPercent > 70) {
            progressBar.classList.add('bg-warning');
        } else {
            progressBar.classList.add('bg-success');
        }
    })
    .catch(error => {
        showToast('Ошибка обновления вместимости', 'error');
        console.error(error);
    });
}
</script>
@endsection
