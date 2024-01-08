@extends('layouts.app')
@section('content')
<div class="mt-4 flex justify-content-center p-4 bg-gray-200 "  style="border-bottom: 2px solid #14B8A6; font-size: 1.2rem">

<div  style="width: 40rem"> 
    <h1 >
    <p>Параметры поиска:</p><hr>
    <p>имя автора: <i class="text-muted">{{$user_name}}</i></p>
    <p>категория: <i class="text-muted">{{$category_title}}</i></p>
    <p>номер оборудования: <i class="text-muted">{{$mashine_number}}</i></p>
    
    <p class="flex justify-content-end">Результат поиска: <strong>{{($searched_orders->total())}}</strong></p>
    </h1>
    </div>    
</div>
@foreach($searched_orders as $order) 
    
    <div class="flex justify-content-center mt-1">
        <div class="card shadow p-3 m-3 bg-white rounded"  style="width: 40rem">
                <div class="row g-0">
                    
                        <div class="card-body">
                            <div class="flex justify-content-between mt-1">
                           
                             
                            <a href="{{route('order.create')}}">    
                            <h5 class="card-title"><strong>ЭКГ№{{$order->mashine->number}}</strong></h5></a>
                             </div> <hr>      

                                        <small class="text-muted">от {{$order->createCarbon->day}} {{$order->createCarbon->translatedFormat('F')}} {{$order->createCarbon->year}}</small><br>
                                        <small class="text-muted">автор:</small>{{$order->user->name}}<br>
                                       
                                        
                                       @if(isset($order->userExec->name) && $order->userExec->name != '') 
                                        <small class="text-muted">изменено пользователем: </small>
                                           
                                         {{$order->userExec->name}}({{$order->updatedCarbon->day}} {{$order->updatedCarbon->translatedFormat('F')}} {{$order->updatedCarbon->year}}) 
                                        @endif
                                        <hr>
                                        <p class="card-text">{{$order->content}}</p>
                                        
                                        <a href="{{route('order.show', $order->id)}}"><small class="text-muted">подробнее ></small></a>

                        </div>
                    

                </div>
        </div>
    </div>     
@endforeach      
</div>     
<div class="w-3/4 flex justify-center">
    {{$searched_orders->links()}}
</div>
       

@endsection