@extends('layouts.app')

@section('title', 'Управление породами')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4><i class="fas fa-cubes me-2"></i>Управление породами</h4>
                <a href="{{ route('rocks.create') }}" class="btn btn-success btn-sm">
                    <i class="fas fa-plus"></i> Добавить породу
                </a>
            </div>
        </div>
    </div>

    <!-- Статистика -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3>{{ $stats['total_rocks'] }}</h3>
                    <small>Всего пород</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3>{{ $stats['linked_to_zones'] }}</h3>
                    <small>Привязаны к зонам</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3>{{ $stats['linked_to_miners'] }}</h3>
                    <small>Привязаны к забоям</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card {{ $stats['unlinked'] > 0 ? 'bg-warning' : 'bg-secondary' }} text-white">
                <div class="card-body text-center">
                    <h3>{{ $stats['unlinked'] }}</h3>
                    <small>Без связей</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Таблица пород -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Список пород</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Порода</th>
                            <th>Забои</th>
                            <th>Зоны</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rocks as $rock)
                            @php
                                $hasAllLinks = $rock->zones_count > 0 && $rock->miners_count > 0;
                                $hasSomeLinks = $rock->zones_count > 0 || $rock->miners_count > 0;
                            @endphp
                            <tr class="{{ $hasAllLinks ? '' : ($hasSomeLinks ? 'table-warning' : 'table-danger') }}">
                                <td>{{ $rock->id }}</td>
                                <td>
                                    <strong>{{ $rock->name_rock }}</strong>
                                </td>
                                <td>
                                    @if($rock->miners_count > 0)
                                        <span class="badge bg-info">{{ $rock->miners_count }} забоев</span>
                                    @else
                                        <span class="badge bg-danger">Не привязана</span>
                                    @endif
                                </td>
                                <td>
                                    @if($rock->zones_count > 0)
                                        <span class="badge bg-success">{{ $rock->zones_count }} зон</span>
                                    @else
                                        <span class="badge bg-danger">Не привязана</span>
                                    @endif
                                </td>
                                <td>
                                    @if($hasAllLinks)
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Активна
                                        </span>
                                    @elseif($hasSomeLinks)
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Частично
                                        </span>
                                    @else
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times me-1"></i>Не используется
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <form action="{{ route('rocks.destroy', $rock->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Удалить породу {{ $rock->name_rock }}?')"
                                                {{ $rock->zones_count > 0 || $rock->miners_count > 0 ? 'disabled title="Нельзя удалить - используется"' : '' }}>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($rocks->count() === 0)
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-cubes fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Породы не найдены</h5>
                <a href="{{ route('rocks.create') }}" class="btn btn-success mt-2">
                    <i class="fas fa-plus"></i> Добавить первую породу
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
