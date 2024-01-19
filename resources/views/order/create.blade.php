@extends('layouts.app')
@section('content')
<form action="{{route('order.store')}}" method="POST" class="flex justify-center" enctype="multipart/form-data">
    @csrf

    <!-- добавление id пользователя поле 'user_id_req' -->
    <input type="hidden" name="user_id_req" value="{{(auth()->user()->id)}}">
    <input type="hidden" name="category_id" value="1">
    
    
    <div class="flex justify-content-center mt-5">
    <span class="error" aria-live="polite"></span>
        <div class="card shadow mt-10 p-2 bg-white rounded">
        <div class="row g-0">
          <div class="col-md-6 mr-3">
             <div class="text-red-500"></div> 
                <div class="flex justify-end mb-2">
                  <label class="pt-2" for="mashine_id">ЭКГ№:</label>
                    <div class="form-group ml-4">
                        <select class = "form-control" name = "mashine_id" id="mashine" required 	autofocus>
                         <option></option>
                           @foreach($mashines as $mashine)
                          <option value="{{$mashine->id}}">{{$mashine->number}}</option>
                          @endforeach
                        </select>
                    </div>
                </div>  
                <hr>  
                <label for="content_id"> заявка на выполнение работ,<br>доставку ТМЦ:</label><br>
                  <textarea class="w-100 focus:outline-none focus:ring focus:border-blue-500" rows="7" name="content" id="content_id" class="border m-3" style="border-bottom: 2px solid #14B8A6; border-right: 2px solid #14B8A6;font-size: 1rem" placeholder="{{(isset($error)) ? $error : 'Текст заявки...'}}"></textarea><br>
                  <div class=""> 
                  <label for="foto" class="pt-3">фото</label><br>
                  <input class="" type="file" name="image" id="foto">
                  </div>
          </div>
                  <div class="col-md-5 complect">
                    <i><h4><label for="sets">комплектация</label></h4></i><hr>
                      <div class="form-check">
                       
                          @foreach($sets as $set)
                         <p style="font-size: 1rem;"><label class="form-check-label hover:text-cyan-400" for="{{$set->id}}">
                          <input class="form-check-input checked:bg-cyan-300 hover:border-cyan-300" type="checkbox" name = "sets[]" value="{{$set->id}}" id="{{$set->id}}">
                          
                           {{$set->name}}
                          </label></p>
                         
                          @endforeach
                        </select>
                      </div>
                  </div>
                <div class="flex justify-end">
                  <button style="background-color: rgb(59 130 246 / 0.7);" id = "button_id" type="submit" class="inline-block text-sm px-4 py-2 leading-none border rounded text-white border-teal-400 bg-teal-500 hover:text-teal-500 hover:bg-white mt-4 lg:mt-0 callout mb-1 w-90">отправить</button>
                </div>
            </div>
          </div>
          
            
        </div>
        
    </div> 
</form> 


@endsection