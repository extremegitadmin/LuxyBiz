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
  
      @canany([
          'admin.tour-categories.view',
          'admin.tours.view',
          'admin.objectives.view',
          'admin.departures.view',
          'admin.departure-operations.view'
      ])
      <li class="menu-label">CATALOGUE</li>
      @endcanany

      @can('admin.tour-categories.view')
      <li class="{{ request()->routeIs('admin.tour-categories.*') ? 'mm-active' : '' }}">
          <a href="{{ route('admin.tour-categories.index') }}">
              <div class="parent-icon">
                  <i class="material-icons-outlined">category</i>
              </div>
              <div class="menu-title">Tour Categories</div>
          </a>
      </li>
      @endcan

      @can('admin.tours.view')
      <li class="{{ request()->routeIs('admin.tours.*') ? 'mm-active' : '' }}">
          <a href="{{ route('admin.tours.index') }}">
              <div class="parent-icon">
                  <i class="material-icons-outlined">inventory_2</i>
              </div>
              <div class="menu-title">Tours</div>
          </a>
      </li>
      @endcan

      @can('admin.objectives.view')
      <li class="{{ request()->routeIs('admin.objectives.*') ? 'mm-active' : '' }}">
          <a href="{{ route('admin.objectives.index') }}">
              <div class="parent-icon">
                  <i class="material-icons-outlined">flag</i>
              </div>
              <div class="menu-title">Objectives</div>
          </a>
      </li>
      @endcan

      <!-- @can('admin.departures.view')
      <li class="{{ request()->routeIs('admin.departures.*') ? 'mm-active' : '' }}">
          <a href="{{ route('admin.departures.index') }}">
              <div class="parent-icon">
                  <i class="material-icons-outlined">event</i>
              </div>
              <div class="menu-title">Departures</div>
          </a>
      </li>
      @endcan -->

      <!-- @can('admin.departure-operations.view')
      <li class="{{ request()->routeIs('admin.departure-operations.*') ? 'mm-active' : '' }}">
          <a href="{{ route('admin.departure-operations.index') }}">
              <div class="parent-icon">
                  <i class="material-icons-outlined">event_available</i>
              </div>
              <div class="menu-title">Departure Operations</div>
          </a>
      </li>
      @endcan     -->
      <!-- END TOUR -->

      @canany(['admin.bookings.view', 'admin.assisted.view', 'admin.customers.view', 'admin.payments.view', 'admin.payments.events.view'])
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
      @can('admin.assisted.view')
      <li class="{{ request()->routeIs('admin.assisted.*') ? 'mm-active' : '' }}">
        <a href="{{ route('admin.assisted.index') }}">
          <div class="parent-icon"><i class="material-icons-outlined">support_agent</i>
          </div>
          <div class="menu-title">Assisted bookings</div>
        </a>
      </li>
      @endcan
       @can('admin.customers.view')
      <li class="{{ request()->routeIs('admin.customers.*') ? 'mm-active' : '' }}">
        <a href="{{ route('admin.customers.index') }}">
          <div class="parent-icon"><i class="material-icons-outlined">groups</i>
          </div>
          <div class="menu-title">Customers</div>
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
              <i class="material-icons-outlined">history</i>
          </div>
          <div class="menu-title">Payment Activity</div>
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
      
      @canany(['admin.roles.view', 'admin.users.view'])
      <li class="menu-label">ROLES & PERMISSIONS</li>
      @endcanany

      @can('admin.roles.view')
      <li class="{{ request()->routeIs('admin.roles.*') ? 'mm-active' : '' }}">
        <a href="{{ route('admin.roles.index') }}">
          <div class="parent-icon"><i class="material-icons-outlined">lock</i></div>
          <div class="menu-title">Roles</div>
        </a>
      </li>
      @endcan

      @can('admin.users.view')
      <li class="{{ request()->routeIs('admin.users.*') ? 'mm-active' : '' }}">
        <a href="{{ route('admin.users.index') }}">
          <div class="parent-icon"><i class="material-icons-outlined">person</i></div>
          <div class="menu-title">Users</div>
        </a>
      </li>
      @endcan
      <!-- CONTENT MANAGEMENT -->
      @canany([
          'admin.cms.pages.view',
          'admin.cms.homepage.view',
          'admin.cms.testimonials.view',
          'admin.cms.trust-items.view',
          'admin.cms.menus.view',
          'admin.cms.site-settings.view',
          'admin.media.view'
      ])
      <li class="menu-label">CONTENT MANAGEMENT</li>
      @endcanany

      @can('admin.cms.pages.view')
      <li class="{{ request()->routeIs('admin.cms.pages.*') ? 'mm-active' : '' }}">
          <a href="{{ route('admin.cms.pages.index') }}">
              <div class="parent-icon">
                  <i class="material-icons-outlined">description</i>
              </div>
              <div class="menu-title">CMS Pages</div>
          </a>
      </li>
      @endcan

      @can('admin.cms.homepage.view')
      <li class="{{ request()->routeIs('admin.cms.homepage.*') ? 'mm-active' : '' }}">
          <a href="{{ route('admin.cms.homepage.edit') }}">
              <div class="parent-icon">
                  <i class="material-icons-outlined">home</i>
              </div>
              <div class="menu-title">Homepage</div>
          </a>
      </li>
      @endcan

      @can('admin.cms.testimonials.view')
      <li class="{{ request()->routeIs('admin.cms.testimonials.*') ? 'mm-active' : '' }}">
          <a href="{{ route('admin.cms.testimonials.index') }}">
              <div class="parent-icon">
                  <i class="material-icons-outlined">reviews</i>
              </div>
              <div class="menu-title">Testimonials</div>
          </a>
      </li>
      @endcan

      @can('admin.cms.trust-items.view')
      <li class="{{ request()->routeIs('admin.cms.trust-items.*') ? 'mm-active' : '' }}">
          <a href="{{ route('admin.cms.trust-items.index') }}">
              <div class="parent-icon">
                  <i class="material-icons-outlined">shield</i>
              </div>
              <div class="menu-title">Trust Items</div>
          </a>
      </li>
      @endcan

      @can('admin.cms.menus.view')
      <li class="{{ request()->routeIs('admin.cms.menus.*') ? 'mm-active' : '' }}">
          <a href="{{ route('admin.cms.menus.index') }}">
              <div class="parent-icon">
                  <i class="material-icons-outlined">menu</i>
              </div>
              <div class="menu-title">Menus & Footer</div>
          </a>
      </li>
      @endcan

      @can('admin.cms.site-settings.view')
      <li class="{{ request()->routeIs('admin.cms.site-settings.*') ? 'mm-active' : '' }}">
          <a href="{{ route('admin.cms.site-settings.edit') }}">
              <div class="parent-icon">
                  <i class="material-icons-outlined">settings</i>
              </div>
              <div class="menu-title">Site Settings</div>
          </a>
      </li>
      @endcan

      @can('admin.media.view')
      <li class="{{ request()->routeIs('admin.media.*') ? 'mm-active' : '' }}">
          <a href="{{ route('admin.media.index') }}">
              <div class="parent-icon">
                  <i class="material-icons-outlined">perm_media</i>
              </div>
              <div class="menu-title">Media Library</div>
          </a>
      </li>
      @endcan
      @can('admin.insurance.view')
      <li class="menu-label">SETTINGS</li>
      <li class="{{ request()->routeIs('admin.insurance.*') ? 'mm-active' : '' }}">
        <a href="{{ route('admin.insurance.index') }}">
          <div class="parent-icon"><i class="material-icons-outlined">verified_user</i></div>
          <div class="menu-title">Insurance Options</div>
        </a>
      </li>
      @endcan
    </ul>
    <!--end navigation-->
  </div>
</aside>
<!--end sidebar-->