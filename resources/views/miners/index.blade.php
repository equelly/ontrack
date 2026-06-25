@extends('layouts.app')

@section('title', 'Управление забоями')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4><i class="fas fa-mountain me-2"></i>Управление забоями и породами</h4>
                <a href="{{ route('miners.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Добавить забой
                </a>
            </div>
        </div>
    </div>

    <!-- Информационная панель -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Как работает система</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong>1. Породы в забое:</strong> Экскаваторщик выбирает текущую породу при погрузке из общего списка
                    </p>
                    <p class="mb-2">
                        <strong>2. Породы в зоне:</strong> Диспетчер настраивает какие породы принимает каждая зона
                    </p>
                    <p class="mb-0 text-muted small">
                        <i class="fas fa-exchange-alt me-1"></i>
                        При смене породы система автоматически находит подходящую зону для разгрузки
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-cubes me-2"></i>Управление породами</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('rocks.index') }}" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-list"></i> Список всех пород
                    </a>
                    <a href="{{ route('rocks.create') }}" class="btn btn-success btn-sm ms-2">
                        <i class="fas fa-plus"></i> Добавить породу
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Таблица забоев -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Список забоев</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Забой</th>
                            <th>Текущая порода</th>
                            <th>Маршруты</th>
                            <th>Производительность</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($miners as $miner)
                            <tr class="{{ $miner->active ? '' : 'table-secondary' }}">
                                <td>
                                    <strong>{{ $miner->name_miner }}</strong>
                                    @if($miner->description)
                                        <br><small class="text-muted">{{ $miner->description }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($miner->currentRock)
                                        <span class="badge bg-success">{{ $miner->currentRock->name_rock }}</span>
                                    @elseif($miner->rocks->count() > 0)
                                        <small class="text-muted">
                                            Были: {{ $miner->rocks->pluck('name_rock')->join(', ') }}
                                        </small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ $miner->orders_count }}</span>
                                </td>
                                <td>
                                    @if($miner->capacity_per_trip)
                                        <small>{{ $miner->capacity_per_trip }} т/рейс</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($miner->active)
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Активен
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-times me-1"></i>Неактивен
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('miners.edit', $miner->id) }}" class="btn btn-outline-primary" title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('miners.destroy', $miner->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Удалить" onclick="return confirm('Удалить забой?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($miners->count() === 0)
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-mountain fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Забои не найдены</h5>
                <a href="{{ route('miners.create') }}" class="btn btn-primary mt-2">
                    <i class="fas fa-plus"></i> Добавить первый забой
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
