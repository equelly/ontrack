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
                    <a href="{{route('miners.index')}}" class="nav-link hover:bg-sky-600/50 pl-2">
                        Забои 
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
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mt-4" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
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
//checkbox active в работе / не в работе

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
document.addEventListener('DOMContentLoaded', function() {
    // ✅ НАЙДЁМ ВСЕ INPUT'Ы ОБЪЁМА ЗОН
    let volumeInputs = document.querySelectorAll('input[name*="][volume]"]');

    volumeInputs.forEach(function(input) {
        // ✅ ПРИ ФОКУСЕ - ОЧИЩАЕМ VALUE!
        input.addEventListener('focus', function() {
            // Сохраняем позицию курсора (если нужно)
            let cursorPos = this.selectionStart;

            // ✅ ОЧИЩАЕМ СТАРОЕ ЗНАЧЕНИЕ
            this.value = '';

            // Возвращаем курсор в начало
            this.setSelectionRange(0, 0);
        });

        // ✅ ПРИ КЛИКЕ (если фокус через клик)
        input.addEventListener('click', function() {
            // Если значение не пустое - очищаем
            if (this.value && this.value!== '') {
                this.value = '';
            }
        });

        // ✅ ОПЦИОНАЛЬНО: ПРОВЕРКА НА ПУСТОЕ ПОЛЕ ПРИ ПОТЕРЕ ФОКУСА
        input.addEventListener('blur', function() {
            // Если поле пустое после редактирования - можно оставить пустым или восстановить
            if (!this.value || this.value === '') {
                // this.value = '0'; // или оставить пустым для обязательной проверки
            }
        });
    });

});


// добавление новых зон
$(document).ready(function() {
    window.zoneCounter = window.zoneCounter || 0;

    $('#add-zone').click(function() {

        let template = $('.zone-template').first();
        if (template.length === 0) {
            
            return;
        }

        // ✅ КЛОНИРУЕМ
        let cloned = template.clone();
        cloned.removeClass('d-none zone-template').addClass('new-zone');
        cloned.show();

        // ✅ ПРАВИЛЬНЫЕ ИМЕНА ДЛЯ НОВЫХ ЗОН
        let index = window.zoneCounter;

        // Название зоны
        cloned.find('.zone-name').attr('name', `zones[new_${index}][name_zone]`);
        cloned.find('.zone-name').attr('id', `zone_id_new_${index}`);

        // Объем
        cloned.find('.zone-volume').attr('name', `zones[new_${index}][volume]`);
        cloned.find('.zone-volume').attr('id', `slider_new_${index}`);

        // Порода (один select)
        cloned.find('.zone-rocks').attr('name', `zones[new_${index}][rocks][]`);

        // Доставка
        cloned.find('.zone-delivery').attr('name', `zones[new_${index}][delivery]`);

        // Лоадер
        cloned.find('.zone-loader').attr('value', `new_${index}`);

        // ✅ ОЧИЩАЕМ ПОЛЯ
        cloned.find('.zone-name').val('');
        cloned.find('.zone-volume').val('0');
        cloned.find('.zone-rocks option:first').prop('selected', true);
        cloned.find('.zone-delivery').prop('checked', false);
        cloned.find('.zone-loader').prop('checked', false);

        // ✅ ДОБАВЛЯЕМ В КОНЕЦ
        $('tbody').append(cloned);

        window.zoneCounter++;

        
    }); 
});
// удаление зон 
function markZoneForDeletion(zoneId) {
    

    // Добавляем скрытое поле для удаления
    let form = document.querySelector('form');
    let hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'delete_zones[]';
    hiddenInput.value = zoneId;
    form.appendChild(hiddenInput);

    // Визуально скрываем строку
    let row = document.querySelector(`tr[data-zone-id="${zoneId}"]`);
    if (row) {

                // ✅ ПОЛУПРОЗРАЧНОСТЬ + КРАСНЫЙ ФОН
        row.style.opacity = '1';
        row.style.backgroundColor = '#f8d7da'; // Светло-красный фон
        row.style.transition = 'opacity 0.3s, background-color 0.3s'; // Плавная анимация
       
        let deleteBtn = row.querySelector('.mark-for-delete');
        
                // ✅ КНОПКА С НАДПИСЬЮ - С Z-INDEX!
        deleteBtn.innerHTML = '<small style="white-space: nowrap; opacity: 1!important;">после обновления зона будет удалена</small>';
        deleteBtn.disabled = true;

        // ✅ ПРАВИЛЬНЫЕ СТИЛИ С Z-INDEX
        deleteBtn.style.cssText = `
            background-color: #dc3545!important;
            color: white!important;
            opacity: 1!important;
            border: 1px solid #dc3545;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 12px;
            line-height: 1.2;
            white-space: nowrap;
            min-width: fit-content;
            max-width: 200px;

            /* ✅ Z-INDEX РАБОТАЕТ ТОЛЬКО С POSITION! */
            position: relative!important;
            z-index: 1000!important;
        `;

        // ✅ ТЕКСТ В КНОПКЕ ТОЖЕ С Z-INDEX
        let smallText = deleteBtn.querySelector('small');
        if (smallText) {
            smallText.style.cssText = `
                opacity: 1!important;
                color: white!important;
                font-size: 11px;
                white-space: nowrap;
                position: relative;
                z-index: 1000;
            `;
        }
    }

}
</script>
</body>
</html>                