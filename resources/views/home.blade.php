@extends('layouts.app')

@section('content')
<div class="view" style="background-image: url('dist/img/quarry.jpg'); background-repeat: no-repeat; background-size: cover; background-position: center center;">
     
    <div class="row justify-content-center">
        <div class="col-md-8 mt-5">
            <div class="card mt-10 shadow rounded"">
                <div class="card-header" style="font-size: 1.3rem; color: white;">
                    <h2 class="justify-content-center">{{ __('Панель входа') }}</h2>
                    <div>{{('пользователь: ')}}{{Auth::user()->name}}<br>
                    {{('персонал: ')}}{{Auth::user()->role}}
                </div>
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
