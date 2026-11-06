        <div wire:key="tab-rocks" class="mt-4 space-y-4">
            <div class="bg-white p-4 rounded-xl border shadow-sm flex gap-2">
                <input type="text" wire:model="newRockName" placeholder="Название новой породы" class="flex-1 border-gray-300 rounded-md shadow-sm py-2">
                @error('newRockName')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <button wire:click="addRock" class="px-4 py-2 bg-emerald-600 text-white rounded-md font-semibold uppercase text-sm hover:bg-emerald-700">Добавить</button>
            </div>
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="p-4 border-b font-bold text-gray-800 uppercase text-sm">Список пород</div>
                <ul class="divide-y divide-gray-100">
                    @foreach($rocks as $rock)
                        <li wire:key="rock-{{ $loop->iteration }}" class="p-3 flex justify-between items-center hover:bg-slate-50">
                            <span class="font-medium text-gray-800">{{ $rock->name_rock }}</span>
                            <button wire:click="deleteRock({{ $rock->id }})" wire:confirm="Удалить породу?" class="text-red-400 hover:text-red-600 text-sm"><i class="fas fa-trash"></i></button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
