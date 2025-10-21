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
<form class="mt-5" action="{{route('dump.update', $dump->id)}}" method="POST" class="flex justify-center" >
    @csrf
    @method('patch')
        <div class="flex justify-content-center mt-1">
        <div class="card shadow p-1 m-1 bg-white rounded" style="width: 40rem">
                <div class="row g-0">
                    <div class="col-md-8">
                        <div class="card-body pl-1 pr-1">
                          <div class="flex justify-between" >
                                <a href="{{route('dump.edit', $dump->id)}}">    
                                <h5 class="card-title"><strong>перегрузка №{{$dump->name_dump}}</strong></h5></a>
                                
                                <div>отгрузка
                                    @foreach($dump->zones as $zone)
                                        @foreach($zone->rocks as $rock)
                                            {{ $zone->ship == true ? ($map[$rock->name_rock]?? $rock->name_rock). $zone->name_zone: '' }}
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
                                    
                                        <td  class="w-[15px] border border-gray-300">
                                          <input 
                                        type="text" 
                                        id="zone_id({{ $zone->id }})"
                                        name="name_zone[{{ $zone->name_zone }}]" 
                                        value="{{ $zone->name_zone }}" 
                                        class="rounded-sm border-3 border-sky-500 w-[40px] focus:outline-none focus:ring"/>
                                        
                                        <!-- @foreach ($zone->rocks as $rock) 
                                            

                                                @foreach($zone->rocks as $rock)
                                                    {{ $map[$rock->name_rock]?? $rock->name_rock }}
                                                @endforeach

                                                
                                         -->
                                        </td>
                                        <td class="w-[15px] border border-gray-300">
                                            <select class = "form-control" name = "rock_id" id="rock">
                                              
                                              <option></option>
                                              
                                              @foreach($rocks as $Rock)
                                              @foreach ($zone->rocks as $rock_selected)
                                              <option value="" {{$rock_selected->id != $Rock->id ? '' : 'selected'}}>{{$Rock->name_rock}}</option>
                                              @endforeach
                                              @endforeach
                                            </select>
                                        </td>
                                        <td class="w-[15px] border border-gray-300"><input 
                                        type="number" 
                                        id="slider_{{ $zone->id }}" 
                                        min="0" 
                                        max="30" 
                                        name="volume[{{ $zone->id }}]" 
                                        value="{{ $zone->volume }}" 
                                        class="rounded-sm border-3 border-sky-500 focus:outline-none focus:ring"/></td>
                                        <td  class="w-[15px] border border-gray-300"><span id="value_{{ $zone->id }}" class="diagramm inline-block h-5"
                                        style= "width: {{ $zone->volume * 0.1 }}rem;
                                                background-color: {{ $colorMap[$rock->name_rock]?? 'gray' }};">
                                        </span></td>
                                        <td  class="w-[10px] text-center align-middle border border-gray-300"> <input class="m-auto" type="checkbox" name="delivery" {{ $zone->delivery==true?'checked':'' }} /></td>
                                        <td  class="w-[10px] text-center align-middle border border-gray-300"> <input type="radio" name="ship_{{$dump->id}}" value="1" {{ $zone->ship==true?'checked':'' }}/></td>
                                    @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                                    
                                
                            </table>     
                                       
                                        
                                        <a class="flex justify-content-end" href="{{route('dump.edit', $dump->id)}}"><small class="btn mt-2">обновить информацию</small></a>
                                        
                                    
                              
                        </div>
                    </div>
                 
                    
                </div>
        </div>
    </div> 

</form> 
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
          span.style.width = (value * 0.1) + 'rem'; // длина столбика
        //span.textContent = this.value;                     // обновляем значение span рядом
      }
    });
  });
});
</script>
@endsection