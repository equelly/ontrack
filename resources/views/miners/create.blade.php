@extends('layouts.app')

@section('title', 'Создание забоя')

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12">
            <a href="{{ route('miners.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Назад к списку
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-plus me-2"></i>Создание нового забоя
                    </h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('miners.store') }}" method="POST">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Название забоя *</label>
                                <input type="text" name="name_miner" value="{{ old('name_miner') }}" class="form-control" required placeholder="Например: ЭКГ-5">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Производительность (т/рейс)</label>
                                <input type="number" name="capacity_per_trip" value="{{ old('capacity_per_trip') }}" class="form-control" step="0.1" min="0" placeholder="Опционально">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Описание</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Опционально">{{ old('description') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <h5>Расстояния до мест разгрузки</h5>
                            @foreach($dumps as $dump)
                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label"> {{ $dump->name_dump }}</label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="number" step="0.1" name="distances[{{ $dump->id }}]" class="form-control" placeholder="км">
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Порода для забоя выбирается экскаваторщиком при погрузке из общего списка пород.
                            Диспетчер настраивает только породы, принимаемые зонами разгрузки.
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('miners.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Отмена
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Создать забой
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
