@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h4>Добавить майнера</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('miners.store') }}" method="POST">
                        @csrf
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <h6>Ошибки валидации:</h6>
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label">Название</label>
                            <input type="text" name="name_miner" class="form-control" 
                                   value="{{ old('name') }}" required>
                            @error('name')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
<div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="active" 
                                       name="active" 
                                       value="1" 
                                       {{ old('active', true)? 'checked': '' }}>
                                <label class="form-check-label" for="active" id="activeLabel" style="min-width: 120px;">
                                   <span id="activeText">
                                      {{ old('active', true)? '': 'checked' }}
                                  </span>
                                </label>
                            </div>
                        </div>


                        <button type="submit" class="btn btn-primary">Создать</button>
                        <a href="{{ route('miners.index') }}" class="btn btn-secondary">Отмена</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
