@extends('layouts.app')
@section('content')
    <div class="flex justify-content-center mt-10">

        <div class="card shadow p-3 m-3 bg-white rounded" style="width: 40rem">
        <div class="flex justify-content-between">
            <p class="card-text"><small class="text-muted">{{$order->cteated_at}}</small></p>
           
            @if(auth()->user() && (auth()->user()->id == $order->user_id_req))
                
                <form action="{{route('order.destroy', $order->id)}}" method="POST"> 
                    @csrf
                    @method('DELETE')
                <button type="submit" class="btn-close" data-bs-dismiss="toast" aria-label="Close"><small class="text-muted">удалить</small></button>
                </form>
            @endif
        </div>
        <div class="row g-0">
            <div class="col-md-8">
                <div class="card-body">
                    <div class="flex justify-content-between mt-1">
                        <h4 class="card-title"><strong>ЭКГ№{{$order->mashine->number}}</strong></h4><a href="{{route('order.edit', $order->id)}}"><small class="text-muted">обработать ></small></a>
                    </div>  
                    <hr> 
                  
                    <small class="text-muted">Добавлена: {{$order->dateAsCarbon->diffForHumans()}}</small>
                        <p class="card-text">{{$order->content}}</p>
                </div>
                </div>
                <div class="col-md-4 complect">
            <i><h4>комплектация</h4></i><hr>
          
            @foreach($mashine_sets->sets as $set)
            
                <p  style="font-size: 1.1rem;">{{$set->name}}</p>
            @endforeach
               

                </div>
                
        </div>
        </div>
    </div> 
@endsection