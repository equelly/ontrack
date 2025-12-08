@extends('layouts.app')

@section('content')
@php
    $map = [
       'вскрыша' => 'V',
       'руда' => 'R',
       'песчаник' => 'Kvp',
       'руда_S' => 'Rs',
            ];
    $colorMap = [
        'вскрыша' => 'green',
        'руда' => 'red',
        'песчаник' => 'yellow',
        'руда_S' => 'red',
            ];
@endphp
    <div class="container mt-4">
        <div class="bg-gray-200 alert-info mb-1 p-1 rounded-md"><p><i class="fas fa-info-circle me-2"></i>Распределение грузопотоков</p><br>
           <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="active_zones_only" 
                    id="active-zones" value="1" {{ $activeZonesOnly? 'checked': '' }} onchange="changeActiveZones()">
                <label class="form-check-label" for="active-zones">
                    Подготовленные для приема г/м: {{ $activeZonesOnly? $stats['count']: '⚠️ нет' }}
                </label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="active_zones_only" 
                    id="all-zones" value="0" {{!$activeZonesOnly? 'checked': '' }} onchange="changeActiveZones()">
                <label class="form-check-label" for="all-zones">
                     Все перегрузки: {{ !$activeZonesOnly? $stats['count']: '' }}
                </label>
            </div>

            
            
                <div class="flex justify-between">
                    <label for="sort-mode" style="font-weight: bold; font-size: 15px; margin-right: 10px; color: #1976d2;">
                        сортировка: 
                    </label>
                    <select class="form-select" id="sort-mode" name="mode" onchange="changeSortMode()" 
                            style="padding: 4px 6px; font-size: 14px; border: 2px solid #2196f3; border-radius: 6px; background: white;max-width: 200px">

                        <option value="balance" {{ ($mode?? 'balance') == 'balance'? 'selected': '' }}>
                            ⚖️ По балансу
                        </option>
                        <option value="volume" {{ ($mode?? 'balance') == 'volume'? 'selected': '' }}>
                            📏 По объёму
                        </option>
                        <option value="distance" {{ ($mode?? 'balance') == 'distance'? 'selected': '' }}>
                            🗺 По расстоянию
                        </option>
                    </select>
                </div>

            </div>
        </div>
        <div class="col-12">

                {{--  SELECT ДЛЯ РЕЖИМОВ --}}
           
            <div style="color:#2c3e50;">✅Подготовленные зоны для приема горной массы</div>
            <div style="color:#2c3e50;">расположены в порядке возрастания объемов</div>
           
            
            
                @foreach($stats['zones_by_rock'] as $rockName => $zones)
                        @php
                            $deliveryCount = $zones->where('delivery', 1)->count();
                            $totalInRock = $zones->count();
                        @endphp
                    <div style="margin: 15px 0; padding: 12px; background: #f8f9fa; border-radius: 8px; border: 1px solid #007bff;  border-left: 4px solid #007bff;">
                        <h3 style="margin: 0 0 10px 0; color: #2c3e50;">
                            🪨 <strong>{{ $rockName }}</strong> ({{ $totalInRock }} {{ $totalInRock == 1? 'зона': ($totalInRock < 5? 'зоны': 'зон') }})
                            @if($deliveryCount != 0)
                            подготовлено - {{$deliveryCount}} {{ $deliveryCount == 1? 'зона': ($deliveryCount < 5? 'зоны': 'зон') }} 
                            @else
                            ⚠️ нет подготовленных зон 
                            @endif
                        </h3>

                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            @foreach($zones as $key=>$zone)
                                <div class="pt-2">{{$key+1}}.
                                     <!-- отправим информацию для перехода на страницу с которой зашли 'return_to' => 'index' в session[] -->
                                <a href="{{route('dump.edit', ['dump' => $zone->dump_id, 'return_to' => 'distribution'])}}">
                                <span style="background: {{ $zone->delivery == 1? '#1bae2aa3' : '#f34121ac' }};
                                            padding: 6px 12px; border-radius: 20px; font-size: 14px; border: 1px solid #2196f3;">
                                    {{ $zone->name_zone }}
                                </span></div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            <div class="container mt-4">
                <h2>Результаты распределения</h2>

                <div class="alert alert-success">
                    <h4>📊 Статистика</h4>
                    <p><strong>Метод:</strong> {{ $distributionStats['method'] }}</p>
                    <p><strong>Наиболее высокий приоритет:</strong> {{ round($distributionStats['best_score'], 1) }}</p>
                    <p><strong>Средний приоритет:</strong> {{ round($distributionStats['avg_score_per_miner'], 1) }}</p>
                </div>
                
    

                <div class="container">
                    <h4>Назначенные маршруты:</h4>

                    @foreach($assignmentsPoints as $minerId => $routes)
                        <div class="card mb-3">
                            <div class="card-header">
                                @foreach($routes as $route)
                                <strong>Забой #{{ $route['miner_name']?? "{$minerId}" }}</strong> 
                                <span class="badge bg-primary">{{ count($routes) }} маршрутов</span>
                            </div>
                            <div class="card-body">
                                
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <strong>перегрузка №{{ $route['dump_name'] }}</strong>
                                            <br><small>Приоритет: {{ round($route['score'], 1) }}</small>
                                        </div>
                                        <div class="col-6 text-end">
                                            <small>{{ $route['distance'] }} км</small>
                                            <br><small> {{ $route['assigned_round'] }} очередь</small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>

        </div>
        <div class="container">
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
                @foreach($allOptions[$key] as $option)
                
                    <tbody>
                        <tr>
                            <td>{{ $loop->index + 1 }}</td>
                            
                            <td>
                                @php
                                    $deliveryZones = collect($option['dump']['zones'])->filter(function($zone) {
                                        return $zone['delivery'] == true;
                                    });
                                @endphp

                                @if ($deliveryZones->isNotEmpty())
                                    @foreach ($deliveryZones as $zone)
                                        
                                        ✅ {{ $map[$zone->rocks->first()->name_rock]?? $zone->rocks->first()->name_rock }}{{ $zone['name_zone'] }}<br>
                                         
                                    @endforeach
                                @else
                                    № {{ $option['dump']['name_dump'] }}
                                @endif
                            </td>
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
    </div>
@endsection

