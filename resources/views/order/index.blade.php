@extends('layouts.app')
@section('content')
<div class="mt-4 flex justify-content-center p-2 bg-gray-200 "  style="border-bottom: 2px solid #14B8A6; font-size: 1.2rem">
<h4  class="flex justify-content-end p-2" style="width: 40rem">Всего текущих заявок:  {{count($orders)}}</h4>
</div>    
@foreach($mashines as $mashine) 
<div id="app">
<example-component></example-component>
</div>   
    <div class="flex justify-content-center mt-1">
        <div class="card shadow p-3 m-3 bg-white rounded" style="width: 40rem">
                <div class="row g-0">
                    <div class="col-md-8">
                        <div class="card-body">
                            <div class="flex justify-content-between mt-1">
                           
                             
                            <a href="{{route('order.create')}}">    
                            <h5 class="card-title"><strong>ЭКГ№{{$mashine->number}}</strong></h5></a>
                             </div> <hr>  
                                
                                    @foreach($mashine->orders as $order)
                                    
                                        @if($order->category_id==1 and $order->content != '')
                                        
                                        <small class="text-muted"> {{$order->carbon->day}} {{$order->carbon->translatedFormat('F')}} {{$order->carbon->year}} ({{$order->carbon->diffForHumans()}})</small>
                                        <p class="card-text">{{$order->content}}</p>
                                        
                                        <a href="{{route('order.show', $order->id)}}"><small class="text-muted">подробнее ></small></a><hr>
                                        @endif
                                    @endforeach
                              
                        </div>
                    </div>
                    <div class="col-md-4 complect bg-gray-200">
                    <a href="{{route('order.edit', $order->id)}}"><i><h4>комплектация</h4></i><hr></a>
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