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
<!-- В начале body -->
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<form class="mt-5" action="{{route('dump.update', $dump)}}" method="POST" class="flex justify-center" >
  @csrf
  @method('PATCH')
  
  <div class="flex justify-content-center mt-1">
    <div class="card shadow p-1 m-1 bg-white rounded" style="width: 40rem">
      <div class="row g-0">
        <div class="col-md-8">
          <div class="card-body pl-1 pr-1">
            <div class="flex justify-between" >
                <label class="card-title"><strong>перегрузка №</strong>
                  <input type="text" name="name_dump" 
                    value="{{ old('name_dump', $dump->name_dump) }}" 
                    class="rounded-sm border-3 border-sky-500 w-[40px] focus:outline-none focus:ring" required>
                    @error('name_dump') <span class="text-danger">{{ $message }}</span> @enderror
                </label>
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
                <small class="text-muted">обновил: <br>{{ $dump->lastEditor->name?? 'нет данных' }}</small>
                <small class="text-muted">
                    {!! $dump->last_updated_at? $dump->last_updated_at->format('d.m. H:i'). '<br>('. $dump->last_updated_at->diffForHumans(). ')': 'нет данных'!!}
                </small>

              </div> 
                <table class="table-fixed w-full border-collapse border border-gray-400">
                  <tbody>
                    @foreach($dump->zones as $index => $zone) 
                      <tr>
                        <input type="hidden" name="zones[{{ $index }}][id]" value="{{ $zone->id }}">
                        <td  class="w-[15px] border border-gray-300">
                          <input 
                            type="text" 
                            id="zone_id({{ $zone->id }})"
                            name="zones[{{ $index }}][name_zone]" 
                            value="{{ old('zones.'. $index. '.name_zone', $zone->name_zone) }}" 
                            class="rounded-sm border-3 border-sky-500 w-[40px] focus:outline-none focus:ring"/>            
                          @error('zones.'. $index. '.name_zone')
                              <span class="text-danger">{{ $message }}</span>
                          @enderror              
                        </td>
                        <td class="w-[15px] border border-gray-300">
                        
                          @foreach($zone->rocks as $rockIndex => $rock)
                          <select class = "form-control" name="zones[{{ $index }}][rocks][{{ $rockIndex }}][id]" required>  
                            
                            <option disabled>порода</option>
                            @foreach ($allRocks as $rockOption)   
                            <option value="{{ $rockOption->id }} " 
                                {{$rock->id != $rockOption->id ? '' : 'selected'}}>
                                {{$rockOption->name_rock}}</option>
                              @endforeach
                            
                          </select>
                          @endforeach
                        </td>
                        <td class="w-[15px] border border-gray-300">
                          <input 
                            type="number" 
                            id="slider_{{ $zone->id }}" 
                            min="0" 
                            max="30" 
                            name="zones[{{ $index }}][volume]" 
                            value="{{ old('zones.'. $index. '.volume', $zone->volume) }}"
                            class="rounded-sm border-3 border-sky-500 focus:outline-none focus:ring"/></td>
                        <td  class="w-[15px] border border-gray-300"><span id="value_{{ $zone->id }}" class="diagramm inline-block h-5"
                             style= "width: {{ $zone->volume * 0.1 }}rem;
                              background-color: {{ $colorMap[$rock->name_rock]?? 'gray' }};"></span>
                        </td>
                        <td  class="w-[10px] text-center align-middle border border-gray-300"> 
                          <input class="m-auto" type="checkbox" 
                            name="zones[{{ $index }}][delivery]" 
                            value="1"
                            {{ old('zones.'. $index. '.delivery', $zone->delivery)? 'checked': '' }} />
                          @error('zones.'. $index. '.delivery')
                              <span class="text-danger">{{ $message }}</span>
                          @enderror
                        </td>
                        <td  class="w-[10px] text-center align-middle border border-gray-300"> 
                          <input type="radio" 
                            name="loader_zone_id" 
                            value="{{ $zone->id }}"
                            {{ old('loader_zone_id', $dump->loader_zone_id?? '') == $zone->id? 'checked': '' }}/>
                          @error('loader_zone_id')
                              <span class="text-danger">{{ $message }}</span>
                          @enderror
                        </td>
                           
                        
                    </tr>
                      @endforeach
                  </tbody>
                                    
                                
                            </table>     
                                       
                                        
                                        <div class="flex justify-content-between">
                                          <button  type="submit" class="btn mt-2">обновить</button>

</form>                                         
                                          <form method="POST" action="{{ route('dump.delete', $dump) }}" 
                                                class="d-inline" onsubmit="return confirm('Удалить дамп #{{ $dump->name_dump }}? Это действие нельзя отменить!')">
                                              @csrf
                                              @method('DELETE')
                                              <button type="submit" class="btn mt-2">удалить</button>
                                          </form>
                                              <a href="{{ route('dump.index') }}" class="btn mt-2">
                                                  отмена
                                              </a>
                                        </div>
                                        
                                    
                              
                        </div>
                    </div>
                 
                    
                </div>
    </div>
  </div>
 
 
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