@extends('layouts.app')

@section('content')
<div class="view" style="background-image: url('dist/img/quarry.jpg'); background-repeat: no-repeat; background-size: cover; background-position: center center;">
            <!-- Mask & flexbox options-->
            
              <!-- Content --><br>
              <div class="container">
                <!--Grid row-->
                <div class="row mt-5">
                  <!--Grid column-->
                  <div class="col-md-6 mb-5 mt-md-0 mt-5 white-text text-center text-md-left">
                    <h1 class="m-3 h1-responsive font-weight-bold wow fadeInLeft" data-wow-delay="0.3s">Система мониторинга сервиса</h1>
                    
					<hr class="bg-white wow fadeInLeft" data-wow-delay="0.3s">
                    <div class="mb-4 wow fadeInLeft" data-wow-delay="0.3s">
					<h2 class="text-light">Проект для взаимодействия между эксплуатационным персоналом и персоналом занятым обслуживанием и ремонтом оборудования.</h2>
					
					 <p class="text-light"># Получение и предоставление информации 24/7</p>
                     <p class="text-light"># Ведение учета работ</p>
	                 <p class="text-light"># Снижение аварийных простоев оборудования
                    </p></div>
                   
                  </div>
                  <!--Grid column-->
                  <!--Grid column-->
                   <div class="col-md-6 col-xl-5 mb-4">
                    
                        <form  method="POST" action="{{ route('register') }}">
                        @csrf
                            <div class="card wow fadeInRight" data-wow-delay="0.3s">
                            <div class="card-body">
                            <div class="text-center">
                                <h2 class="text-light" id="demo">{{ __('Карточка регистрации') }}</h2>
                                    <hr style="background: white;">
                            </div>
                                <div class="md-form ">
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
                            </form>
                    </div>
            </div>
        </div>
        </div>
                </div>
                 
                  <!--Grid column-->
               
                <!--Grid row-->
             
              <!-- Content -->
           
            <!-- Mask & flexbox options-->
          
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

@endsection
