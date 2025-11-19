@extends('layouts.app')

@section('content')
    <div class="container mt-4">
    
        <div class="col-12">
            <!-- Заголовок -->
            <div class="bg-gray-200 text-center mb-4">🧑‍🔧 Система Распределения грузопотоков </div>
            <div style="color:#2c3e50;">✅Подготовленные зоны для приема горной массы</div>
            <div style="color:#2c3e50;">расположены в порядке возрастания </div>
                @foreach($stats['zones_by_rock']->take(3) as $rockName => $zones)
                    <div style="margin: 15px 0; padding: 12px; background: #f8f9fa; border-radius: 8px; border: 1px solid #007bff;  border-left: 4px solid #007bff;">
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
            <div style="background: #f8f9fa; padding: 10px; border-radius: 5px;">
                <h4>📊 СТАТИСТИКА:</h4>
                <div><strong>Общий объём на перегрузках:</strong> {{ $stats['total_volume'] }} м³
                <strong>Количество пород:</strong> {{ count($stats['zones_by_rock']) }}
                <strong>Всего зон:</strong> {{ $stats['total_zones'] }}</div>
            </div>

        </div>
        <p>Распределение грузопотоков по перегрузкам</p>
        <p>📊 Статистика</p>
        <ul>
            <li>Всего точек погрузки в автотранспорт : {{ $stats['total_miners'] }}</li>
            <li>Перегрузки: {{ $stats['total_dumps'] }}</li>
            <li>всего зон: {{ $stats['total_zones'] }}</li>
            <li>рассчет выполнен в режиме {{ $stats['mode_name'] }}</li>
            <li>для {{ $stats['total_assignments'] }} назначений</li>
            <li>Общая дистанция рейсов отсортированных маршрутов: {{ $stats['total_distance_km'] }} км</li>
            <li>Общее время для выполнения: {{ $stats['total_time_hours'] }} автом/часов</li>
            <li>Среднее расстояние рейса: {{ $stats['average_distance'] }} км</li>
            <li>Среднее время рейса: {{ $stats['average_time'] }} ч</li>
        </ul>
        <h2>🚛 Назначения ({{ count($assignments) }} шт.)</h2>
        
        @foreach($assignments as $assignment)
        
            <div style="margin: 15px 0; padding: 12px; background: #f8f9fa; border-radius: 8px;
            border: 1px solid #007bff;  border-left: 4px solid #007bff;" class="col-12">
                <strong>{{ $assignment[0]['miner_name'] }} 
                
                → перегрузка №{{ $assignment[0]['name_dump'] }}</strong>

                <br>
                <div>приоритет {{ $assignment[0]['score'] }} 
                <small class="text-muted">по  
                    @if($stats['mode_name'] == '⚖️ Баланс объёма и расстояния (30/70)')
                        балансу (V30/L70)⚖️ 
                    @endif
                </small></div>
                📏 {{ $assignment[0]['distance_km'] }} км | ⏱️ {{ $assignment[0]['travel_time_hours'] }} ч<br>
                📦 Общая емкость: {{ $assignment[0]['dump_volume'] }} | 
                Текущие объемы: {{ $assignment[0]['total_zone_volume'] }} остаточная емкость {{$assignment[0]['last_volume']}}
            </div>
            
        @endforeach

    </div>

@endsection

