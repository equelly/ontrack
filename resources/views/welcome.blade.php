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
<style>
html,
body,
header,
.view {
  height: 100%;
}

@media (max-width: 740px) {
  html,
  body,
  header,
  .view {
    height: 1500px;
  }
}
@media (min-width: 800px) and (max-width: 850px) {
  html,
  body,
  header,
  .view {
    height: 1500px;
  }
}

.top-nav-collapse {
  background-color: rgba(125,180,210,0.5) !important;
}

.navbar:not(.top-nav-collapse) {
  background: transparent !important;
}

@media (max-width: 500px) {
  .navbar:not(.top-nav-collapse) {
    background: #3f51b5 !important;
  }
}

.rgba-gradient {
  background: -webkit-linear-gradient(45deg, rgba(0, 0, 0, 0.7), rgba(125,180,210,0.1) 100%);
  background: -webkit-gradient(linear, 45deg, from(rgba(0, 0, 0, 0.7), rgba(125,180,210,0.1) 100%));
  background: linear-gradient(to 45deg, rgba(0, 0, 0, 0.7), rgba(125,180,210,0.1) 100%);
}

.card {
  background-color: rgba(125,180,210,0.3);
}

.md-form label {
  color: #ffffff;
}

h6 {
  line-height: 2.5;
}
.btn  {
  color:#ffffff;
  background-color:rgba(125,180,210) ;
}


#roles {
	background-color:rgba(125,180,210,0.3) ;
	color:#ffffff;
	border-bottom: 1px solid white; 
}
h1 {
    font-size: 2.5em;
	color: #ffffff;
}

h2 {
    font-size: 2.0em;
	color: #ffffff;
}

p {
    font-size: 1.4em;
}
</style>
</head>
<body>

        <header>
          <!-- Navbar -->
          <nav class="navbar navbar-expand-lg navbar-dark fixed-top scrolling-navbar">
            <div class="container">
              <a class="navbar-brand" href="/home">
                <strong>SMS</strong>
              </a>
              <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent-7" aria-controls="navbarSupportedContent-7" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
              </button>
              <div class="collapse navbar-collapse" id="navbarSupportedContent-7">
                <ul class="navbar-nav mr-auto">
                  <li class="nav-item active">
                    <a class="nav-link" href="/admin1/">Войти в систему
                      <span class="sr-only">(current)</span>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </nav>
          <!-- Navbar -->
          <!-- Full Page Intro -->
          <div class="view" style="background-image: url('dist/img/quarry.jpg'); background-repeat: no-repeat; background-size: cover; background-position: center center;">
            <!-- Mask & flexbox options-->
            <div class="mask rgba-gradient align-items-center">
              <!-- Content --><br>
              <div class="container">
                <!--Grid row-->
                <div class="row mt-5">
                  <!--Grid column-->
                  <div class="col-md-6 mb-5 mt-md-0 mt-5 white-text text-center text-md-left">
                    <h1 class="m-3 h1-responsive font-weight-bold wow fadeInLeft" data-wow-delay="0.3s">Система мониторинга сервиса</h1>
                    
					<hr class="hr-light wow fadeInLeft" data-wow-delay="0.3s">
                    <h6 class="mb-4 wow fadeInLeft" data-wow-delay="0.3s">
					<h4 class="text-light">Проект для взаимодействия между эксплуатационным персоналом и персоналом занятым обслуживанием и ремонтом оборудования.</h4>
					
					 <p class="text-light"># Получение и предоставление информации 24/7</p>
                     <p class="text-light"># Ведение учета работ</p>
	                 <p class="text-light"># Снижение аварийных простоев оборудования
                    </p></h6>
                   
                  </div>
                  <!--Grid column-->
                  <!--Grid column-->
                  <div class="col-md-6 col-xl-5 mb-4">
                    <!--Form-->
                    <form method="POST" action="{{ route('login') }}">
                        @csrf
                    <div class="card wow fadeInRight" data-wow-delay="0.3s">
                      <div class="card-body">
                        <!--Header-->
                        <div class="text-center">
                          <h3 class="text-light" id="demo">Регистрация</h3>
                            
                          <hr class="hr-light">
                        </div>
                        <!--Body-->
                        
						<div class="md-form">
                          <i class="fas fa-user prefix text-light active"></i>
                            <input id="name" type="text" class="form-control @error('email') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>
                                @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                          <label for="name" class="active">Ваше имя</label>
                        </div>
                        <div class="md-form">
                          <i class="fas fa-envelope prefix text-light active"></i>
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                          <label for="email" class="active">Ваш email</label>
                        </div>
						<div class="md-form">
                          <label for="roles" id="roles"></label>
						  <select name="roles" id="roles"class="browser-default custom-select custom-select-lg mb-3">
                                <option selected class="option">Категория персонала</option>
                                <option value="эксплуатационный">эксплуатационный</option>
                                <option value="обслуживающий">обслуживающий</option>
                            </select>
                        </div>
					    <div class="md-form">
                          <i class="fas fa-lock prefix text-light active"></i>
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                          <label for="password">Пароль</label>
                        </div>
                        <div class="text-center white-text active mt-4">
                          <input type="hidden" name="action" value="login" >
						  <input class="btn form-control" name="submit" type="submit" value="Зарегистрироваться"></button>
                          <hr class="hr-light mb-3 mt-4">
                        </div>
                      </div>
                    </div>
                    <!--/.Form--></form>
                  </div>
                  <!--Grid column-->
                </div>
                <!--Grid row-->
              </div>
              <!-- Content -->
            </div>
            <!-- Mask & flexbox options-->
          </div>
          <!-- Full Page Intro -->
        </header>
        <!-- Main navigation -->
        <!--Main Layout-->
        <main>
          <div class="container">
            <!--Grid row-->
            <div class="row py-5">
              <!--Grid column-->
              <div class="col-md-12 text-center">
                <p>Этот проект создан обеспечить связь и взаимодействие между эксплуатационным персоналом и персоналом 
				занятым обслуживанием и ремонтом оборудования, для эффективного использования машин и механизмов
				при ведении горных работ.Доступность и простота предоставления и получения информации, возможность ведения учета 
				и отчета о выполненных работах и доставке ТМЦ - непосредственно "от первого лица". Легкость обработки полученой и 
				накопленной информации в базе данных дает возможность определить частоту возникновения работ по видам, конкретной 
				машине и по парку в целом.</p>
			</div>
              <!--Grid column-->
            </div>
            <!--Grid row-->
          </div>
        </main>
        <!--Main Layout-->

    

 <!-- End your project here-->
 
  <!-- jQuery -->
  <script type="text/javascript" src="/js/jquery.min.js"></script>
  <!-- Bootstrap tooltips -->
  <script type="text/javascript" src="/js/popper.min.js"></script>
  <!-- Bootstrap core JavaScript -->
  <script type="text/javascript" src="/js/bootstrap.min.js"></script>
  <!-- MDB core JavaScript -->
  <script type="text/javascript" src="/js/mdb.min.js"></script>
  <!-- Your custom scripts (optional) -->
  <script type="text/javascript"></script>
<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
 
</body>
</html>
