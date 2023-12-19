@extends('layouts.app')

@section('content')
<div class="container bd-red-100">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card mt-10 shadow rounded"">
                <div class="card-header flex justify-content-between" style="font-size: 18px;color: white;">
                    <div>{{ __('Панель входа') }}</div>
                    <div>{{Auth::user()->name}}</div>
                </div>
                
                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <p  style="font-size: 1.5rem;color: white;">
                    <?php
                        $t = date("H");

                        if ($t < "8") {
                            echo "Доброе утро!";
                        } elseif ($t < "17") {
                            echo "Добрый день!";
                        } else {
                            echo "Доброй ночи!";
                        }
                        ?><br>
                        Авторизация выполнена успешно.
                    </p><hr class="bg-white">
                    <div class="mt-4 row justify-content-center">
                    <a class="btn btn-outline-primary w-75" href="{{route('order.index')}}" style="float: center;">вход</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
