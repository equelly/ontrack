@extends('layouts.app', ['hideNav' => true])
@section('title', 'Панель диспетчера')

@section('content')
    @livewire('main-dispatcher-panel')
    <div class="container-fluid mt-4">
        @livewire('shift-statistics')
    </div>
@endsection
