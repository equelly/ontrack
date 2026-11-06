{{-- Главный файл панели мастера. Каркас + @include по вкладкам. --}}
{{-- Каждая вкладка вынесена в master/_xxx-tab.blade.php для удобства поддержки. --}}
<div class="min-h-screen flex flex-col bg-slate-50" x-data="{}">

    {{-- Шапка с alerts widget и навигацией по вкладкам --}}
    @include('livewire.master._header')

    @if($activeTab === 'dashboard')
        @include('livewire.master._dashboard-tab')
    @endif

    @if($activeTab === 'equipment')
        @include('livewire.master._equipment-tab')
    @endif

    @if($activeTab === 'maintenance')
        @include('livewire.master._maintenance-tab')
    @endif

    @if($activeTab === 'requests')
        @include('livewire.master._requests-tab')
    @endif

    @if($activeTab === 'miners')
        @include('livewire.master._miners-tab')
    @endif

    @if($activeTab === 'rocks')
        @include('livewire.master._rocks-tab')
    @endif

    @if($activeTab === 'dumps')
        @include('livewire.master._dumps-tab')
    @endif

    {{-- Модальные окна (berm + orders) --}}
    @include('livewire.master._modals')

    {{-- Подключение стилей и скриптов (Leaflet и др.) --}}
    @include('livewire.master._scripts')

</div>
