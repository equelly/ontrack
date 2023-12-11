@extends('layouts.main')
@section('content')
<div class="mt-1 flex justify-content-center p-2 bg-gray-200 "  style="border-bottom: 2px solid #14B8A6; font-size: 1.2rem">
    <h1  class="flex justify-content-end p-2" style="width: 40rem">Всего текущих заявок:  </h1>
</div>
    <div class="flex justify-content-center mt-1">
<form action="" method="GET">
    <div class="row g-3 align-items-center">
        <div class="card shadow p-3 m-3 bg-white rounded" style="width: 20rem">
            <p><div class="col-auto">
                <label for="category_id" class="col-form-label">Категория </label>
            </div>
            <div class="col-auto">
            <select class = "form-control w-60" name = "category_id" id="category_id">
                    <option></option>
                    @foreach($categories as $category)
                    <option value="{{$category->id}}">{{$category->title}}</option>
                    @endforeach
                </select>
            </div></p>
            <p><div class="col-auto">
                <label for="user_id" class="col-form-label">Автор </label>
            </div>
            <div class="col-auto">
            <select class = "form-control w-60" name = "users" id="user">
                    <option></option>
                    @foreach($users as $user)
                    <option value="{{$user->id}}">{{$user->name}}</option>
                    @endforeach
                </select>
            </div></p>
            <p><div class="col-auto">
                <label for="mashine_id" class="col-form-label">Оборудование</label>
            </div>
            <div class="col-auto">
                <select class = "form-control w-60" name = "mashine_id" id="mashine">
                    <option></option>
                    @foreach($mashines as $mashine)
                    <option value="{{$mashine->id}}">{{$mashine->number}}</option>
                    @endforeach
                </select>
            </div></p>
            <p><div class="col-auto">
                <label for="date_id" class="col-form-label">Дата </label>
            </div>
            <div class="col-auto">
                <input type="date" id="date_id" class="form-control w-60" name="created_at">
            </div></p>
            <p><div class="col-auto">
                <label for="content_id" class="col-form-label">фрагмент заявки</label>
            </div>
            <div class="col-auto">
                <input type="text" id="content_id" class="form-control" name="content">
            </div></p>
            <input type="hidden" id="content_id" class="form-control" name="action" value="search">
        
                <div class="flex justify-end">
                  <button id = "button_id" type="submit" class=" w-60 inline-block text-sm px-4 py-2 leading-none border rounded text-white border-teal-400 bg-teal-500 hover:text-teal-500 hover:bg-white mt-4 lg:mt-0 callout mb-1 w-90">найти</button>
                </div>
        </div>
    </div>
</form>
    </div> 
@endsection