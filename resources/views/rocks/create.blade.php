@extends('layouts.app')

@section('title', 'Добавление породы')

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12">
            <a href="{{ route('rocks.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Назад к списку
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-plus me-2"></i>Добавление новой породы
                    </h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('rocks.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Название породы *</label>
                            <input type="text" name="name_rock" value="{{ old('name_rock') }}" class="form-control" required placeholder="Например: Песчаник">
                            @error('name_rock')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            После создания породы не забудьте:
                            <ul class="mb-0 mt-2">
                                <li>Привязать её к <a href="{{ route('miners.index') }}">забоям</a></li>
                                <li>Привязать её к <a href="{{ route('dump.index') }}">зонам разгрузки</a></li>
                            </ul>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('rocks.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Отмена
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Создать породу
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
