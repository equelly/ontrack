@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h4>Добавить перегрузочный пункт</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('dump.store') }}" method="POST">
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
                            <label class="form-label">Наименование</label>
                            <input type="text" name="name_dump" class="form-control" 
                                   value="{{ old('name_dump') }}" required>
                            @error('name')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                <div class="mb-3">
                    <label class="form-label">Зоны</label>
                    <button type="button" id="addZone" class="btn btn-sm btn-outline-secondary mb-2">Добавить зону</button>
                    <div id="zonesContainer" class="mb-3">
                        <!-- Dynamic zone inputs will be added here -->
                    </div>
                </div>

                @section('scripts')
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Проверка доступности элементов
                        
                        let zoneIndex = 0;
                        
                        document.getElementById('addZone').addEventListener('click', function(e) {
                            
                            const container = document.getElementById('zonesContainer');
                            if (!container) {
                                console.error('Container not found!');
                                return;
                            }
                            
                            const zoneDiv = document.createElement('div');
                            zoneDiv.className = 'zone-entry mb-2 border p-2';
                            zoneDiv.innerHTML = `
                                <div class="row">
                                    <div class="col-md-4">
                                        <input type="text" name="zones[${zoneIndex}][name_zone]" 
                                               class="form-control" placeholder="Название зоны" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" name="zones[${zoneIndex}][capacity]" 
                                               class="form-control" placeholder="Вместимость">
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="zones[${zoneIndex}][delivery]" 
                                                   class="form-check-input">
                                            <label class="form-check-label">Прием породы</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="this.closest('.zone-entry').remove()">Удалить</button>
                                    </div>
                                </div>
                            `;
                            
                            container.appendChild(zoneDiv);
                            zoneIndex++;
                            
                            // Проверка создания
                        });
                    });
                </script>
                @endsection

                        <button type="submit" class="btn btn-primary">Создать</button>
                        <a href="{{ route('dump.index') }}" class="btn btn-secondary">Отмена</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    // Debug function to test if scripts are loading
    window.checkScriptLoaded = function() {
    };
    checkScriptLoaded();
</script>
@endsection
