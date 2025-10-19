@extends('layouts.app')
@section('content')
<div class="mt-4 flex justify-content-center p-2 bg-gray-200 "  style="border-bottom: 2px solid #14B8A6; font-size: 1.2rem">
<h4  class="flex justify-content-end p-2" style="width: 40rem">Всего точек разгрузки автомобилей:  {{count($dumps)}}</h4>
</div>    

@foreach($dumps as $dump) 

    <div class="flex justify-content-center mt-1">
        <div class="card shadow p-1 m-1 bg-white rounded" style="width: 40rem">
                <div class="row g-0">
                    <div class="col-md-8">
                        <div class="card-body pl-1">
                           
                            <a href="{{route('order.create')}}">    
                            <h5 class="card-title"><strong>перегрузка №{{$dump->name_dump}}</strong></h5></a>
                            <div class="flex justify-content-between mt-1">
                            <small class="text-muted">обновил: <br>{{ $dump->lastEditor->name?? 'неизвестный' }}</small>
                            <small class="text-muted">
                                {!! $dump->last_updated_at? $dump->last_updated_at->format('d.m. H:i'). '<br>('. $dump->last_updated_at->diffForHumans(). ')': 'нет данных'!!}
                            </small>

                             </div> 
                             <table class="table-fixed w-full border-collapse border border-gray-400">
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
                                
                                <tbody>
                                  @foreach($dump->zones as $zone) 
                                    <tr>
                                    
                                        <td  class="w-[20px] border border-gray-300">{{ $zone->name_zone }}
                                        @foreach ($zone->rocks as $rock) 
                                            

                                                @foreach($zone->rocks as $rock)
                                                    {{ $map[$rock->name_rock]?? $rock->name_rock }}
                                                @endforeach

                                                
                                        
                                        </td>
                                        <td class="w-[15px] border border-gray-300"><input 
                                        type="number" 
                                        id="slider_{{ $zone->id }}" 
                                        min="0" 
                                        max="30" 
                                        name="volume[{{ $zone->id }}]" 
                                        value="{{ $zone->volume }}" 
                                        class="rounded-sm border-3 border-sky-500 focus:outline-none focus:ring"/></td>
                                        <td  class="w-[35px] border border-gray-300"><span id="value_{{ $zone->id }}" class="diagramm inline-block h-5"
                                        style= "width: {{ $zone->volume * 0.2 }}rem;
                                                background-color: {{ $colorMap[$rock->name_rock]?? 'gray' }};">
                                        </span></td>
                                        <td  class="w-[10px] text-center align-middle border border-gray-300"> <input class="m-auto" type="checkbox" name="delivery" {{ $zone->delivery==true?'checked':'' }} /></td>
                                        <td  class="w-[10px] text-center align-middle border border-gray-300"> <input type="radio" name="ship_{{$dump->id}}" value="1" {{ $zone->ship==true?'checked':'' }}/></td>
                                    @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                                    
                                
                            </table>     
                                       
                                        
                                        <a href="{{route('dump.index')}}"><small class="btn mt-2">обновить</small></a>
                                        
                                    
                              
                        </div>
                    </div>
                 
                    
                </div>
        </div>
    </div> 
       
@endforeach      
<script>
    document.addEventListener('DOMContentLoaded', () => {
  // Получаем все input с id, начинающимся на "slider_"
  document.querySelectorAll('input[id^="slider_"]').forEach(input => {
    input.addEventListener('input', function() {
        const max = 30;
        let value = Number(this.value);
        if (value > max) {
        alert(`Максимальное значение — ${max}. Значение будет установлено в ${max}.`);
        value = max;
        this.value = max;
        } else if (value < 0) {
        value = 0;
        this.value = 0;
        }
        
      const zoneId = this.id.split('_'); // получаем id зоны slider из полученного массива (второй элемент с индексом 1) 
       
                   
      const span = document.getElementById(`value_${zoneId[1]}`); 
      if(span) {
          const value = Number(this.value);
          span.style.width = (value * 0.2) + 'rem'; // длина столбика
        //span.textContent = this.value;                     // обновляем значение span рядом
      }
    });
  });
});
</script>
@endsection
