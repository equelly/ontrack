@extends('layouts.app')
@section('content')

        <div class="flex justify-content-center mt-5">
       <h5>Расстояния до дампов</h5>
<ul>
    @foreach ($miner->dumps as $dump)
        <li>{{ $dump->name_dump }} — {{ $dump->pivot->distance_km }} км</li>
    @endforeach
</ul>

    </div> 
@endsection