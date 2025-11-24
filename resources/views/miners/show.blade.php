@extends('layouts.app')

@section('title', 'Забой: '. $miner->name_miner)

@section('content')
    @if($miner->dumps->count() > 0)
    <div class="card border-0 shadow-sm mb-4 mt-4">
        <div class="card-header bg-gray-300 text-white">
            <table class="table table-borderless mb-0">
                <tr>
                    <td class="fw-semibold text-muted w-30">Оборудование:</td>
                    <td class="text-dark"><strong>{{ $miner->name_miner }}</strong></td>
                </tr>
                <tr>
                    <td class="fw-semibold text-muted">Статус:</td>
                    <td>
                        <span class="badge {{ $miner->active? 'bg-success': 'bg-secondary' }} fs-6">
                            {{ $miner->active? 'В работе': 'Не в работе' }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="fw-semibold text-muted">Создан:</td>
                    <td>{{ $miner->created_at->format('d.m.Y H:i') }}</td>
                </tr>
                @if($miner->last_updated_at && $miner->last_updated_by)
                <tr>
                    
                    <td class="fw-semibold text-muted">Изменен:</td>
                    <td>{{ $miner->last_updated_at->format('d.m.Y H:i') }}<br>({{ $miner->last_updated_at->diffForHumans() }})</td>
                </tr>
                    @if($lastUpdater)
                <tr>
                    
                    <td class="fw-semibold text-muted">данные обновил:</td>
                    <td>{{ $lastUpdater->name?? 'ID '. $miner->last_updated_by }}</td>
                </tr>
                     @else
                <tr>
                    
                    <td class="fw-semibold text-muted">Пользователь:</td>
                    <td> удалён</td>
                </tr>
                    @endif
                @else
                <tr>
                    
                    <td class="fw-semibold text-muted">Нет обновлений</td>
                    
                </tr>
                @endif
            </table>
                    
        </div>
        <div class="card-header bg-gray-300 text-dark">
            <h6 class="mb-0">
                <i class="fas fa-ruler-combined me-1"></i>
                Маршруты до перегузок ({{ $miner->dumps->count() }})
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 400px;">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Перегрузка</th>
                            <th class="text-center">Расстояние</th>
                            
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($miner->dumps->sortBy(function ($dump) {
                            return $dump->pivot?->distance_km?? 9999;
                        }) as $dump)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-dumpster text-muted me-2"></i>
                                    <strong>п/п №{{ $dump->name_dump?? $dump->name }}</strong>
                                    @if($dump->description)
                                        <br><small class="text-muted">{{ Str::limit($dump->description, 50) }}</small>
                                    @endif
                                </div>
                                @if($loop->first && $dump->pivot->distance_km)
                                    <span class="badge bg-warning text-dark small mt-1">
                                        ⭐️ Ближайшая
                                    </span>
                                @endif
                            </td>

                            <td class="text-center">
                                @if($dump->pivot && $dump->pivot->distance_km!== null)
                                    <span class="badge bg-primary fs-6">
                                        {{ number_format($dump->pivot->distance_km, 1) }} км
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted">Не задано</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @else
    <div class="alert alert-warning text-center">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Нет связанных дампов для забоя <strong>{{ $miner->name_miner }}</strong>
    </div>
    @endif
    <!-- Кнопки действий -->
    <div class="card-futer">
        
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-grid gap-2 d-md-block">
                        <a href="{{ route('miners.edit', $miner) }}" 
                           class="btn btn-primary btn-lg me-md-2 mb-2 mb-md-0">
                            <i class="fas fa-edit me-2"></i>
                            Редактировать
                        </a>

                        <a href="{{ route('miners.index') }}" 
                           class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-arrow-left me-2"></i>
                            Назад к списку
                        </a>
                    </div>
                </div>
            </div>
        
    </div>

    <!-- Закрываем контейнер -->
</div>
@endsection

