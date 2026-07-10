@extends('layouts.app')

@section('content')

<!-- Убрали container, row и col. Используем простой блок с max-width и px-0 для мобильных -->
<div class="w-100 px-0 px-md-4 mt-4 mt-md-4 mx-auto" style="max-width: 960px;">
    <div class="card industrial-card">
        <div class="card-header industrial-header d-flex align-items-center">
            <i class="fas fa-industry mr-2"></i> Регистрация перегрузочного пункта
        </div>
        <!-- Плотные отступы тела карточки: p-2 на мобилках, p-4 на ПК -->
        <div class="card-body p-2 p-md-4">
            <form action="{{ route('dump.store') }}" method="POST">
                @csrf
                @if ($errors->any())
                    <div class="alert alert-danger border-0 rounded-0 p-2" role="alert">
                        <strong class="text-uppercase">Ошибка валидации:</strong>
                        <ul class="mb-0 mt-2 small">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mb-3 mb-md-4">
                    <label class="industrial-label">Наименование пункта</label>
                    <input type="text" name="name_dump" class="form-control industrial-input" value="{{ old('name_dump') }}" required>
                    @error('name_dump')
                        <div class="text-danger small mt-1 text-uppercase">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 mb-md-3">
                    <button type="button" id="addZone" class="btn btn-sm btn-add-zone w-100 w-md-auto mt-1 mt-md-0">
                        <i class="fas fa-plus mr-1"></i> Добавить зону
                    </button>
                </div>

            <select id="rock-options-template" style="display: none;">
                <!-- Рекомендация: Добавлены disabled, selected и пустой value -->
                <option value="" disabled selected>-- выберите горную породу --</option>                
                
                @foreach($rocks as $rock)
                    <option value="{{ $rock->id }}">{{ $rock->name_rock ?? $rock->name ?? $rock->title ?? 'Untitled' }}</option>
                @endforeach
            </select>
            
            <div id="zonesContainer" class="mb-3">
                <!-- Dynamic zone inputs will be added here -->
            </div>
                
                <div id="noZonesAlert" class="zone-placeholder text-center">
                    Минимум одна зона
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-end mt-4 mt-md-5 pt-2 pt-md-3 border-top">
                    <a href="{{ route('dump.index') }}" class="btn btn-industrial-secondary mb-1 mb-md-0 mr-md-2">
                        <i class="fas fa-times mr-1"></i> Отмена
                    </a>
                    <button type="submit" class="btn btn-industrial-primary">
                        <i class="fas fa-save mr-1"></i> Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        let zoneIndex = 0;
        const container = document.getElementById('zonesContainer');
        const noZonesAlert = document.getElementById('noZonesAlert');
        
        const updatePlaceholder = () => {
            if(container.children.length > 0) {
                noZonesAlert.style.display = 'none';
            } else {
                noZonesAlert.style.display = 'block';
            }
        };
        updatePlaceholder();

        document.getElementById('addZone').addEventListener('click', function(e) {
            const zoneDiv = document.createElement('div');
            zoneDiv.className = 'zone-entry mb-2 mb-md-3';
            zoneDiv.innerHTML = `
                <div class="row align-items-center g-md-2">
                    <!-- Скрываем общий заголовок на десктопе, чтобы не дублировался в строке -->
                    <h6 class="industrial-label mb-1 mb-md-0 text-dark col-12 d-md-none" style="font-size: 0.9rem;">Данные зоны разгрузки</h6>      
                    
                    <!-- Идентификатор (col-md-3) -->
                    <div class="col-12 col-md-3 mb-1 mb-md-0">
                        <label class="industrial-label d-block d-md-none">Идентификатор</label>
                        <input type="text" name="zones[${zoneIndex}][name_zone]" class="form-control industrial-input" placeholder="Напр: Зона-А" required>
                    </div>
                    
                    <!-- Вместимость с красивой плашкой м³ (col-md-3) -->
                    <div class="col-12 col-md-3 mb-1 mb-md-0">
                        <label class="industrial-label d-block d-md-none">Вместимость</label>
                        <div class="input-group">
                            <input type="number" name="zones[${zoneIndex}][capacity]" class="form-control industrial-input" placeholder="Емкость" step="0.1">
                            <span class="input-group-text industrial-input-addon" style="font-size: 0.85rem; padding: 0.375rem 0.5rem;">м³</span>
                        </div>
                    </div>
                    
                    <!-- Порода (col-md-2) -->
                    <div class="col-12 col-md-2 mb-1 mb-md-0">
                        <label class="industrial-label d-block d-md-none">Порода</label>
                        <select name="zones[${zoneIndex}][rock_id]" class="form-control industrial-input" required>
                            ${Array.from(document.getElementById('rock-options-template').options).map(opt => opt.outerHTML).join('')}
                        </select>
                    </div>
                    
                    <!-- Чекбокс: выровнен по центру на десктопе (col-md-2) -->
                    <div class="col-12 col-md-2 d-flex align-items-center mb-1 mb-md-0 justify-content-md-center">
                        <div class="form-check">
                            <input type="checkbox" name="zones[${zoneIndex}][delivery]" class="form-check-input" id="check_${zoneIndex}">
                            <label class="form-check-label industrial-label" for="check_${zoneIndex}" style="margin-bottom: 0; font-size: 0.75rem; user-select: none;">Прием г/м</label>
                        </div>
                    </div>
                    
                    <!-- Кнопка удаления (col-md-2) -->
                    <div class="col-12 col-md-2 text-md-right">
                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-zone w-100" onclick="this.closest('.zone-entry').remove(); updatePlaceholder();">
                            <i class="fas fa-trash-alt"></i> <span class="d-md-none d-lg-inline">Удалить</span>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(zoneDiv);
            zoneIndex++;
            updatePlaceholder();
        });

    });
</script>
@endsection