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
    <div class="card-header d-flex justify-content-center align-items-center mb-2">
        <a href="{{ route('dump.create') }}" class="btn btn-primary w-100">
            <i class="fas fa-plus"></i> Добавить перегрузку
        </a>
    </div>
</div>
<div class="flex justify-content-center mt-1">
<div style="background: #f0f0f0;max-width:500px;" class="card mb-2" >
    <h3 class="m-2" >фильтр вывода информации по перегрузкам</h3>
    <!-- Блок фильтров -->
    <div class="filters-panel mb-4 p-3 bg-light rounded">
        <hr>
        <form method="GET" action="{{ route('dump.index') }}">
            <div class="row align-items-center">
                <!-- Чекбокс "Завозка" -->
                <div class="col-md-10 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" 
                            type="radio" 
                            name="filter_mode" 
                            id="delivery_filter"
                            value="all_delivery"
                            {{ request('filter_mode') == 'all_delivery'? 'checked': '' }}>  <!-- ← ИСПРАВЛЕНО -->
                        <label class="form-check-label" for="delivery_filter">
                             Все подготовленные для приема горной массы
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" 
                            type="radio" 
                            name="filter_mode" 
                            id="has_rock_filter"
                            value="has_ruda"
                            {{ request('filter_mode') == 'has_ruda'? 'checked': '' }}>  <!-- ← ИСПРАВЛЕНО -->
                        <label class="form-check-label" for="has_rock_filter">
                             Рудные перегрузки:
                        </label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" 
                            type="radio" 
                            name="filter_mode" 
                            id="ruda_delivery" 
                            value="ruda_delivery"
                            {{ request('filter_mode') == 'ruda_delivery'? 'checked': '' }}>  <!-- ← ИСПРАВЛЕНО -->
                        <label class="form-check-label pt-1" for="ruda_delivery">
                             → подготовленные для завозки 
                        </label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" 
                            type="radio" 
                            name="filter_mode" 
                            id="rock_shipment_filter"
                            value="ruda_shipment"
                            {{ request('filter_mode') == 'ruda_shipment'? 'checked': '' }}>  <!-- ← ИСПРАВЛЕНО -->
                        <label class="form-check-label pt-1" for="rock_shipment_filter">
                            → производится отгрузка 
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" 
                            type="radio" 
                            name="filter_mode" 
                            id="priority_zones_filter"
                            value="priority_zones"
                            {{ request('filter_mode') == 'priority_zones'? 'checked': '' }}>
                        <label class="form-check-label pt-1" for="priority_zones_filter">
                            → приоритетные зоны для завозки
                        </label>
                    </div>
                </div>

                <!-- Кнопки управления -->
                <div class="flex justify-content-between">
                    <button type="submit" class="p-1 rounded-md" style="background-color:#dddddd;">
                        🔍 Применить
                    </button>
                    <a href="{{ route('dump.index') }}" class="p-1 rounded-md" style="background-color:#dddddd;">
                        ❌ Сбросить
                    </a>
                </div>
            </div>
        </form>
        @if($activeFilter && $activeFilter!== 'all')
            <div class="alert alert-info mt-3">
                <strong> фильтр:</strong> 
                @switch($activeFilter)
                    @case('all_delivery')
                        🚛 перегрузки с подготовленными к завозке зонами - всего: {{ $dumps->count() }}
                        @break
                    @case('ruda_delivery')
                        подготовленные зоны для завозки руды ({{ $dumps->count() }})
                        @break
                    @case('has_ruda')
                        показаны рудные перегрузки ({{ $dumps->count() }})
                        @break
                    @case('ruda_shipment')
                        точки отгрузки руды ({{ $dumps->count() }})
                        @break
                    @case('priority_zones')
                        Приоритетные зоны для завозки руды ({{ $dumps->count() }})
                        <p><strong>Начните с верхних</strong> — где меньше всего руды!</p>
                        @break
                @endswitch
            </div>
        @endif
            

    </div>
</div>
</div>
    <!-- /filters-panel -->

    @if(isset($sortedDumps))
    <div class="flex justify-content-center mt-1">
        <div style="background: #f0f0f0;max-width:500px;" class="card mb-2" >
            <h3 class="m-2" ><strong>📊 Объёмы на выбранных перегрузках</strong></h3>
            <table style="border-collapse: collapse;">
                <tr style="background: #ddd;">
                    <th style="padding: 8px; border: 1px solid #ccc;">П/п</th>
                    <th style="padding: 8px; border: 1px solid #ccc;">Объём</th>
                    <th style="padding: 12px; border: 1px solid #ccc; font-weight: bold; background-color: #fff3cd;">
                        Руда
                    </th>
                    <th style="padding: 8px; border: 1px solid #ccc;">Завозка</th>

                </tr>

                @foreach($sortedDumps as $item)
                
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ccc;">
                            <a href="{{route('dump.edit', $item['dump']->id)}}">{{ $item['dump']->name_dump }}</a>
                        </td>
                       
                        <td style="white-space: nowrap; padding: 8px; border: 1px solid #ccc; text-align: right;" class="text-muted">
                            @foreach($item['dump']->zones as $zone)
                                @foreach($zone->rocks as $rock)
                                   <span style =" font-weight: bold;{{$zone->delivery == true? 'color:green;' : 'color:red;'}}">
                                    {!!$zone->delivery == true? '<sup>+</sup>' : '<sup>-</sup>'!!}{{ floor($zone->volume) }}{!!$item['dump']['loader_zone_id'] == $zone->id? '<sup>o</sup>' : ''!!}
                                    <sub class="text-muted">{{ $map[$rock->name_rock]?? $rock->name_rock }}</sub></span>
                                @endforeach
                            @endforeach
                            <br>
                            Σ ={{ $item['total_volume'] }} 
                        </td>
                        <td style="padding: 8px; border: 1px solid #ccc; text-align: right; background-color: #fff3cd;">
                            @if(isset($item['has_rock_zones']) && $item['has_rock_zones'])
                            {{--ИМЕЕТ зоны с рудой --}}
                            @if($item['rock_volume'] > 0)
                                <strong style="color: #856404;">
                                    {{ number_format($item['rock_volume'], 1) }} 
                                </strong>
                            @else
                                <span style="color: #dc3545; font-weight: bold;">
                                    0 м³ ⚠️
                                </span>
                            @endif
                        @else
                            {{-- НЕ ИМЕЕТ зон с рудой --}}
                            <span style="color: #6c757d; font-style: italic;">
                                —
                            </span>
                        @endif

                        </td>

                        <td style="padding: 8px; border: 1px solid #ccc; text-align: left;">
                            @if(isset($item['has_delivery']) && $item['has_delivery'] && isset($item['delivery_zone_rocks']) && count($item['delivery_zone_rocks']) > 0)
                                <span style="color: #28a745; font-weight: bold;">
                                    ✅ <br>
                                    @foreach($item['delivery_zone_rocks'] as $zoneData)
                                        
                                        @php
                                            $shortRocks = [];
                                            if(isset($zoneData['rocks']) && is_array($zoneData['rocks'])) {
                                                foreach($zoneData['rocks'] as $rockName) {
                                                    $shortRocks[] = $map[$rockName]?? substr($rockName, 0, 3);
                                                }
                                            }
                                            $rockString = implode('/', $shortRocks);
                                        @endphp
                                        @if(!empty($rockString))
                                            <small style="color: #6c757d;">{{ $rockString }}{{ $zoneData['name'] }}</small>
                                        @endif
                                        @if(!$loop->last) @endif
                                        
                                    @endforeach
                                </span>
                            @else
                                <span style="color: #dc3545; font-weight: bold;">❌ Нет</span>
                            @endif
                        </td>




                    </tr>
                @endforeach
            </table>
            <p style="margin: 10px 0; font-size: 0.9em; color: #666;">
                Количество перегрузок соответствующих параметрам: {{ $sortedDumps->count() }}
            </p>
            @else
                <p>Нет данных с объёмами</p>
            @endif
        </div>
    </div>



    

@foreach($dumps as $dump) 

    <div class="flex justify-content-center mt-1">
        <div class="card shadow p-1 m-1 bg-white rounded" style="width: 40rem">
                <div class="row g-0">
                    <div class="col-md-8">
                        <div class="card-body pl-1">
                           <div class="flex justify-between" >
                            <!-- отправим информацию для перехода на страницу с которой зашли 'return_to' => 'index' в session[] -->
                                <a href="{{route('dump.edit', ['dump' => $dump, 'return_to' => 'index'])}}">    
                                <h5 class="card-title"><strong>перегрузка №{{$dump->name_dump}}</strong></h5></a>
                                
                                <div>
                                <input disabled type="radio" {{ $dump->loader_zone_id != 0?'checked':'' }} /> 
                                отгрузка
                                    @if($dump->loader_zone_id != 0)
                                        @foreach($dump->zones as $zone)
                                            @foreach($zone->rocks as $rock)
                                                {{ $dump->loader_zone_id == $zone->id ? ($map[$rock->name_rock]?? $rock->name_rock). $zone->name_zone: '' }}
                                            @endforeach
                                        @endforeach
                                    @else
                                    ❌
                                    @endif
                                    <br>
                                    <input disabled class="m-auto" type="checkbox" {{ $zone->delivery == true?'checked':'' }}/>
                                    завозка
                                    @if($zone->delivery == true)
                                        @foreach($dump->zones as $zone)
                                            @foreach($zone->rocks as $rock)
                                                {{ $zone->delivery == true ? ($map[$rock->name_rock]?? $rock->name_rock). $zone->name_zone: '' }}
                                            @endforeach 
                                        @endforeach
                                    @else
                                    ❌
                                    @endif
                                </div>
                           </div>
                            <div class="flex justify-content-between mt-1">
                            <small class="text-muted">обновил: <br>{{ $dump->lastEditor->name?? 'неизвестный' }}</small>
                            <small class="text-muted">
                                {!! $dump->last_updated_at? $dump->last_updated_at->format('d.m. H:i'). '<br>('. $dump->last_updated_at->diffForHumans(). ')': 'нет данных'!!}
                            </small>

                             </div> 
                             <table class="table-fixed w-full border-collapse border border-gray-400">
                                
                                <tbody>
                                  @foreach($dump->zones as $zone) 
                                    <tr>
                                    
                                        <td  class="w-[20px] border border-gray-300">{{ $zone->name_zone }}
                                        @foreach ($zone->rocks as $rock)                                                 
                                            {{ $map[$rock->name_rock]?? $rock->name_rock }}
                                        </td>
                                        <td class="w-[15px] border border-gray-300"><div>{{ $zone->volume }}</div> 
                                        </td>
                                        <td  class="w-[35px] border border-gray-300"><span id="value_{{ $zone->id }}" class="diagramm inline-block h-5"
                                        style= "width: {{ $zone->volume * 0.2 }}rem;
                                                background-color: {{ $colorMap[$rock->name_rock]?? 'gray' }};">
                                        </span></td>
                                        <td  class="w-[10px] text-center align-middle border border-gray-300"> 
                                            <input disabled class="m-auto" type="checkbox" name="delivery"  
                                            {{ $zone->delivery==true?'checked':'' }} /></td>
                                        <td  class="w-[10px] text-center align-middle border border-gray-300"> 
                                            <input disabled type="radio" name="ship_{{$dump->id}}" value="1" 
                                            {{ $dump->loader_zone_id==$zone->id?'checked':'' }}/></td>
                                    @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                                    
                                
                            </table>     

                        </div>
                    </div>
                 
                    
                </div>
        </div>
    </div> 
       
@endforeach      

@endsection
