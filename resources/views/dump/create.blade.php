@extends('layouts.app')

@section('content')
<style>
    /* Промышленный дизайн v4 (Compact Mobile - No Margins) */
    .industrial-card {
        margin: 0 !important; /* Жестко убираем все внешние отступы */
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        background-color: #f8fafc;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    }
    .industrial-header {
        background-color: #90b4bb;
        color: #f1f5f9;
        border-radius: 4px 4px 0 0;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 700;
        font-size: 0.9rem;
        border-top: 1px solid #f59e0b;
        border-bottom: 3px solid #f59e0b;
        overflow: hidden;
        text-overflow: ellipsis;
        padding: 0.5rem 0.75rem;
    }
    .industrial-label {
        text-transform: uppercase;
        font-size: 0.9rem;
        font-weight: 700;
        color: #475569;
        letter-spacing: 0.5px;
        margin-bottom: 0.2rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .industrial-input {
        border-radius: 4px;
        border: 1px solid #94a3b8;
        background-color: #ffffff;
        font-weight: 500;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        padding: 0.375rem 0.5rem;
    }
    .industrial-input:focus {
        border-color: #475569;
        box-shadow: 0 0 0 2px rgba(71, 85, 105, 0.2);
    }
    
    /* Кнопки */
    .btn-industrial-primary {
        background-color: #f59e0b;
        outline: 2px solid #cbd5e1;
        border-radius: 4px;
        color: #1f2937;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: background-color 0.2s ease, border-color 0.2s ease;
        white-space: nowrap;
    }
    .btn-industrial-primary:hover {
        background-color: #d97706;
        border-color: #d97706;
        color: #1f2937;
    }
    .btn-industrial-secondary {
        background-color: #e2e8f0;
        outline: 2px solid #cbd5e1; 
        border-radius: 4px;
        color: #475569;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
        transition: background-color 0.2s ease;
        white-space: nowrap;
    }
    .btn-industrial-secondary:hover {
        background-color: #cbd5e1;
        color: #1e293b;
    }
    .btn-add-zone {
        background-color: transparent;
        /* Вместо этого добавляем внешнюю линию нужной толщины */
        outline: 2px solid #94a3b8;
        border-radius: 4px;
        color: #475569;
        text-transform: uppercase;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        transition: background-color 0.2s ease, color 0.2s ease;
        white-space: nowrap;
    }
    .btn-add-zone:hover {
        background-color: #475569;
        color: #ffffff;
    }
    .zone-entry {
        background-color: #ffffff;
        border: 1px solid #cbd5e1 !important;
        border-left: 4px solid #f59e0b !important;
        border-radius: 4px !important;
        padding: 0.5rem;
    }
    @media (min-width: 768px) {
        .zone-entry {
            padding: 1rem;
        }
    }
    .btn-remove-zone {
        background: transparent;
        outline: 2px solid #ef4444;
        border-radius: 4px;
        color: #ef4444;
        font-size: 0.75rem;
        text-transform: uppercase;
        font-weight: 700;
        transition: background-color 0.2s ease, color 0.2s ease;
        white-space: nowrap;
    }
    .btn-remove-zone:hover {
        background-color: #ef4444;
        color: #ffffff;
    }
    .zone-placeholder {
        border: 2px dashed #cbd5e1;
        color: #94a3b8;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border-radius: 4px;
        padding: 1rem;
    }
</style>

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
                <div class="row align-items-center">
                <h6 class="industrial-label mb-1 mb-md-0 text-dark" style="font-size: 0.9rem;">Данные зоны разгрузки</h6>        
                    <div class="col-12 col-md-4 mb-1 mb-md-0">
                        <label class="industrial-label d-block d-md-none">Идентификатор</label>
                        <input type="text" name="zones[${zoneIndex}][name_zone]" class="form-control industrial-input" placeholder="Напр: Зона-А" required>
                    </div>
                    <div class="col-7 col-md-3 mb-1 mb-md-0">
                        <label class="industrial-label d-block d-md-none">Вместимость (м³)</label>
                        <input type="number" name="zones[${zoneIndex}][capacity]" class="form-control industrial-input" placeholder="емкость" step="0.1">
                    </div>
                    м<sup>3</sup>
                    <div class="col-5 col-md-3 d-flex align-items-center mb-1 mb-md-0">
                        <div class="form-check ml-auto ml-md-0">
                            <input type="checkbox" name="zones[${zoneIndex}][delivery]" class="form-check-input" id="check_${zoneIndex}">
                            <label class="form-check-label industrial-label" for="check_${zoneIndex}" style="margin-bottom: 0; font-size: 0.65rem;">Прием г/м</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-2 mb-1 mb-md-0">
                        <label class="industrial-label d-block d-md-none">Порода</label>
                        <select name="zones[${zoneIndex}][rock_id]" class="form-control industrial-input" required>
                            ${Array.from(document.getElementById('rock-options-template').options).map(opt => opt.outerHTML).join('')}
                        </select>
                    </div>
                    <div class="col-12 col-md-2 text-right">
                        <button type="button" class="btn-sm btn-remove-zone w-100 w-auto" onclick="this.closest('.zone-entry').remove(); updatePlaceholder();">
                            <i class="fas fa-trash-alt"></i> Удалить
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