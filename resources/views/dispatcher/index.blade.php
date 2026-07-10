@extends('layouts.app', ['hideNav' => true])
@section('title', 'Панель диспетчера')

@section('content')
    @livewire('main-dispatcher-panel')
@endsection
