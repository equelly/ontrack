<div class="dispatcher-panel-wrapper" x-data="{ tab: @entangle('activeTab') }">
    @include('livewire.dispatcher._header')
    <div class="tab-content">
        @include('livewire.dispatcher._trucks-tab')
        @include('livewire.dispatcher._miners-tab')
        @include('livewire.dispatcher._routes-tab')
        @include('livewire.dispatcher._assign-tab')
        @include('livewire.dispatcher._zones-tab')
        @include('livewire.dispatcher._pauses-tab')
        @include('livewire.dispatcher._analytics-tab')
        @include('livewire.dispatcher._settings-tab')
    </div>
    @include('livewire.dispatcher._modals')
    @include('livewire.dispatcher._scripts')
</div>
