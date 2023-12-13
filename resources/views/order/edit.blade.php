@extends('layouts.main')
@section('content')
<form action="{{route('order.update', $order->id)}}" method="POST" class="flex justify-center">
    @csrf
    @method('patch')
    <div class="flex justify-content-center mt-1">

        <div class="card shadow p-3 m-3 bg-white rounded" style="width: 20rem">
          <div class="flex justify-between">
            <div class="card-text"><small class="text-muted">от {{$order->created_at}}</small></div>
            <div>
              <select class = "form-control" name = "category_id" id="category">
              <option>{{$order->category->title}}</option>
                @foreach($categories as $category)
                  <option value="{{$category->id}}"{{$order->category->id != $category->id ? '' : 'selected'}}>{{$category->title}}
                   
                    
                   
                  </option>
                @endforeach
              </select>
            </div>
          </div>  


        <div class="row g-0">
        <div class="w-100">
            <div class="card-body">
                <div class="flex justify-between">
                  <div><label class="pt-3" for="mashine_id">ЭКГ№:</label></div>
                  <div><input class="border-blue-500 focus:outline-none focus:ring focus:border-blue-500 mt-3 w-20" style="width: 6rem; float: right; border-bottom: 2px solid #14B8A6;  border-right: 2px solid #14B8A6" type="text" name="machine_id" id="mashine_id" placeholder="" value="{{$order->mashine->number}}" readonly><br></div>
                </div>  
                <hr>  
                <label for="content_id"> заявка на выполнение работ,<br>доставку ТМЦ:</label><br>
                  <textarea class="w-100 focus:outline-none focus:ring focus:border-blue-500" rows="7" name="content" id="content_id" class="border m-3" style="border-bottom: 2px solid #14B8A6; border-right: 2px solid #14B8A6;" >{{$order->content}}</textarea><br>
                <div class="flex justify-between">  
                  <label for="foto" class="pt-3">фото</label>
                  <input class="focus:outline-none focus:ring focus:border-blue-500 mt-3 w-30" type="text" name="image" id="foto" placeholder="вставить"  value="{{$order->image}}"style="width: 10rem; border-bottom: 2px solid #14B8A6;border-right: 2px solid #14B8A6">
                </div>
                  <div class="col-md-4 complect">
                    <i><h4><label for="sets">комплектация</label></h4></i><hr>
                    <div class="form-check">
                        
                        @foreach($sets as $set)   
                        <p><label class="form-check-label hover:font-cyan-300  hover:text-cyan-400" for="{{$set->id}}">
                            <input class="form-check-input checked:bg-cyan-300 hover:border-cyan-300" type="checkbox" name = "sets[]" value="{{$set->id}}" id="{{$set->id}}" 
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
                  <button type="submit" class="inline-block text-sm px-4 py-2 leading-none border rounded text-white border-teal-400 bg-teal-500 hover:text-teal-500 hover:bg-cyan-300 mt-4 lg:mt-0 callout mb-1 w-90">обновить</button>
                </div>
            </div>
          </div>
          
            
        </div>
        </div>
    </div> 
</form> 
@endsection