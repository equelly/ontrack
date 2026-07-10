@extends('layouts.app')

@section('title', 'Детали отвала')

@php
    $VERTUSHKA = 380; // 1 вертушка = 380 м³
@endphp

@section('content')
<div class="container-fluid px-1 px-md-4 mt-2 mt-md-4">
    <div class="row mb-4 mt-4">
        <div class="col-12">
            <div>
                <a href="{{ route('dump.index') }}" class="btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Назад к зонам разгрузки
                </a>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <h3> {{ $dump->name_dump }}</h3>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card industrial-card mb-4">
                <div class="card-header industrial-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Статистика</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td>Доставленный объём:</td>
                            <td>
                                <strong>{{ number_format($dump->delivered_volume / $VERTUSHKA, 1) }} вертушек</strong>
                                <br><small class="text-muted">({{ number_format($dump->delivered_volume, 0) }} м³)</small>
                            </td>
                        </tr>
                        <tr>
                            <td>Количество рейсов:</td>
                            <td><strong>{{ $dump->trips_count }}</strong></td>
                        </tr>
                        <tr>
                            <td>Количество зон:</td>
                            <td><strong>{{ $dump->zones->count() }}</strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-map me-2"></i>Зоны</h5>
                </div>
                <div class="card-body">
                    @if($dump->zones->count() > 0)
                        @foreach($dump->zones as $zone)
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                <div>
                                    <strong>{{ $zone->name_zone }}</strong>
                                    <br>
                                    <small class="text-muted">
                                        Породы: {{ $zone->rocks->pluck('name_rock')->join(', ') ?: 'Не указаны' }}
                                    </small>
                                </div>
                                <div>
                                    @if($zone->delivery)
                                        <span class="badge bg-success">Открыта</span>
                                    @else
                                        <span class="badge bg-secondary">Закрыта</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted mb-0">Нет зон</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($dump->orders->count() > 0)
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-route me-2"></i>Связанные маршруты</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Забой</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dump->orders as $order)
                                <tr>
                                    <td>{{ $order->id }}</td>
                                    <td>{{ $order->miner?->name_miner ?? '-' }}</td>
                                    <td>
                                        @if($order->active)
                                            <span class="badge bg-success">Активен</span>
                                        @else
                                            <span class="badge bg-secondary">Неактивен</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
