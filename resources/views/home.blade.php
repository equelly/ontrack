@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card m-10">
                <div class="card-header" style="font-size: 18px;color: white;">{{ __('Панель входа') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <p  style="font-size: 1.5rem;color: white;">
                    <?php
                        $t = date("H");

                        if ($t < "10") {
                            echo "Доброе утро!";
                        } elseif ($t < "17") {
                            echo "Добрый день!";
                        } else {
                            echo "Доброй ночи!";
                        }
                        ?>
                        Авторизация выполнена успешно.
                    </p><hr class="bg-white">
                    <div class="row justify-content-center">
                    <a class="btn btn-outline-primary w-75" href="{{route('order.index')}}" style="float: center;">вход от имени {{Auth::user()->name}}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
