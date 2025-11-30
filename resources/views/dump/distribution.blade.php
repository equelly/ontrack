@extends('layouts.app')

@section('content')
    <div class="container mt-4">
                <!-- Заголовок -->
            <div class="bg-gray-200 text-center mb-4"> Система Распределения грузопотоков </div>
                <div style="text-align: center; margin: 20px 0; padding: 15px; background: #e3f2fd; 
                    border-radius: 8px;" class="">
                    <label for="sort-mode" style="font-weight: bold; font-size: 15px; margin-right: 10px; color: #1976d2;">
                        🛠️ РЕЖИМ СОРТИРОВКИ:
                    </label>

                    <select  class="form-select" id="sort-mode" name="mode" onchange="changeSortMode()" 
                            style="padding: 4px 6px; font-size: 14px; border: 2px solid #2196f3; border-radius: 6px; background: white;">

                        {{-- ✅ ВЫБОР ТЕКУЩЕГО РЕЖИМА --}}
                        <option value="balance" {{ ($mode?? 'balance') == 'balance'? 'selected': '' }}>
                            ⚖️  (по балансу)
                        </option>
                        <option value="volume" {{ ($mode?? 'balance') == 'volume'? 'selected': '' }}>
                            📏  (по объёму)
                        </option>
                        <option value="distance" {{ ($mode?? 'balance') == 'distance'? 'selected': '' }}>
                            🗺️  (по расстоянию)
                        </option>
                    </select>
                </div>
        <div class="col-12">

                {{-- ✅ НОВЫЙ SELECT ДЛЯ РЕЖИМОВ --}}
           
            <div style="color:#2c3e50;">✅Подготовленные зоны для приема горной массы</div>
            <div style="color:#2c3e50;">расположены в порядке возрастания объемов</div>
            
                @foreach($stats['zones_by_rock'] as $rockName => $zones)
                    <div style="margin: 15px 0; padding: 12px; background: #f8f9fa; border-radius: 8px; border: 1px solid #007bff;  border-left: 4px solid #007bff;">
                        <h3 style="margin: 0 0 10px 0; color: #2c3e50;">
                            🪨 {{ $rockName }} ({{ $zones->count() }} {{ $zones->count() == 1? 'зона': ($zones->count() < 5? 'зоны': 'зон') }})

                        </h3>

                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            @foreach($zones as $key=>$zone)
                                <div class="pt-2">{{$key+1}}.
                                <span style="background: {{ $zone->delivery == 1? '#1bae2aa3' : '#f34121ac' }};
                                            padding: 6px 12px; border-radius: 20px; font-size: 14px; border: 1px solid #2196f3;">
                                    {{ $zone->name_zone }}
                                </span></div>
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
        <p>📊 Статистика</p>
        <ul>
            <li>Всего точек погрузки в автотранспорт : {{ $stats['total_miners'] }}</li>
            <li>Перегрузки: {{ $stats['total_dumps'] }}</li>
            <li>всего зон: {{ $stats['total_zones'] }}</li>
            <li>рассчет выполнен в режиме <strong><br> {{ $stats['mode_name'] }}</strong></li>
            <li>{{ $stats['total_assignments'] }} забоев в работе</li>
            <li>Общая дистанция рейсов отсортированных маршрутов: {{ $stats['total_distance_km'] }} км</li>
            <li>Общее время рейсов: {{ $stats['total_time_hours'] }} автом/часов</li>
            <li>Среднее расстояние рейса: {{ $stats['average_distance'] }} км</li>
            <li>Среднее время рейса: {{ $stats['average_time'] }} ч</li>
        </ul>
        <h4>🔄 Назначения для {{ count($assignments) }} забоев</h4>
     
        @foreach($assignments as $key => $assignment)
        
            <div style="margin: 15px 0; padding: 12px; background: #f8f9fa; border-radius: 8px;
            border: 1px solid #007bff;  border-left: 4px solid #007bff;" class="col-12">
                <strong>лучший маршрут для <br>{{ $assignment['miner_name'] }} 
               
                → перегрузка №{{ $assignment['name_dump'] }}</strong> 
                
                <br>
                <div>{{ $assignment['score'] }} 
                <small class="text-muted">{{$stats['mode_name']}} </small></div>
                
                📏 {{ $assignment['distance_km'] }} км | ⏱️ {{ $assignment['travel_time_hours'] }} ч<br>
                <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>№ п/п</th>
                                <th>перегрузка</th>
                                <th>рейс (км)</th>
                                <th>приоритет</th>
                            </tr>
                        </thead>
                @foreach(array_slice($allOptions[$key], 1) as $option)
                    <tbody>
                        <tr>
                            <td>{{ $loop->index + 2 }}</td>
                            <td>№{{$option['dump']['name_dump']}}</td>
                            <td>{{$option['distance']}}</td>
                            <td>{{$option['score']}}</td>
                        </tr>
                    </tbody>
                      @endforeach
                </table>
                Общая емкость: {{ $assignment['dump_volume'] }} <br>
                Текущие объемы: {{ $assignment['total_zone_volume'] }} <br>остаточная емкость {{$assignment['last_volume']}}
            </div>
            
        @endforeach

    </div>
@endsection

