@extends('layouts.app')

@section('title', 'Редактирование забоя')

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
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-mountain me-2"></i>Редактирование забоя: {{ $miner->name_miner }}
                    </h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('miners.update', $miner->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Название забоя *</label>
                                <input type="text" name="name_miner" value="{{ $miner->name_miner }}" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Производительность (т/рейс)</label>
                                <input type="number" name="capacity_per_trip" value="{{ $miner->capacity_per_trip }}" class="form-control" step="0.1" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Статус</label>
                                <div class="form-check form-switch mt-2">
                                    <input type="checkbox" name="active" value="1" class="form-check-input" id="active" {{ $miner->active ? 'checked' : '' }}>
                                    <label class="form-check-label" for="active">
                                        {{ $miner->active ? 'Активен' : 'Неактивен' }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Описание</label>
                            <textarea name="description" class="form-control" rows="2">{{ $miner->description }}</textarea>
                        </div>

                        <!-- Информация о породах -->
                        <div class="card mb-3">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-cubes me-2"></i>Породы, добытые в забое
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Список формируется автоматически при работе экскаваторщика. Экскаваторщик выбирает породу из общего списка при погрузке.
                                </p>
                                @if($miner->rocks->count() > 0)
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($miner->rocks as $rock)
                                            <span class="badge bg-info fs-6">{{ $rock->name_rock }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-minus-circle me-1"></i>
                                        Пока нет данных о добытых породах
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('miners.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Отмена
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Сохранить изменения
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Информация</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td>ID:</td>
                            <td>{{ $miner->id }}</td>
                        </tr>
                        <tr>
                            <td>Маршрутов:</td>
                            <td>{{ $miner->orders()->count() }}</td>
                        </tr>
                        <tr>
                            <td>Расстояний:</td>
                            <td>{{ $miner->distances()->count() }}</td>
                        </tr>
                        <tr>
                            <td>Рейсов:</td>
                            <td>{{ $miner->truckTrips()->count() }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Подсветка выбранных пород
document.querySelectorAll('input[name="rock_ids[]"]').forEach(cb => {
    cb.addEventListener('change', function() {
        const label = this.nextElementSibling;
        if (this.checked) {
            label.classList.add('fw-bold', 'text-success');
        } else {
            label.classList.remove('fw-bold', 'text-success');
        }
    });
});
</script>
@endpush
