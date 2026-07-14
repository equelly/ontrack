@extends('layouts.app', ['hideNav' => true])
@section('title', 'Панель водителя')

@section('content')
    @livewire('driver-panel')
    <div class="container-fluid mt-4">
        @livewire('shift-statistics')
    </div>
@endsection
