<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <title>SMS</title>
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.11.2/css/all.css">
  <!-- Google Fonts Roboto -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap">
  @vite(['resources/sass/app.scss', 'resources/js/app.js', 'resources/css/app.css'])
</head>
<body class="mt-3">

        <header>
          <!-- Navbar -->
          <nav class="navbar navbar-expand-lg navbar-dark fixed-top scrolling-navbar">
            <div class="container">
              <a class="navbar-brand" href="/">
                <strong>SMS</strong>
              </a>
             
                <ul class="navbar-nav ms-auto pr-2">
                    <li style="color: #ffffff;">
                        @if(isset(Auth::user()->name)) 
                         пользователь: {{Auth::user()->name}}
                         @else <a class="nav-link mt-3" href="{{route('register')}}">{{ __('Регистрация') }}</a>
                         @endif
                    </li>      
                </ul>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent-7" aria-controls="navbarSupportedContent-7" aria-expanded="false" aria-label="Toggle navigation">
                menu
                </button>
              <div class="collapse navbar-collapse" id="navbarSupportedContent-7">
                <ul class="m-2 navbar-nav mr-auto " >
                <li class="nav-item active mt-2">
                @guest
                    @if (Route::has('login'))
                        
                            <a class="nav-link" href="{{ route('login') }}">{{ __('Вход в систему') }}</a>
                       
                    @endif
                @endguest  
                @can('view', auth()->user())  
                <form id="logout-form" action="{{ route('logout') }}" method="POST">    
                     @csrf
                    <input class="nav-item" type="submit" value="{{ __('Выйти') }}">  
                </form>
                </li>
                <li class="nav-item">
                    <a href="{{route('order.index')}}" class="nav-link">
                        К заявкам
                    </a></li>
                <li class="nav-item">
                    <a href="{{route('order.create')}}" class="nav-link">
                        Сформировать заявку
                    </a></li>
                <li class="nav-item">
                    <a href="{{route('order.search')}}" class="nav-link">
                        Поиск 
                    </a></li>
                
                    @if(auth()->user()->role == 'admin')
                <li class="nav-item">   
                    
                    <a href="{{route('admin.order.index')}}" class="nav-link">
                        Admin 
                    </a></li>
                    @endif
                    @endcan
                
                </ul>
              </div>
            </div>
          </nav>
          <!-- Navbar -->
<br>

                    @yield('content')
<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
 
</body>
</html>                