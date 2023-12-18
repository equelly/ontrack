@extends('layouts.admin')
@section('content')
<form action="{{route('admin.order.store')}}" method="POST">
    @csrf

    <!-- временный инпут для обхода ошибки при добавлении по default !=null поле 'user_id_req' -->
    <input type="hidden" name="user_id_req" value="1">
    <input type="hidden" name="category_id" value="1">
    
    
    <div class="flex justify-content-center mt-1">
    <div class="card shadow p-3 m-3 bg-white rounded" style="width: 40rem">
    <span class="error" aria-live="polite"></span>
        
        <div class="row g-0">
        <div class="w-100">
            <div class="card-body">
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
                  <textarea class="w-100 focus:outline-none focus:ring focus:border-blue-500" rows="7"  name="content" id="content_id" class="border m-3" style="border-bottom: 2px solid #14B8A6; border-right: 2px solid #14B8A6;font-size: 1rem; width:auto;" placeholder="{{(isset($error)) ? $error : 'Текст заявки...'}}"></textarea><br>
                  <div class="flex justify-between"> 
                  <label for="foto" class="pt-3">фото</label>
                  <input class="focus:outline-none focus:ring focus:border-blue-500 mt-3 w-30" type="text" name="image" id="foto" placeholder="вставить"  value="" style="width: 10rem; border-bottom: 2px solid #14B8A6;border-right: 2px solid #14B8A6">
                  </div>
                  <div class="col-md-4 complect mt-3">
                    <i><h4><label for="sets">комплектация</label></h4></i><hr>
                      <div class="form-check">
                       
                          @foreach($sets as $set)
                         <p><label class="form-check-label hover:text-cyan-400" for="{{$set->id}}">
                          <input class="form-check-input checked:bg-cyan-300 hover:border-cyan-300" type="checkbox" name = "sets[]" value="{{$set->id}}" id="{{$set->id}}">
                          
                           {{$set->name}}
                          </label></p>
                         
                          @endforeach
                        </select>
                      </div>
                  </div>
                <div class="flex justify-end">
                  <button id = "button_id" type="submit" class="inline-block text-sm px-4 py-2 leading-none border rounded text-white border-teal-400 bg-teal-500 hover:text-teal-500 hover:bg-white mt-4 lg:mt-0 callout mb-1 w-90">отправить</button>
                </div>
            </div>
          </div>
          
            
        </div>
        </div>
    </div> 
</form> 


@endsection