<div class="main-nav">
    <!-- Sidebar Logo -->
    <div class="logo-box">
        <a href="{{ route('dashboard.index') }}" class="logo-dark">
            <img src="/images/logo-sm.png" class="logo-sm" alt="logo sm">
            <img src="/images/logo-dark.png" class="logo-lg" alt="logo dark">
        </a>

        <a href="{{ route('dashboard.index') }}"
            class="d-inline-flex align-items-center gap-1 text-decoration-none">
            <img src="/images/luky/logo.png" alt="LUKY logo" class="" style="height: 36px; width: auto; ">
            <span class="text-white fw-bold fs-3 text-uppercase ls-wide" style="letter-spacing: 1rem;">LUKY</span>
        </a>
    </div>

    <!-- Menu Toggle Button (sm-hover) -->
    <button type="button" class="button-sm-hover" aria-label="Show Full Sidebar">
        <iconify-icon icon="solar:double-alt-arrow-right-bold-duotone" class="button-sm-hover-icon"></iconify-icon>
    </button>

    <div class="scrollbar" data-simplebar>
        <ul class="navbar-nav" id="navbar-nav">

            <li class="menu-title">{{ __('common.general') }}</li>

            <li class="nav-item">
                <a class="nav-link" href="{{ route('dashboard.index') }}">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:widget-5-bold-duotone"></iconify-icon>
                     </span>
                    <span class="nav-text">{{ __('common.dashboard') }} </span>
                </a>
            </li>

            @canany(['view_clients', 'manage_clients'])
            <li class="nav-item">
                <a class="nav-link menu-arrow" href="#sidebarCustomers" data-bs-toggle="collapse" role="button"
                   aria-expanded="false" aria-controls="sidebarCustomers">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:users-group-two-rounded-bold-duotone"></iconify-icon>
                     </span>
                    <span class="nav-text"> {{ __('common.clients') }} </span>
                </a>
                <div class="collapse" id="sidebarCustomers">
                    <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('second', ['clients',  'list'])}}">{{ __('common.list') }}</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endcanany

            @canany(['view_providers', 'manage_providers', 'approve_providers'])
            <li class="nav-item">
                <a class="nav-link menu-arrow" href="#sidebarProviders" data-bs-toggle="collapse" role="button"
                   aria-expanded="false" aria-controls="sidebarProviders">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:shop-bold-duotone"></iconify-icon>
                     </span>
                    <span class="nav-text"> {{ __('common.providers') }} </span>
                </a>
                <div class="collapse" id="sidebarProviders">
                    <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('second', ['provider',  'list'])}}">{{ __('common.list') }}</a>
                        </li>
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('providers.pending') }}">
                                {{ __('common.pending_approvals') }}
                                @if(isset($pendingCount) && $pendingCount > 0)
                                    <span class="badge bg-warning text-dark">{{ $pendingCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('providers.pendingChanges') }}">
                                {{ __('common.pending_changes') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            @endcanany

            @canany(['view_bookings', 'manage_bookings'])
            <li class="nav-item">
                <a class="nav-link menu-arrow" href="#sidebarBookings" data-bs-toggle="collapse" role="button"
                   aria-expanded="false" aria-controls="sidebarBookings">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:clipboard-list-bold-duotone"></iconify-icon>
                     </span>
                    <span class="nav-text"> {{ __('common.bookings') }} </span>
                </a>
                <div class="collapse" id="sidebarBookings">
                    <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('bookings.index') }}">{{ __('common.all_bookings') }}</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endcanany

            @canany(['view_services', 'manage_services'])
            <li class="nav-item">
                <a class="nav-link menu-arrow" href="#sidebarServices" data-bs-toggle="collapse" role="button"
                   aria-expanded="false" aria-controls="sidebarServices">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:widget-5-bold-duotone"></iconify-icon>
                     </span>
                    <span class="nav-text"> {{ __('common.services') }} </span>
                </a>
                <div class="collapse" id="sidebarServices">
                    <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('services.index') }}">{{ __('common.all_services') }}</a>
                        </li>
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('categories.index') }}">{{ __('common.categories') }}</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endcanany

            @canany(['view_promo_codes', 'create_promo_codes', 'edit_promo_codes'])
            <li class="nav-item">
                <a class="nav-link menu-arrow" href="#sidebarPromo" data-bs-toggle="collapse" role="button"
                   aria-expanded="false" aria-controls="sidebarPromo">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:tag-bold"></iconify-icon>
                     </span>
                    <span class="nav-text"> {{ __('common.promo_codes') }} </span>
                </a>
                <div class="collapse" id="sidebarPromo">
                    <ul class="nav sub-navbar-nav">
                        @can('view_promo_codes')
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('promos.index') }}">{{ __('common.list') }}</a>
                        </li>
                        @endcan
                        @can('create_promo_codes')
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('promos.create') }}">{{ __('common.add') }}</a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcanany

            @canany(['view_users', 'manage_users', 'manage_roles'])
            <li class="nav-item">
                <a class="nav-link menu-arrow" href="#sidebarAdminRoles" data-bs-toggle="collapse" role="button"
                   aria-expanded="false" aria-controls="sidebarAdminRoles">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:user-speak-rounded-bold-duotone"></iconify-icon>
                     </span>
                    <span class="nav-text">{{ __('common.users_and_roles') }} </span>
                </a>
                <div class="collapse" id="sidebarAdminRoles">
                    <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('adminrole.users') }}">{{ __('common.users') }}</a>
                        </li>
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('adminrole.roles') }}">{{ __('common.roles') }}</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endcanany
            
            @canany(['view_reviews', 'manage_reviews'])
            <li class="nav-item">
                <a class="nav-link" href="{{ route('reviews.index') }}">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:star-bold"></iconify-icon>
                     </span>
                    <span class="nav-text"> {{ __('common.reviews') }} </span>
                </a>
            </li>
            @endcanany
            
            @canany(['send_notifications', 'manage_notifications'])
            <li class="nav-item">
                <a class="nav-link" href="{{ route('notifications.index') }}">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:bell-bing-bold"></iconify-icon>
                     </span>
                    <span class="nav-text"> {{ __('common.notifications') }} </span>
                </a>
            </li>
            @endcanany

            @canany(['view_payments', 'manage_payments'])
            <li class="nav-item">
                <a class="nav-link menu-arrow" href="#sidebarPayments" data-bs-toggle="collapse" role="button"
                   aria-expanded="false" aria-controls="sidebarPayments">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:card-bold-duotone"></iconify-icon>
                     </span>
                    <span class="nav-text"> {{ __('common.payment_management') }} </span>
                </a>
                <div class="collapse" id="sidebarPayments">
                    <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('second', ['payment', 'settings'])}}">{{ __('common.gateway_configuration') }}</a>
                        </li>
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('payments.transactions')}}">{{ __('common.transactions') }}</a>
                        </li>
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('payments.methods')}}">{{ __('common.payment_methods') }}</a>
                        </li>
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('payments.commissions')}}">{{ __('common.commission_tracking') }}</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endcanany

            @can('manage_static_pages')
            <li class="nav-item">
                <a class="nav-link menu-arrow" href="#sidebarContent" data-bs-toggle="collapse" role="button"
                   aria-expanded="false" aria-controls="sidebarContent">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:document-text-bold-duotone"></iconify-icon>
                     </span>
                    <span class="nav-text"> {{ __('common.content_management') }} </span>
                </a>
                <div class="collapse" id="sidebarContent">
                    <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('static-pages.index') }}">{{ __('common.static_pages') }}</a>
                        </li>
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('second', ['banners', 'banners'])}}">{{ __('common.banners') }}</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endcan

            <li class="nav-item">
                <a class="nav-link" href="{{ route('second', ['reports', 'reports'])}}">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:file-check-bold"></iconify-icon>
                     </span>
                    <span class="nav-text"> {{ __('common.reports') }} </span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="{{ route('second', ['settings', 'settings'])}}">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:settings-bold-duotone"></iconify-icon>
                     </span>
                    <span class="nav-text"> {{ __('common.settings') }} </span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link menu-arrow" href="#sidebarCustomercare" data-bs-toggle="collapse" role="button"
                   aria-expanded="false" aria-controls="sidebarCategory">
                     <span class="nav-icon">
                          <iconify-icon icon="solar:help-bold"></iconify-icon>
                     </span>
                    <span class="nav-text"> {{ __('common.customer_service') }} </span>
                </a>
                <div class="collapse" id="sidebarCustomercare">
                    <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('second', ['customerservices',  'tickets'])}}">{{ __('common.tickets') }}</a>
                        </li>
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('second', ['customerservices',  'chat'])}}">{{ __('common.chat') }}</a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </div>
</div>
