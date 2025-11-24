@extends('layouts.app')
@section('content')

  <form action="{{ route('miners.update', $miner) }}" method="POST">
    @csrf
    @method('PUT')                     

      <div class="mt-4 flex justify-content-center p-2 bg-gray-200 "  style="border-bottom: 2px solid #14B8A6; font-size: 1rem">
        <input type="text" style="max-width: 100px;"
                      name="name_miner" 
                      class="form-control @error('name') is-invalid @enderror" 
                      value="{{ old('name_miner', $miner->name_miner) }}" 
                      required>
                      @error('name')
                        <div class="text-danger">{{ $message }}</div>
                      @enderror
                                <!-- ПОЛЕ ACTIVE -->
                        <div class="mb-3 ml-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="active" 
                                       name="active" 
                                       value="1" 
                                       {{ old('active', $miner->active)? 'checked': '' }}>
                                <label class="form-check-label" for="active" id="activeLabel" style="min-width: 120px; font-size: 1rem">
                                   <span id="activeText">
                                      {{ old('active', $miner->active)? 'в работе': 'не в работе' }}
                                  </span>
                                </label>
                            </div>
                        </div>

      </div>
      <p  class="m-1 p-2">Форма редактирования расстояний маршрутов от забоя {{ $miner->name_miner }} </p>
      <div class="row">
      @foreach($allDumps as $dump)
        <div class="col-md-6 mb-3">
          <div class="card">
           
            <label class="form-label pl-3"> → до перегрузки №{{ $dump->name_dump }}</label>
            <div class="input-group">
                <input type="number" 
                       name="dump_distances[{{ $dump->id }}]" 
                       class="form-control" 
                       value="{{ old('dump_distances.'. $dump->id, $miner->dumps->where('id', $dump->id)->first()?->pivot?->distance_km?? '') }}"
                       step="0.01" min="0" placeholder="км">
                <span class="input-group-text">км</span>
            </div>
          </div>
        </div>
      @endforeach
      </div>
      <div class="flex justify-around">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="{{ route('miners.index') }}" class="btn btn-secondary">
          <i class="fas fa-arrow-left me-2"></i>
                            Назад к списку
        </a>
      </div>
      </form>


@endsection
