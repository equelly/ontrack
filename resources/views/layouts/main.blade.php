<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">
    <!-- font awesome cdn link  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
    @vite(['resources/sass/app.scss', 'resources/js/app.js', 'resources/css/app.css'])
   
    <title>SMS</title>
</head>
<body>
<nav class="flex items-center justify-between flex-wrap bg-teal-500 p-6">
  <div class="flex items-center flex-shrink-0 text-white mr-6">
    <svg class="fill-current h-8 w-8 mr-2" width="54" height="54" viewBox="0 0 54 54" xmlns="http://www.w3.org/2000/svg"><path d="M13.5 22.1c1.8-7.2 6.3-10.8 13.5-10.8 10.8 0 12.15 8.1 17.55 9.45 3.6.9 6.75-.45 9.45-4.05-1.8 7.2-6.3 10.8-13.5 10.8-10.8 0-12.15-8.1-17.55-9.45-3.6-.9-6.75.45-9.45 4.05zM0 38.3c1.8-7.2 6.3-10.8 13.5-10.8 10.8 0 12.15 8.1 17.55 9.45 3.6.9 6.75-.45 9.45-4.05-1.8 7.2-6.3 10.8-13.5 10.8-10.8 0-12.15-8.1-17.55-9.45-3.6-.9-6.75.45-9.45 4.05z"/></svg>
    <span class="font-semibold text-xl tracking-tight">SMS</span>
  </div>
  <div class="block lg:hidden">
    <button class="flex items-center px-3 py-2 border rounded text-teal-200 border-teal-400 hover:text-white hover:border-white" onclick="menuNav()">
      <svg class="fill-current h-3 w-3" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><title>Menu</title><path d="M0 3h20v2H0V3zm0 6h20v2H0V9zm0 6h20v2H0v-2z"/></svg>
    </button>
  </div>
  <div class="blok hidden w-full block flex-grow lg:flex lg:items-center lg:w-auto" id="id_menu">
    <div class="text-sm lg:flex-grow">
      <a href="{{route('order.index')}}" class="block mt-4 lg:inline-block lg:mt-0 text-teal-200 hover:text-white mr-4">
        К заявкам
      </a>
      <a href="{{route('order.create')}}" class="block mt-4 lg:inline-block lg:mt-0 text-teal-200 hover:text-white mr-4">
        Сформировать заявку
      </a>
      <a href="{{route('order.search')}}" class="block mt-4 lg:inline-block lg:mt-0 text-teal-200 hover:text-white">
        Поиск 
      </a>
    </div>
    
  </div>
</nav>
<body class="bg-gray-100">
  

    @yield('content')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
</body>
<script>
  //функция управления navbar
  function menuNav() {
        let menu = document.getElementById('id_menu');
          if (menu.style.display === 'block') {
              menu.style.display = ('none');
          } else {
              menu.style.display = ('block');
          }
  }
window.onscroll = () =>{
	document.getElementById('id_menu').style.display = ('none');
}


/*
let submit = document.querySelector("#button_id")
submit.addEventListener('click', function(event){

  let f_content = document.getElementById('content_id');
  let checks = document.querySelectorAll("input.form-check-input");
  let checked = [];

  for (let i = 0; i < checks.length; i++) {
    if(checks[i].checked == true){
  
      checked.push(checks[i]);
    }
  }
  //
  
  if(f_content.value == '' && checked.length == 0){
    alert('Попытка отправить не заполненую форму!\nзаполните текстовое поле или выберите \n необходимые позиции в комплектации')
  //предотвратим отправку пустой формы на сервер
    event.preventDefault();
  };
  
  
})*/
</script>
  </body>
</html>