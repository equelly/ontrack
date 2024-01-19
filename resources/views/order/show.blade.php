@extends('layouts.app')
@section('content')
    <div class="flex justify-content-center mt-10">

        <div class="card shadow p-3 m-3 bg-white rounded" style="width: 40rem">
        <div class="flex justify-content-between">
            <p class="card-text"><small class="text-muted">{{$order->cteated_at}}</small></p>
           
            @if(auth()->user() && (auth()->user()->id == $order->user_id_req))

            @endif
        </div>
        <div class="row g-0">
            <div class="col-md-8">
                <div class="card-body">
                    <div class="flex justify-content-between mt-1">
                        <h4 class="card-title"><strong>ЭКГ№{{$order->mashine->number}}</strong></h4>
                        @if(auth()->user()->id == $order->user_id_req || auth()->user()->role == 'обслуживающий')    
                        <a href="{{route('order.edit', $order->id)}}"><small class="text-muted">обработать ></small></a>
                        @endif
                    </div>  
                    <hr> 
                  
                    <small class="text-muted">Добавлена: {{$order->dateAsCarbon->diffForHumans()}}</small><br>
                    <small class="text-muted">автор: {{$order->user->name}}</small>
                        <p class="card-text">{{$order->content}}</p>
                </div>

            </div>
            <div class="col-md-4 complect">
                <i><h4>комплектация</h4></i><hr>
            
                @foreach($mashine_sets->sets as $set)
                
                    <p  style="font-size: 1.1rem;">{{$set->name}}</p>
                @endforeach
                

            </div>
            @if ($order->image !== NULL)
        <div class="flex justify-content-center mt-4">
             <img src="{{asset('storage/'.$order->image)}}" alt='some photo...' ">
        </div>
            @endif
            <form action="{{route('order.destroy', $order->id)}}" method="POST" class="flex justify-content-end mr-3"> 
                    @csrf
                    @method('DELETE')
                <button type="submit" class="btn m-3" data-bs-dismiss="toast" aria-label="Close">удалить заявку</button>
                </form>
        </div>
     
            
        </div>

        </div>
    </div> 
@endsection