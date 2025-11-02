@extends('layouts.app')
@section('content')
<form action="{{route('order.store')}}" method="POST"class="flex justify-center" enctype="multipart/form-data">
    @csrf

    <!-- добавление id пользователя поле 'user_id_req' -->
    <input type="hidden" name="user_id_req" value="{{(auth()->user()->id)}}">
    <input type="hidden" name="category_id" value="1">
    
    
    <div class="flex justify-content-center mt-5">
    <span class="error" aria-live="polite"></span>
        <div class="card shadow mt-10 p-2 bg-white rounded">
        <div class="row g-0">
          <div class="col m-2">
             <div class="text-red-500"></div> 
                <div class="flex justify-end mb-2">
                  <div class="pt-2">ЭКГ№:</div>
                    <div class="form-group ml-4">
                      <select name="mashine_id" 
                              id="mashine" 
                              class="form-control @error('mashine_id') is-invalid @enderror">
                              <option value="" 
                                      {{ empty(old('mashine_id'))? 'selected': '' }}>
                                  Выберите:
                              </option>
                              @foreach($mashines as $mashine)
                                  <option value="{{ $mashine->id }}" 
                                          {{ old('mashine_id') == $mashine->id? 'selected': '' }}>
                                      {{ $mashine->number }}
                                  </option>
                              @endforeach
                        </select>
                            @error('mashine_id')
                                <div class="error-message">
                                    <small class="p-2">{{ $message }}</small>
                                </div>
                            @enderror
                    </div>
                </div>  
                <hr>
                <div class="form-group">  
                <label  for="content_id"> заявка на выполнение работ,<br>доставку ТМЦ:</label>

               <textarea class="form-control @error('content') is-invalid @enderror w-100 focus:outline-none focus:ring focus:border-blue-500" rows="7" name="content" id="content_id" 
                        style="border: 2px solid #14B8A6; font-size: 1rem"
                         placeholder="{{(isset($error)) ? $error : 'Каждую заявку оформляйте отдельно: одно действие-одна заявка...'}}">{{ old('content') }}</textarea>
                   @error('content')
                          <div class="error-message">
                              <small class="p-2">{{ $message }}</small>
                          </div>
                    @enderror
                </div>
                  <br>
                  <div class="mb-3"> 
                  <label for="foto" class="pt-3">добавить фото (по необходимости)</label><br>
                  <input class="" type="file" name="image" id="foto" accept="image/*" onchange="previewImage(event)">
                  <div id="preview" style="margin-top: 10px;"></div>
                  </div>
          </div>
                  <div class="col-md-5 complect">
                    <i><h4>комплектация</h4></i><hr>
                      <div class="form-check">
                       
                          @foreach($sets as $set)
                         <p style="font-size: 1rem;"><label class="form-check-label hover:text-cyan-400" for="{{$set->id}}">
                          <input class="form-check-input checked:bg-cyan-300 hover:border-cyan-300" 
                                  type="checkbox" 
                                  name = "sets[]" 
                                  value="{{$set->id}}" 
                                  id="{{$set->id}}"
                                  {{ in_array($set->id, old('sets', []))? 'checked': '' }}/>
                          
                           {{$set->name}}
                          </label></p>
                         
                          @endforeach
                       @error('sets')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                      </div>
                  </div>
                  
                <div class="flex justify-end">
                  
                  <div><button style="background-color: rgb(59 130 246 / 0.7);" id = "button_id" type="submit" class="inline-block text-sm px-4 py-2 leading-none border rounded text-white border-teal-400 bg-teal-500 hover:text-teal-500 hover:bg-white mt-4 lg:mt-0 callout mb-1 w-90">отправить</button></div>
                </div>
            </div>
          </div>
          
            
        </div>
        
    </div> 
</form> 
<script>
function previewImage(event) {
  const preview = document.getElementById('preview');
  preview.innerHTML = '';
  const files = document.getElementById('foto').files;
  
  if (!files || files.length === 0) return;
  
 
  
  

  const reader = new FileReader();
  reader.onload = function(e) {
    const img = document.createElement('img');
    img.src = e.target.result;
    img.style.maxWidth = '100px';
    img.style.border = '1px solid #ddd';
    preview.appendChild(img);
  };
  
  
  reader.readAsDataURL(files.item(0));//берем первыи элемент списка объекта FileList
}
</script>
@endsection