@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Заголовок -->
            <h1 class="bg-gray-200 text-center mb-4">🧑‍🔧 Система Распределения грузопотоков </h1>

            {{-- ДОСТУПНЫЕ ЗОНЫ --}}

            <p>-
                {{  $stats['total_available_zones'] }} {{  $stats['total_available_zones'] == 1? 'зона': ( $stats['total_available_zones'] < 5? 'зоны': 'зон') }}
                подготовленны для завозки
            </p>
            <p><strong>Всего: {{ $stats['total_zones'] }}</strong></p>

                @foreach($stats['zones_by_rock'] as $rockName => $zones)
                    <div style="margin: 15px 0; padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;">
                        <h3 style="margin: 0 0 10px 0; color: #2c3e50;">
                            🪨 {{ $rockName }} ({{ $zones->count() }} {{ $zones->count() == 1? 'зона': ($zones->count() < 5? 'зоны': 'зон') }})

                        </h3>

                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            @foreach($zones as $zone)
                                <span style="background: #e3f2fd; padding: 6px 12px; border-radius: 20px; font-size: 14px; border: 1px solid #2196f3;">
                                    {{ $zone->name_zone }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if($stats['total_available_zones'] == 0)
                    <div style="background: #fff3cd; padding: 12px; border-radius: 5px; border-left: 4px solid #ffc107;">
                        ⚠️ Нет доступных зон для завозки
                    </div>
                @endif


        </div>
        <p>Распределение грузопотоков по перегрузкам</p>

        {{-- 1. СТАТИСТИКА --}}
        <p>📊 Статистика</p>
        <ul>
            <li>Всего на погрузке в автомобили: {{ $stats['total_miners'] }}</li>
            <li>Перегрузки: {{ $stats['total_dumps'] }}</li>
            <li>зоны: {{ $stats['total_zones'] }}</li>
            <li>Назначений: {{ $stats['total_assignments'] }}</li>
            <li>Общая дистанция: {{ $stats['total_distance_km'] }} км</li>
            <li>Общее время: {{ $stats['total_time_hours'] }} ч</li>
            <li>Среднее расстояние: {{ $stats['average_distance'] }} км</li>
            <li>Среднее время: {{ $stats['average_time'] }} ч</li>
        </ul>
        <h3>⚡️ Производительность</h3>
        <p>Обработано miners: {{ $stats['performance']['total_miners'] }}</p>
        <p>Текущий средний объем на перегрузках в данном распределении: {{ number_format($stats['performance']['avg_zone_volume'], 1) }} м³</p>
        <p>Среднее расстояние: {{ number_format($stats['performance']['avg_distance'], 1) }} км</p>

        {{-- 2. НАЗНАЧЕНИЯ --}}
        <h2>🚛 Назначения ({{ count($assignments) }} шт.)</h2>
        @foreach($assignments as $assignment)
            <div style="border: 1px solid #ccc; margin: 5px; padding: 10px;">
                <strong>{{ $assignment['miner_name'] }}</strong> 
                → <strong>{{ $assignment['name_dump'] }}</strong><br>
                📏 {{ $assignment['distance_km'] }} км | ⏱️ {{ $assignment['travel_time_hours'] }} ч<br>
                📦 Общая емкость: {{ $assignment['dump_volume'] }} | 
                Текущие объемы: {{ $assignment['total_zone_volume'] }} остаточная емкость {{$assignment['last_volume']}}
            </div>
        @endforeach

        <!-- ПРОГРЕСС (обновляем текст) -->
        <div class="alert alert-success">
            <h5>✅ Система готова! Загружаем статистику</h5>
            <p><strong>Текущий статус:</strong></p>
            <ul class="mb-0">
                <li>🎯 <strong>{{ $stats['total_miners']?? 0 }}</strong> miners готовы к распределению</li>
                <li>📦 <strong>{{ $stats['total_dumps']?? 0 }}</strong> дампов для обработки</li>
            </ul>
            <hr>
            <p class="mb-0"><small><strong>Следующие шаги:</strong> Зоны, алгоритм, API</small></p>
        </div>

                        <!-- БЛОК 3: Зоны (ФИОЛЕТОВЫЙ) -->
        <div class="col-md-3 mb-3">
            <div class="border p-3 text-center rounded shadow-sm" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                <h2 style="color: #9c27b0; font-size: 2rem;">{{ $stats['total_zones']?? 0 }}</h2>
                <p class="mb-1"><strong>Зон</strong></p>
                <small class="text-muted">Географические зоны</small>
                <div class="mt-1">
                    <small class="text-success">✓ {{ $stats['available_zones']?? 0 }} доступно</small>
                </div>
            </div>
        </div>

                    </div>



                </div>
            </div>
        </div>
        @endsection

