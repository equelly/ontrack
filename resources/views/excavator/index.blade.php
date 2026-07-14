@extends('layouts.app', ['hideNav' => true])

@section('title', 'Панель машиниста экскаватора')

@section('content')
    @livewire('excavator-panel')
    <div class="container-fluid mt-4">
        @livewire('shift-statistics')
    </div>
@endsection
