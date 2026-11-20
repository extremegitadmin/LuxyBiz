<?php
  $url = Request::segment(2); 
?>
<!--start sidebar-->
<aside class="sidebar-wrapper" data-simplebar="true">
  <div class="sidebar-header">
    <div class="logo-icon">
      <img src="{{asset('assets/images/logo-icon.png')}}" class="logo-img" alt="">
    </div>
    <div class="logo-name flex-grow-1">
      <h5 class="mb-0">LuxyBiz</h5>
    </div>
    <div class="sidebar-close">
      <span class="material-icons-outlined">close</span>
    </div>
  </div>
  <div class="sidebar-nav">
    <!--navigation-->
    <ul class="metismenu" id="sidenav">
      <li @if($url == "dashboard") class="active mm-active" @endif>
        <a href="{{route('admin.dashboard')}}">
          <div class="parent-icon"><i class="material-icons-outlined">home</i>
          </div>
          <div class="menu-title">Dashboard</div>
        </a>
      </li>          
      <li class="menu-label">CATALOGUE</li>
      <li>
        <a href="{{ route('admin.tour-categories.index') }}">
            <div class="parent-icon">
                <i class="material-icons-outlined">category</i>
            </div>
            <div class="menu-title">Tour Categories</div>
        </a>
      </li>
      <li>
        <a href="{{ route('admin.tours.index') }}">
          <div class="parent-icon"><i class="material-icons-outlined">inventory_2</i>
          </div>
          <div class="menu-title">Tours</div>
        </a>
      </li>
      <li class="{{ request()->routeIs('admin.departure-operations.*') ? 'mm-active' : '' }}">
    <a href="{{ route('admin.departure-operations.index') }}">
        <div class="parent-icon">
            <i class="material-icons-outlined">event_available</i>
        </div>
        <div class="menu-title">Departure Operations</div>
    </a>
</li>
      @canany(['admin.bookings.view', 'admin.payments.view', 'admin.payments.events.view'])
      <li class="menu-label">BOOKINGS</li>
      @endcanany
      @can('admin.bookings.view')
      <li class="{{ request()->routeIs('admin.bookings.*') ? 'mm-active' : '' }}">
        <a href="{{ route('admin.bookings.index') }}">
          <div class="parent-icon"><i class="material-icons-outlined">confirmation_number</i>
          </div>
          <div class="menu-title">Bookings</div>
        </a>
      </li>
      @endcan
      @can('admin.payments.view')
      <li class="{{ request()->routeIs('admin.payments.index', 'admin.payments.show') ? 'mm-active' : '' }}">
        <a href="{{ route('admin.payments.index') }}">
          <div class="parent-icon">
              <i class="material-icons-outlined">payments</i>
          </div>
          <div class="menu-title">Payments</div>
        </a>
      </li>
      @endcan
      @can('admin.payments.events.view')
      <li class="{{ request()->routeIs('admin.payments.events') ? 'mm-active' : '' }}">
        <a href="{{ route('admin.payments.events') }}">
          <div class="parent-icon">
              <i class="material-icons-outlined">webhook</i>
          </div>
          <div class="menu-title">Webhook events</div>
        </a>
      </li>
      @endcan
      @can('admin.settings.gateway.view')
      <li class="{{ request()->routeIs('admin.settings.gateway*') ? 'mm-active' : '' }}">
        <a href="{{ route('admin.settings.gateway') }}">
          <div class="parent-icon">
              <i class="material-icons-outlined">credit_card</i>
          </div>
          <div class="menu-title">Payment gateways</div>
        </a>
      </li>
      @endcan
      @can('admin.settings.cancellation.view')
      <li class="{{ request()->routeIs('admin.settings.cancellation*') ? 'mm-active' : '' }}">
        <a href="{{ route('admin.settings.cancellation') }}">
          <div class="parent-icon">
              <i class="material-icons-outlined">event_busy</i>
          </div>
          <div class="menu-title">Cancellation rules</div>
        </a>
      </li>
      @endcan
      @can('admin.cancellations.view')
      <li class="{{ request()->routeIs('admin.cancellations*') ? 'mm-active' : '' }}">
        <a href="{{ route('admin.cancellations.index') }}">
          <div class="parent-icon">
              <i class="material-icons-outlined">cancel</i>
          </div>
          <div class="menu-title">
            Cancellations
            @if (($pendingCancellations = \App\Models\CancellationRequest::pendingCount()) > 0)
              <span class="badge bg-warning text-dark ms-1">{{ $pendingCancellations }}</span>
            @endif
          </div>
        </a>
      </li>
      @endcan
      <li class="menu-label">ROLES & PERMISSIONS</li>
      
      <li @if($url == "roles") class="active mm-active" @endif>
        <a href="{{route('admin.roles.index')}}">
          <div class="parent-icon"><i class="material-icons-outlined">lock</i>
          </div>
          <div class="menu-title">Roles</div>
        </a>
      </li> 
      <li @if($url == "users") class="active mm-active" @endif>
        <a href="{{route('admin.roles.index')}}">
          <div class="parent-icon"><i class="material-icons-outlined">person</i>
          </div>
          <div class="menu-title">Users</div>
        </a>
      </li> 
    </ul>
    <!--end navigation-->
  </div>
</aside>
<!--end sidebar-->