@extends('layouts.admin')
@section('content')
    <div class="flex justify-content-center mt-1">

        <div class="card shadow p-3 m-3 bg-white rounded" style="width: 40rem">
        <div class="flex justify-content-between">
            <p class="card-text"><small class="text-muted">{{$order->cteated_at}}</small></p>
            <form action="{{route('order.destroy', $order->id)}}" method="POST">
                @csrf
                @method('delete')
            <button type="submit" class="card-text"><small class="text-muted">удалить</small></button>
            </form>
        </div>
        <div class="row g-0">
            <div class="col-md-8">
                <div class="card-body">
                    <div class="flex justify-content-between mt-1">
                        <h5 class="card-title">ЭКГ№{{$order->mashine->number}}</h5><a href="{{route('admin.order.edit', $order->id)}}"><small class="text-muted">обработать ></small></a>
                    </div>  
                    <hr>
                    <small class="text-muted">Добавлена: {{$order->dateAsCarbon->diffForHumans()}}</small><br>
                    
                    <small class="text-muted">автор: {{$order->user->name}}</small><hr>
                    @if(isset($order->userExec->name) && $order->userExec->name != '')
                        <small class="text-muted">изменено: {{$order->updated_at->diffForHumans()}}</small><br>
                        <small class="text-muted">пользователем: {{$order->userExec->name}}</small><hr>
                    @endif
                        <p class="card-text">{{$order->content}}</p>
                </div>
                </div>
                <div class="col-md-4 complect">
            <i><h4>комплектация</h4></i><hr>
          
            @foreach($mashine_sets->sets as $set)
            
                <p>{{$set->name}}</p>
            @endforeach
               

                </div>
                
        </div>
        </div>
    </div> 
@endsection