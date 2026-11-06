
{{-- Модальное окно «Запрос обваловки» — упрощённое --}}
<div
    x-data="{ open: @entangle('showBermModal') }"
    x-show="open"
    x-cloak
    x-transition.opacity
    style="display: none;"
    class="fixed inset-0 z-[99999] flex items-center justify-center"
    x-on:keydown.escape.window="open = false"
>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/60 backdrop-blur-sm" x-on:click="open = false"></div>
    <div x-show="open" x-transition class="relative w-full max-w-sm mx-4 bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-5 py-3 bg-gradient-to-r from-orange-500 to-amber-500 text-white">
            <div class="flex items-center gap-2">
                <i class="fas fa-mountain text-xl"></i>
                <h3 class="text-base font-semibold">Обваловать зону</h3>
            </div>
        </div>
        <form wire:submit.prevent="createBermRequest" class="px-5 py-4 space-y-3">
            <div class="text-sm text-gray-700 bg-gray-50 px-3 py-2 rounded-md border border-gray-100">
                Зона: <strong>{{ $bermZoneName ?? '—' }}</strong>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Количество самосвалов</label>
                <input type="number" wire:model.blur="bermTrucksNeeded" min="1" max="50" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-md p-2 text-xs text-amber-800">
                <i class="fas fa-info-circle mr-1"></i>
                Порода берётся из зоны автоматически. Пока обваловывается — обычные маршруты на зону блокируются.
            </div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-md font-medium text-sm transition">
                    Обваловать
                </button>
                <button type="button" wire:click="closeBermModal" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md font-medium text-sm transition">
                    Отмена
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Модальное окно «Создать заявку» --}}
<div
    x-data="{ open: @entangle('showCreateOrderModal') }"
    x-show="open"
    x-cloak
    x-transition.opacity
    style="display: none;"
    class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
    x-on:keydown.escape.window="open = false"
>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/60 backdrop-blur-sm" x-on:click="open = false"></div>
    <div x-show="open" x-transition class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 bg-gradient-to-r from-emerald-500 to-teal-500 text-white">
            <div class="flex items-center gap-3">
                <i class="fas fa-clipboard-list text-2xl"></i>
                <div>
                    <h3 class="text-lg font-semibold">{{ $editingOrderId ? 'Редактировать заявку #' . $editingOrderId : 'Новая заявка' }}</h3>
                    <p class="text-sm text-white/90">Заявка на обслуживание / ремонт</p>
                </div>
            </div>
        </div>
        <form wire:submit.prevent="saveOrder" class="px-6 py-5 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Оборудование *</label>
                <select wire:model.live="newOrderMashineId" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 @error('newOrderMashineId') border-red-500 @enderror">
                    <option value="">— Выберите оборудование —</option>
                    @foreach(\App\Models\Mashine::orderBy('number')->get() as $mashine)
                        <option wire:key="mashine-{{ $loop->iteration }}" value="{{ $mashine->id }}">ЭКГ №{{ $mashine->number }}</option>
                    @endforeach
                </select>
                @error('newOrderMashineId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            {{-- Чекбоксы расходников --}}
            @php $allSets = \App\Models\Set::all(); @endphp
            @if($allSets->isNotEmpty())
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Комплектация (расходники)</label>
                <div class="grid grid-cols-2 gap-2 p-3 bg-slate-50 rounded-md border border-slate-200">
                    @foreach($allSets as $set)
                        <label wire:key="set-{{ $loop->iteration }}" class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" value="{{ $set->id }}" wire:model="newOrderSets" class="rounded text-emerald-600 border-gray-300 focus:ring-emerald-500">
                            {{ $set->name }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-gray-500">Отметьте нужные расходники — они сохранятся в комплектации оборудования</p>
            </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Описание заявки *</label>
                <textarea wire:model.live="newOrderContent" rows="4" placeholder="Опишите неисправность или потребность..." class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 @error('newOrderContent') border-red-500 @enderror" maxlength="2000"></textarea>
                @error('newOrderContent') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            {{-- Загрузка фото --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Фото (необязательно)</label>
                <div class="flex items-center gap-3">
                    <input type="file" wire:model="newOrderImage" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-500
                           id="order-image-upload"
                           onchange="document.getElementById('order-image-preview').src = window.URL.createObjectURL(this.files[0])" />
                    @if($newOrderImage)
                        <img id="order-image-preview" src="{{ $newOrderImage->temporaryUrl() }}" alt="preview" class="w-20 h-20 object-cover rounded-md border border-gray-300" />
                        <button type="button" wire:click="$set('newOrderImage', null)" class="text-red-500 hover:text-red-700 text-xs" title="Убрать фото">
                            <i class="fas fa-times"></i>
                        </button>
                    @else
                        <img id="order-image-preview" src="" alt="" class="w-20 h-20 object-cover rounded-md border border-gray-200 bg-slate-50" style="display:none;" />
                    @endif
                </div>
                @error('newOrderImage') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-gray-500">JPG, PNG, WebP — до 5 МБ</p>
                <div wire:loading wire:target="newOrderImage" class="text-xs text-emerald-600 mt-1">
                    <i class="fas fa-spinner fa-spin mr-1"></i> Загрузка фото...
                </div>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-md p-2 text-xs text-blue-700">
                <i class="fas fa-info-circle mr-1"></i>
                Заявка автоматически попадёт в категорию «текущие». Категорию можно сменить в деталях заявки.
            </div>
            <div class="flex gap-2 pt-2">
                <button type="submit" wire:loading.attr="disabled" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-300 text-white rounded-md font-medium transition">
                    <span wire:loading.remove><i class="fas {{ $editingOrderId ? 'fa-save' : 'fa-paper-plane' }} mr-1"></i> {{ $editingOrderId ? 'Сохранить' : 'Создать заявку' }}</span>
                    <span wire:loading><i class="fas fa-spinner fa-spin mr-1"></i> {{ $editingOrderId ? 'Сохранение...' : 'Создание...' }}</span>
                </button>
                <button type="button" wire:click="closeCreateOrderModal" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md font-medium transition">
                    Отмена
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Модальное окно «Детали заявки» --}}
<div
    x-data="{ open: @entangle('showOrderDetailsModal') }"
    x-show="open"
    x-cloak
    x-transition.opacity
    style="display: none;"
    class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
    x-on:keydown.escape.window="open = false"
>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/60 backdrop-blur-sm" x-on:click="open = false"></div>
    <div x-show="open" x-transition class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 bg-gradient-to-r from-blue-500 to-indigo-500 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fas fa-file-alt text-2xl"></i>
                    <div>
                        <h3 class="text-lg font-semibold">Заявка #{{ $viewingOrderId }}</h3>
                        <p class="text-sm text-white/90">Детали заявки</p>
                    </div>
                </div>
                <button type="button" x-on:click="open = false" class="text-white/80 hover:text-white text-2xl leading-none">&times;</button>
            </div>
        </div>
        @if($viewingOrder)
        <div class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Оборудование</p>
                    <p class="text-sm font-medium text-gray-800">ЭКГ №{{ $viewingOrder->mashine?->number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Автор</p>
                    <p class="text-sm font-medium text-gray-800">{{ $viewingOrder->user?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Создана</p>
                    <p class="text-sm font-medium text-gray-800">{{ $viewingOrder->created_at?->translatedFormat('d F Y, H:i') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Исполнитель</p>
                    @if($viewingOrder->user_exec)
                        <p class="text-sm font-medium text-emerald-600">{{ $viewingOrder->userExec?->name ?? '—' }}</p>
                    @else
                        <p class="text-sm text-gray-400">Не назначен</p>
                    @endif
                </div>
            </div>

            {{-- Комплектация (расходники оборудования) — с чекбоксами --}}
            @php $allSets = \App\Models\Set::all(); @endphp
            @if($allSets->isNotEmpty())
            <div>
                <p class="text-xs text-gray-500 uppercase font-semibold mb-2">Комплектация</p>
                <div class="grid grid-cols-2 gap-2 p-3 bg-slate-50 rounded-md border border-slate-200">
                    @php
                        $equipmentSets = $viewingOrder->mashine?->sets?->pluck('id')?->toArray() ?? [];
                    @endphp
                    @foreach($allSets as $set)
                        @php $isChecked = in_array($set->id, $equipmentSets); @endphp
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer rounded-md px-2 py-1 {{ $isChecked ? 'bg-emerald-50 border border-emerald-200' : 'border border-transparent' }} hover:bg-slate-100 transition">
                            <input type="checkbox"
                                @if($viewingOrder->mashine_id) wire:click="toggleSet({{ $viewingOrder->mashine_id }}, {{ $set->id }})" @endif
                                {{ $isChecked ? 'checked' : '' }}
                                class="rounded text-emerald-600 border-gray-300 focus:ring-emerald-500 cursor-pointer"
                            />
                            <span class="{{ $isChecked ? 'text-emerald-700 font-medium' : 'text-gray-500' }}">
                                {{ $set->name }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-gray-400">Отмечено = требуется завезти. Снять галочку = завезли / не требуется.</p>
            </div>
            @endif

            <div>
                <p class="text-xs text-gray-500 uppercase font-semibold mb-2">Описание</p>
                <div class="bg-slate-50 p-4 rounded-md border border-slate-200">
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $viewingOrder->content }}</p>
                </div>
            </div>

            {{-- Фото заявки --}}
            @if($viewingOrder->image)
            <div x-data="{ zoomed: false }">
                <p class="text-xs text-gray-500 uppercase font-semibold mb-2">Фото</p>
                {{-- Превью — кликабельное --}}
                <div class="rounded-md border border-slate-200 overflow-hidden cursor-zoom-in hover:opacity-80 transition" x-on:click="zoomed = true">
                    <img src="{{ asset('storage/' . $viewingOrder->image) }}" alt="Фото заявки" class="w-full max-h-48 object-cover" />
                </div>
                <p class="mt-1 text-xs text-gray-400"><i class="fas fa-search-plus mr-1"></i>Кликните для увеличения</p>

                {{-- Полноэкранный просмотр при клике --}}
                <template x-if="zoomed">
                    <div class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
                         x-on:click="zoomed = false"
                         x-on:keydown.escape.window="zoomed = false"
                         style="display: flex;">
                        <div class="relative max-w-4xl max-h-[90vh]" x-on:click.stop>
                            <img src="{{ asset('storage/' . $viewingOrder->image) }}" alt="Фото заявки" class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" />
                            <button type="button" x-on:click="zoomed = false" class="absolute -top-3 -right-3 w-8 h-8 bg-white text-gray-800 rounded-full shadow-lg flex items-center justify-center hover:bg-gray-200 transition">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
            @endif

            {{-- Смена категории (мастер) --}}
            <div>
                <p class="text-xs text-gray-500 uppercase font-semibold mb-2">Категория</p>
                <div class="flex gap-2">
                    <select wire:model.live="editOrderCategoryId" class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Без категории</option>
                        @foreach(\App\Models\Category::orderBy('title')->get() as $cat)
                            <option wire:key="cat-{{ $loop->iteration }}" value="{{ $cat->id }}" @selected($viewingOrder->category_id === $cat->id)>{{ $cat->title }}</option>
                        @endforeach
                    </select>
                    <button wire:click="changeOrderCategory({{ $viewingOrder->id }})" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md font-medium text-sm transition">
                        <i class="fas fa-edit mr-1"></i> Сменить
                    </button>
                </div>
            </div>

            <div class="flex gap-2 pt-2 border-t">
                @if(!$viewingOrder->user_exec)
                    <button wire:click="completeOrder({{ $viewingOrder->id }})" wire:loading.attr="disabled" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md font-medium text-sm transition">
                        <i class="fas fa-check mr-1"></i> Отметить выполненной
                    </button>
                @else
                    <div class="flex-1 px-4 py-2 bg-emerald-50 text-emerald-700 rounded-md font-medium text-sm text-center">
                        <i class="fas fa-check-circle mr-1"></i> Выполнена: {{ $viewingOrder->userExec?->name ?? '—' }}
                    </div>
                @endif
                {{-- Редактировать — только автору --}}
                @if($viewingOrder->user_id_req === auth()->id() || auth()->user()?->role === 'admin')
                    <button wire:click="editOrder({{ $viewingOrder->id }})" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md font-medium text-sm transition">
                        <i class="fas fa-edit mr-1"></i> Редактировать
                    </button>
                    <button wire:click="deleteOrder({{ $viewingOrder->id }})" wire:confirm="Удалить заявку?" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-md font-medium text-sm transition">
                        <i class="fas fa-trash mr-1"></i> Удалить
                    </button>
                @else
                    <span class="px-4 py-2 bg-gray-100 text-gray-400 rounded-md font-medium text-sm cursor-not-allowed" title="Редактировать/удалить может только автор заявки">
                        <i class="fas fa-lock mr-1"></i> Только автор
                    </span>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Модальное окно интерактивной карты -->
<div x-data="{ showMap: false, initMap() {
        // Инициализируем карту только один раз
        if (!window.leafletMapInstance) {
            // Добавлен параметр { attributionControl: false } для отключения логотипов
            window.leafletMapInstance = L.map('leafletMap', { attributionControl: false }).setView([51.280247, 37.633032], 13); // Координаты вашего карьера

            // Спутниковый слой Esri
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: '© Esri',
                maxZoom: 19
            }).addTo(window.leafletMapInstance);

            // Обработчик клика
            window.leafletMapInstance.on('click', function(e) {
                L.popup()
                    .setLatLng(e.latlng)
                    .setContent(`<b>Координаты:</b><br>Широта: ${e.latlng.lat.toFixed(6)}<br>Долгота: ${e.latlng.lng.toFixed(6)}`)
                    .openOn(window.leafletMapInstance);
            });
        }
        // Лекарство от скрытого окна: заставляем карту перерисоваться через 100мс после открытия
        setTimeout(() => window.leafletMapInstance.invalidateSize(), 100);
    }
    }">
    <!-- Кнопка открытия карты -->
    <button @click="showMap = true; initMap()" class="fixed bottom-4 right-4 bg-blue-600 text-white px-4 py-2 rounded-md text-xs font-semibold uppercase shadow-lg z-30">
        🗺️ Открыть карту
    </button>

    <!-- Само окно карты -->
    <div x-show="showMap" x-cloak class="fixed inset-0 z-[9999] bg-black/70 flex items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl h-[85vh] flex flex-col">
            <div class="p-4 border-b flex justify-between items-center">
                <h5 class="font-bold text-gray-800 uppercase">Интерактивная карта карьера</h5>
                <button @click="showMap = false" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>

            <!-- Контейнер карты. Добавлен h-full -->
            <div id="leafletMap" class="flex-1 w-full h-full bg-slate-200 rounded-b-xl overflow-hidden"></div>

            <div class="p-3 bg-slate-50 border-t text-xs text-gray-500">
                <i class="fas fa-info-circle"></i> Кликните по карте, чтобы поставить точку.
            </div>
        </div>
    </div>
</div>

