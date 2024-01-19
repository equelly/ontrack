@extends('layouts.app')
@section('content')
<form action="{{route('order.update', $order->id)}}" method="POST" class="flex justify-center">
    @csrf
    @method('patch')
    <!-- добавим скрытое поле input для передачи в базу id пользавателя, который внес изменения -->
    <input type="hidden" name="user_exec" value="{{(auth()->user()->id)}}">
    <div class="flex justify-content-center mt-1">

        <div class="card shadow p-3 m-3 bg-white rounded">
          <div class="row g-0">
            <div class="col-md-6 mr-3">
              
                <div class="flex justify-between">
                  <div class=""><small class="text-muted">от {{$order->created_at}}</small></div>
                  <div>
                    <select class = "form-control" name = "category_id" id="category">
                      <option>{{$order->category->title}}</option>
                      @foreach($categories as $category)
                      <option value="{{$category->id}}"{{$order->category->id != $category->id ? '' : 'selected'}}>{{$category->title}}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
              
            
             
                
                  <div class="flex justify-end">
                    <div class="mr-3"><label class="pt-3" for="mashine_id">ЭКГ№: </label></div>
                    <div><input class="border-blue-500 focus:outline-none focus:ring focus:border-blue-500 mt-3 w-20" style="width: 5rem; float: right; border-bottom: 2px solid #14B8A6;  border-right: 2px solid #14B8A6" type="text" name="machine_id" id="mashine_id" value="{{$order->mashine->number}}" readonly></div>
                  </div>  
                <hr>  
                  <label for="content_id"> заявка на выполнение работ,<br>доставку ТМЦ:</label><hr>
                    <textarea class="w-100 focus:outline-none focus:ring focus:border-blue-500" rows="7" name="content" id="content_id" class="border m-3" style="border-bottom: 2px solid #14B8A6; border-right: 2px solid #14B8A6;" >{{$order->content}}</textarea><br>
                    <div class="flex justify-between">  
                      <label for="foto" class="pt-3">фото</label>
                      <input class="focus:outline-none focus:ring focus:border-blue-500 mt-3 w-30" type="file" name="image" id="foto" placeholder="вставить!"  value="{{$order->image}}"style="width: 10rem; border-bottom: 2px solid #14B8A6;border-right: 2px solid #14B8A6">
                    </div>
                </div> 
                <div class="col-md-5 complect bg-gray-200 ml|mr-2">
                    <i><h4><label for="sets">комплектация</label></h4></i><hr>
                    <div class="form-check">
                        
                        @foreach($sets as $set)   
                        <p  style="font-size: 1rem;"><label class="form-check-label hover:font-cyan-300  hover:text-blue-400" for="{{$set->id}}">
                            <input class="form-check-input checked:bg-cyan-300 hover:border-blue-300" type="checkbox" name = "sets[]" value="{{$set->id}}" id="{{$set->id}}" 
                            @foreach($mashine_sets as $mashine_set)
                            @if ($set ->id == $mashine_set->set_id and $mashine_set->mashine_id == $order->mashine->id)
                            {{'checked'}}
                            @endif
                            @endforeach
                              >
                              {{$set->name}}
                          </label>
                        </p>
                        @endforeach
                      
                    </div>
                </div>
                
                
                <div class="flex justify-end">
                  <button type="submit" style="background-color: rgb(59 130 246 / 0.7);" class="inline-block text-sm px-4 py-2 leading-none border rounded text-white border-teal-400 hover:text-teal-500 hover:bg-cyan-300 mt-4 lg:mt-0 callout mb-1 w-90">обновить</button>
                </div>
            
          
        </div>
          
            
        </div>
        </div>
    </div> 
</form> 
@endsection