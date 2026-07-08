@extends('layouts.app')

@section('title', 'Управление отвалами и породами')

@php
    $VERTUSHKA = 380; // 1 вертушка = 380 м³
@endphp

@section('content')
<div class="container-fluid px-1 px-md-4 mt-2 mt-md-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4>Управление отвалами и породами</h4>
                @if(auth()->user()->role === 'admin' || auth()->user()->role === 'dispatcher')
                    <a href="{{ route('dispatcher.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i>Панель диспетчера
                    </a>
                @endif
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <h4><i class="fas fa-mountain me-2"></i>Управление точками разгрузки автомобилей</h4>
                <a href="{{ route('dump.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Добавить место разгрузки
                </a>
            </div>           
        </div>
    </div>

    @foreach($dumps as $dump)
        <div class="card industrial-card mb-4">
            <div class="card-header industrial-header text-white bg-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <a href="{{ route('dump.show', $dump->id) }}" class="text-white text-decoration-none">
                            <i class="fas fa-warehouse me-2"></i>{{ $dump->name_dump }}
                        </a>
                    </h5>
                    <div>
                        <span class="badge bg-light text-dark me-2">
                            {{ $dump->zones_count }} зон
                        </span>
                        <a href="{{ route('dump.show', $dump->id) }}" class="btn btn-sm btn-outline-light me-1" title="Детали">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="{{ route('dump.edit', $dump->id) }}" class="btn btn-sm btn-light" title="Настроить породы">
                            <i class="fas fa-cog"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                @if($dump->zones->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Зона</th>
                                    <th>Породы</th>
                                    <th style="width: 200px;">Заполнение</th>
                                    <th style="width: 100px;">Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dump->zones as $zone)
                                    @php
                                        $fillPercent = $zone->capacity > 0
                                            ? min($zone->volume / $zone->capacity * 100, 100)
                                            : 0;
                                        $volumeVertushki = $zone->volume / $VERTUSHKA;
                                        $capacityVertushki = $zone->capacity / $VERTUSHKA;
                                    @endphp
                                    <tr class="{{ $zone->delivery ? '' : 'table-secondary' }}">
                                        <td>
                                            <strong>{{ $zone->name_zone }}</strong>
                                        </td>
                                        <td>
                                            @if($zone->rocks->count() > 0)
                                                @foreach($zone->rocks as $rock)
                                                    <span class="badge bg-info me-1">{{ $rock->name_rock }}</span>
                                                @endforeach
                                            @else
                                                <span class="text-muted small">Породы не назначены</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1" style="height: 8px;">
                                                    <div class="progress-bar {{ $fillPercent > 90 ? 'bg-danger' : ($fillPercent > 70 ? 'bg-warning' : 'bg-success') }}"
                                                         style="width: {{ $fillPercent }}%">
                                                    </div>
                                                </div>
                                                <small class="ms-2 text-nowrap" title="{{ number_format($zone->volume, 0) }}/{{ number_format($zone->capacity, 0) }} м³">
                                                    {{ number_format($volumeVertushki, 1) }}/{{ number_format($capacityVertushki, 1) }} верт.
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            @if($zone->delivery)
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>Открыта
                                                </span>
                                            @else
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-times me-1"></i>Закрыта
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p class="mb-0">Нет зон для этого отвала</p>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @if($dumps->count() === 0)
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Отвалы не найдены</h5>
                <p class="text-muted">Сначала создайте отвалы в системе</p>
            </div>
        </div>
    @endif
</div>
@endsection