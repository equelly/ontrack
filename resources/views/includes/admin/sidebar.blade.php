<nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <!-- Add icons to the links using the .nav-icon class
               with font-awesome or any other icon font library -->

          <li class="nav-header">Панель администратора</li>
          <li class="nav-item">
            <a href="{{route('admin.order.create')}}" class="nav-link">
              <i class="nav-icon fas fa-book"></i>
              <p>
                Добавить заявку
              </p>
            </a>
          </li>
          <li class="nav-item">
            <a href="#" class="nav-link">
             
              <span class="badge badge-info">{{count($orders)}}</span>
              <p>
                Заявки
                
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="{{route('admin.order.showByCategory', 1)}}" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Текущие
                  <span class="badge badge-info right">{{count($cur_orders)}}</span>
                  </p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{route('admin.order.showByCategory', 2)}}" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Выполнено
                  <span class="badge badge-info right">{{count($exc_orders)}}</span>
                  </p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{route('admin.order.showByCategory', 3)}}" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Отклонено
                  <span class="badge badge-info right">{{count($den_orders)}}</span>
                  </p>
                </a>
              </li>
            </ul>
          </li>
          <li class="nav-item">
            <a href="#" class="nav-link">
            <span class="badge badge-info">{{count($mashines)}}</span>
              <p>
                Оборудование
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="{{route('admin.mashine.create')}}" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Добавить</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{route('admin.mashine.index')}}" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Удалить</p>
                </a>
              </li>

            </ul>
          </li>
          <li class="nav-item">
            <a href="#" class="nav-link">
            <span class="badge badge-info">{{count($sets)}}</span>
              <p>
                Комплектация
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="{{route('admin.set.create')}}" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Добавить</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{route('admin.set.index')}}" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Удалить</p>
                </a>
              </li>

            </ul>
          </li>
          <li class="nav-item">
            <a href="#" class="nav-link">
            <span class="badge badge-info">{{count($users)}}</span>
              <p>
                Пользователи
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="{{route('admin.users.index')}}" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Удалить</p>
                </a>
              </li>

            </ul>
          </li>

        </ul>
      </nav>