@extends('layouts.admin')
@section('content')
<div class="mt-4 flex justify-content-center p-2 bg-gray-200 "  style="border-bottom: 2px solid #14B8A6; font-size: 1.2rem">
<h4  class="flex justify-content-end p-2" style="width: 40rem">Количество единиц оборудования:  {{count($mashines)}}</h4>
</div>    
@foreach($mashines as $mashine) 
<div class="flex justify-content-center mt-1">
    <div class="card shadow p-3 m-3 bg-white rounded" style="width: 40rem">
        
            
                <div class="card-body p-2">    
                    <div class="flex justify-content-between">
                        <strong class="mt-2">ЭКГ№{{$mashine->number}}</strong>
                            <form action="{{route('admin.mashine.delete', $mashine->id)}}" method="POST"> 
                            @csrf
                             @method('DELETE')
                            <button type="submit" class="btn" data-bs-dismiss="toast" aria-label="Close">удалить</button>
                            </form>
                    </div> 
                </div>
            
        
    </div>
</div>


                     
    
                                                      
    @endforeach      

@endsection