@extends('layouts.admin')
@section('content')
<!-- вставляем переменные фрагменты -->

@foreach($mashines as $mashine) 
<div class="flex justify-content-center mt-1">
        <div class="card shadow p-3 m-3 bg-white rounded" style="width: 40rem">
                <div class="row g-0">
                    <div class="col-md-8">
                        <div class="card-body">
                            <div class="flex justify-content-between">
                           
                             
                            <a href="{{route('admin.order.create')}}">    
                            <h5 class="card-title "><strong>ЭКГ№{{$mashine->number}}</strong></h5></a>
                             </div> <br><hr>      
                                    @foreach($mashine->orders as $order)
                                    
                                        @if($order->category_id==1 and $order->content != '')
                                        <small class="text-muted">от {{$order->created_at}}</small>
                                        <p class="card-text">{{$order->content}}</p>
                                        
                                        <a href="{{route('admin.order.show', $order->id)}}"><small class="text-muted">подробнее ></small></a><hr>
                                        @endif
                                    @endforeach
                              
                        </div>
                    </div>
                    <div class="col-md-4 complect">
                    <i><h4>комплектация</h4></i><hr>
                            <ul> 
                            @foreach($mashine->sets as $set)
                               
                            <li>-{{$set->name}}</li>
                            
                            @endforeach
                            </ul>
                    </div>
                </div>
        </div>
    </div>       
    @endforeach 
@endsection