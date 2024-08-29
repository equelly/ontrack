@extends('layouts.admin')
@section('content')
<form action="{{route('admin.set.store')}}" method="POST" class="flex justify-center">
    @csrf
    
    <div class="flex justify-content-center mt-5">
    <span class="error" aria-live="polite"></span>
        <div class="card shadow mt-10 bg-white rounded">
        <div class="row g-0">
        <div class="w-100">
            <div class="card-body">
             <div class="text-red-500"></div> 
                <div class="flex justify-between">
                  <label for="set" class="pt-3 ml-4">Наименование позиции</label>
                  <input class="focus:outline-none focus:ring focus:border-blue-500 w-30 mr-4" type="text" name="name" id="set" placeholder="   вставить"  value="" style="width: 20rem; border-bottom: 2px solid #14B8A6;border-right: 2px solid #14B8A6" autofocus required autocomplete="off">
                </div>
              
              <div class="flex justify-end">
                <button style="background-color: rgb(59 130 246 / 0.7);" id = "button_id" type="submit" class="inline-block text-sm px-4 py-2 leading-none border rounded text-white border-teal-400 bg-teal-500 hover:text-teal-500 hover:bg-white mt-4 lg:mt-0 callout mb-1 w-90">добавить</button>
              </div>
            </div>
          </div>
        </div>
        </div>
      </div>
    </div>
                    
</form> 


@endsection