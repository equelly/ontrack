@extends('layouts.app')

@section('content')
<div class="container mt-5">
    
    <div class="card-header d-flex justify-content-between align-items-center mb-2">
        <h3>Забои на авто-</h3>
        <a href="{{ route('miners.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Добавить забой
        </a>
    </div>

                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    <div class="table-responsive">
    <table class="table table-striped table-hover">
            <thead class="table-dark">
            <tr class="mobile-table td">
                
                <th>Название</th>
                <th>Маршруты и расстояния</th>
                <th class="hide-on-mobile">Создан</th>
            </tr>
            </thead>
            <tbody>
            @forelse($miners as $miner)
                <tr class="mobile-table td">
                    
                    <td class="name-column" style="vertical-align: bottom; padding-bottom: 8px;">
                        {{-- Контент (верх) --}}
                        <div style="margin-bottom: 40px;">  <!-- Отступ для кнопок -->
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <div>
                                    <h6 class="mb-0">{{ $miner->name_miner }}</h6>
                                </div>
                                <span class="badge bg-{{ $miner->active? 'success': 'secondary' }} rounded-pill mt-2">
                                    {{ $miner->active? 'В работе': 'Не в работе' }}
                                </span>
                            </div>
                        </div>

                        {{-- Кнопки (низ) --}}
                        <div style="border-top: 1px solid #dee2e6; padding-top: 8px;">
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <a href="{{ route('miners.show', $miner) }}" 
                                class="btn btn-outline-primary btn-icon btn-action" 
                                title="Просмотр">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <a href="{{ route('miners.edit', $miner) }}" 
                                class="btn btn-outline-warning btn-icon btn-action" 
                                title="Редактировать">
                                    <i class="fas fa-edit"></i>
                                </a>

                                <form action="{{ route('miners.destroy', $miner) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="btn btn-outline-danger btn-icon btn-action" 
                                            title="Удалить"
                                            onclick="return confirm(`Удалить оборудование {{ $miner->name_miner }} из системы?`)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </td>             
                    <td class="text-end">
                        @if($miner->dumps->count() > 0)
                            <ol class="list-unstyled mb-0">
                                @foreach($miner->dumps as $dump)
                                    <li class="small">
                                         @if($dump->distance_km!== null)
                                         <!-- $loop объект класса CompilesLoops, который laravel создает для циклов foreach -->
                                         @if($loop->first)
                                            <small class="text-success ms-2">ближний</small>
                                         @endif
                                        <strong>до п/п №{{ $dump->name_dump }}</strong>
                                        <span class="badge bg-green-600">
                                            {{ $dump->distance_km }} км
                                        </span>
                                        
                                        @else
                                            <span class="badge bg-light text-muted">не данных</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @else
                            <span class="text-muted">не установлены!</span>
                        @endif
                    </td>
                    <td class="hide-on-mobile">{{ $miner->created_at? $miner->created_at->format('d.m.Y H:i'): 'Неизвестно' }}</td>
                    
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                        <p>Забои не найдены</p>
                        <a href="{{ route('miners.create') }}" class="btn btn-primary">
                            Добавить первый забой
                        </a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
            {{-- Пагинация --}}
        <div class="d-flex justify-content-center mt-4">
            {{ $miners->appends(request()->query())->links() }}
        </div>

        </div>
    </div> {{-- card-body --}}

</div> {{-- container --}}

@endsection


