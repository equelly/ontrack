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
<div class="mt-4 flex justify-content-center p-2 bg-gray-200 "  style="border-bottom: 2px solid #14B8A6; font-size: 1.2rem">
<h4  class="flex justify-content-end p-2" style="width: 40rem">Всего точек разгрузки автомобилей:  {{count($dumps)}}</h4>
</div>    

@foreach($dumps as $dump) 

    <div class="flex justify-content-center mt-1">
        <div class="card shadow p-1 m-1 bg-white rounded" style="width: 40rem">
                <div class="row g-0">
                    <div class="col-md-8">
                        <div class="card-body pl-1">
                           <div class="flex justify-between" >
                                <a href="{{route('dump.edit', $dump->id)}}">    
                                <h5 class="card-title"><strong>перегрузка №{{$dump->name_dump}}</strong></h5></a>
                                
                                <div>отгрузка
                                    @foreach($dump->zones as $zone)
                                        @foreach($zone->rocks as $rock)
                                            {{ $dump->loader_zone_id == $zone->id ? ($map[$rock->name_rock]?? $rock->name_rock). $zone->name_zone: '' }}
                                        @endforeach
                                    @endforeach

                                    <br>
                                    завозка
                                    @foreach($dump->zones as $zone)
                                        @foreach($zone->rocks as $rock)
                                            {{ $zone->delivery == true ? ($map[$rock->name_rock]?? $rock->name_rock). $zone->name_zone: '' }}
                                        @endforeach 
                                    @endforeach
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
                                            

                                                @foreach($zone->rocks as $rock)
                                                    {{ $map[$rock->name_rock]?? $rock->name_rock }}
                                                @endforeach

                                                
                                        
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
