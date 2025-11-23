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
              <a class="navbar-brand ml-3" href="/">
                <strong>SMS</strong>
              </a>
             
                <ul class="navbar-nav ms-auto pr-2">
                    <li style="color: #ffffff;">
                        @if(isset(Auth::user()->name)) 
                          {{Auth::user()->name}}
                         @else <a class="nav-link mt-2" href="{{route('register')}}">{{ __('Регистрация') }}</a>
                         @endif
                    </li>      
                </ul>
                <button class="navbar-toggler mr-3" type="button" data-toggle="collapse" data-target="#navbarSupportedContent-7" aria-controls="navbarSupportedContent-7" aria-expanded="false" aria-label="Toggle navigation">
                menu
                </button>
              <div class="collapse navbar-collapse" id="navbarSupportedContent-7">
                <ul class="flex justify-around m-2 navbar-nav mr-auto ">
                  <li class="nav-item active mt-2">
                  @if(!(auth()->user()) and Route::has('login'))
                      
                          
                              <a class="nav-link  hover:bg-violet-400" href="{{ route('login') }}">{{ __('Вход в систему') }}</a>
                        
                      @endif
                  </li>
                  
                  @if((auth()->user() !== null))  
                  <li class="nav-item active">
                  <form action="{{ route('logout') }}" method="POST">    
                      @csrf
                      <input class="nav-link hover:bg-violet-400" type="submit" value="{{ __('Выйти') }}">  
                  </form>
                  </li>
                </ul> 
                <ul  class="flex justify-around m-2 navbar-nav mr-auto ">
                <li class="nav-item ml-3">
                    <a href="{{route('order.index')}}" class="nav-link hover:bg-sky-600/50 pl-2">
                        К заявкам
                    </a></li>
                <li class="nav-item item ml-3">
                    <a href="{{route('order.create')}}" class="nav-link hover:bg-sky-600/50 pl-2">
                        Сформировать заявку
                    </a></li>
                <li class="nav-item item ml-3">
                    <a href="{{route('order.search')}}" class="nav-link  hover:bg-sky-600/50 pl-2">
                        Поиск 
                    </a></li>
                <li class="nav-item item ml-3">
                    <a href="{{route('dump.index')}}" class="nav-link hover:bg-sky-600/50 pl-2">
                        Перегрузки 
                    </a></li>
                <li class="nav-item item ml-3">
                    <a href="{{route('distribution.index')}}" class="nav-link hover:bg-sky-600/50 pl-2">
                        Грузопотоки
                    </a></li>
                
                    @if(auth()->user()->role == 'admin')
                <li class="nav-item item ml-3">   
                    
                    <a href="{{route('admin.order.index')}}" class="nav-link hover:bg-sky-600/50 pl-2">
                        Admin 
                    </a></li>
                    @endif
                    @endif
                
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
<script>
// ✅ ФУНКЦИЯ ПЕРЕКЛЮЧЕНИЯ РЕЖИМА
function changeSortMode() {
    const select = document.getElementById('sort-mode');
    const newMode = select.value;

    // ✅ ЛОАДИНГ
    select.innerHTML = '<option>⏳ Загрузка...⏳⏳⏳</option>';

    // ✅ ЧИСТЫЙ URL - только mode
    const baseUrl = window.location.pathname; // /dump/distribution
    const newUrl = baseUrl + '?mode=' + newMode;

    window.location.href = newUrl;
}

// ✅ ЦВЕТА ДЛЯ РЕЖИМОВ
function getModeColor(mode) {
    const colors = {
        'balance': '#e3f2fd',    // ← Голубой
        'volume': '#e8f5e8',     // ← Зелёный
        'distance': '#fff3e0'    // ← Оранжевый
    };
    return colors[mode] || '#f3e5f5';
}

// ✅ ЛОАДИНГ (опционально)
function showLoading() {
    const select = document.getElementById('sort-mode');
    const originalText = select.innerHTML;
    select.innerHTML = '<option>⏳ Загрузка...</option>';
    setTimeout(() => {
        select.innerHTML = originalText;
    }, 500);
}
 document.addEventListener('DOMContentLoaded', () => {
  // Получаем все input с id, начинающимся на "slider_"
  document.querySelectorAll('input[id^="slider_"]').forEach(input => {
    input.addEventListener('input', function() {
        const max = 30;
        let value = Number(this.value);
        if (value > max) {
        alert(`Максимальное значение — ${max}. Значение будет установлено в ${max}.`);
        value = max;
        this.value = max;
        } else if (value < 0) {
        value = 0;
        this.value = 0;
        }
        
      const zoneId = this.id.split('_'); // получаем id зоны slider из полученного массива (второй элемент с индексом 1) 
       
                   
      const span = document.getElementById(`value_${zoneId[1]}`); 
      if(span) {
          const value = Number(this.value);
          span.style.width = (value * 0.1) + 'rem'; // длина столбика
        //span.textContent = this.value;                     // обновляем значение span рядом
      }
    });
  });
});
//checkbox active в работе / не в работе
document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('active');
        const textSpan = document.getElementById('activeText');

        // Функция для обновления текста
        function updateLabel() {
            if (checkbox.checked) {
                textSpan.textContent = 'в работе';
                textSpan.classList.remove('text-muted');
                textSpan.classList.add('text-success');
            } else {
                textSpan.textContent = 'не в работе';
                textSpan.classList.remove('text-success');
                textSpan.classList.add('text-muted');
            }
        }

        // Обновляем при загрузке
        updateLabel();

        // Обновляем при клике
        checkbox.addEventListener('change', updateLabel);
    });
</script>
</body>
</html>                