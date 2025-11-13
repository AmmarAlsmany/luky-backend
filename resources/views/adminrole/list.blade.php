@extends('layouts.vertical', ['title' => __('users.roles_list')])

@section('content')


<!-- View Role Modal (read-only) -->
<div class="modal fade" id="roleViewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header">
        <h5 class="modal-title d-flex align-items-center gap-2">
          <iconify-icon icon="solar:shield-user-bold-duotone" class="text-primary fs-20"></iconify-icon>
          {{ __('users.view_role') }}
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Body -->
      <div class="modal-body">

        <!-- Role summary -->
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
              <iconify-icon icon="solar:shield-user-bold-duotone" class="text-primary fs-20"></iconify-icon>
              <h6 class="mb-0">Workspace Manager</h6>
              <span class="badge bg-success-subtle text-success">{{ __('users.active') }}</span>
            </div>
            <small class="text-muted">{{ __('users.last_updated_time', ['time' => 'Sep 10, 2025 • 14:22']) }}</small>
          </div>

          <div class="card-body">
            <div class="row g-3">

              <!-- Description -->
              <div class="col-12">
                <label class="form-label text-muted mb-1">{{ __('users.description') }}</label>
                <p class="text-muted mb-0">
                  Manages providers, promos, and user access within a workspace. Ideal for supervising marketplace content and team permissions.
                </p>
              </div>

              <!-- Assigned Users (horizontal scroll) -->
              <div class="col-12">
                <label class="form-label text-muted mb-1">{{ __('users.assigned_users') }}</label>
                <div class="rounded p-2"
                     style="overflow-x:auto; overflow-y:hidden; white-space:nowrap; -webkit-overflow-scrolling:touch;">
                  <div class="d-inline-flex flex-nowrap gap-2 py-1">

                    <!-- 1 -->
                    <div class="d-inline-flex align-items-center p-2 border rounded me-2" style="min-width:240px;">
                      <img src="/images/users/avatar-4.jpg" class="rounded-circle me-2" alt="Aisha Khan"
                           style="width:40px;height:40px;object-fit:cover;">
                      <div>
                        <div class="fw-semibold">Aisha Khan</div>
                        <small class="text-muted">aisha@luky.app</small>
                      </div>
                    </div>

                    <!-- 2 -->
                    <div class="d-inline-flex align-items-center p-2 border rounded me-2" style="min-width:240px;">
                      <span class="rounded-circle d-inline-flex align-items-center justify-content-center me-2 bg-danger-subtle text-danger fw-bold"
                            style="width:40px;height:40px;">P</span>
                      <div>
                        <div class="fw-semibold">Peter Malik</div>
                        <small class="text-muted">peter@luky.app</small>
                      </div>
                    </div>

                    <!-- 3 -->
                    <div class="d-inline-flex align-items-center p-2 border rounded me-2" style="min-width:240px;">
                      <img src="/images/users/avatar-3.jpg" class="rounded-circle me-2" alt="Omar Saleh"
                           style="width:40px;height:40px;object-fit:cover;">
                      <div>
                        <div class="fw-semibold">Omar Saleh</div>
                        <small class="text-muted">omar@luky.app</small>
                      </div>
                    </div>

                    <!-- 4 -->
                    <div class="d-inline-flex align-items-center p-2 border rounded me-2" style="min-width:240px;">
                      <img src="/images/users/avatar-6.jpg" class="rounded-circle me-2" alt="Lama Al Saud"
                           style="width:40px;height:40px;object-fit:cover;">
                      <div>
                        <div class="fw-semibold">Lama Al Saud</div>
                        <small class="text-muted">lama@luky.app</small>
                      </div>
                    </div>

                    <!-- 5 -->
                    <div class="d-inline-flex align-items-center p-2 border rounded me-2" style="min-width:240px;">
                      <img src="/images/users/avatar-1.jpg" class="rounded-circle me-2" alt="Noura Al Zahrani"
                           style="width:40px;height:40px;object-fit:cover;">
                      <div>
                        <div class="fw-semibold">Noura Al Zahrani</div>
                        <small class="text-muted">noura@luky.app</small>
                      </div>
                    </div>

                    <!-- 6 -->
                    <div class="d-inline-flex align-items-center p-2 border rounded me-2" style="min-width:240px;">
                      <img src="/images/users/avatar-7.jpg" class="rounded-circle me-2" alt="Khalid Al Harbi"
                           style="width:40px;height:40px;object-fit:cover;">
                      <div>
                        <div class="fw-semibold">Khalid Al Harbi</div>
                        <small class="text-muted">khalid@luky.app</small>
                      </div>
                    </div>

                    <!-- 7 -->
                    <div class="d-inline-flex align-items-center p-2 border rounded me-2" style="min-width:240px;">
                      <img src="/images/users/avatar-8.jpg" class="rounded-circle me-2" alt="Maha Al Qahtani"
                           style="width:40px;height:40px;object-fit:cover;">
                      <div>
                        <div class="fw-semibold">Maha Al Qahtani</div>
                        <small class="text-muted">maha@luky.app</small>
                      </div>
                    </div>

                    <!-- 8 -->
                    <div class="d-inline-flex align-items-center p-2 border rounded" style="min-width:240px;">
                      <span class="rounded-circle d-inline-flex align-items-center justify-content-center me-2 bg-info-subtle text-info fw-bold"
                            style="width:40px;height:40px;">S</span>
                      <div>
                        <div class="fw-semibold">Saeed Faraj</div>
                        <small class="text-muted">saeed@luky.app</small>
                      </div>
                    </div>

                  </div>
                </div>
              </div>
              <!-- /Assigned Users -->

            </div> <!-- /.row -->
          </div> <!-- /.card-body -->
        </div> <!-- /.card -->

        <!-- Permissions (read-only) -->
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white">
            <h6 class="mb-0">{{ __('users.permissions') }}</h6>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead class="bg-light-subtle">
                  <tr>
                    <th>{{ __('users.module') }}</th>
                    <th class="text-center">{{ __('users.create') }}</th>
                    <th class="text-center">{{ __('users.read') }}</th>
                    <th class="text-center">{{ __('users.update') }}</th>
                    <th class="text-center">{{ __('users.delete') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="fw-semibold">{{ __('users.users_admins') }}</td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" checked disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" checked disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" disabled></td>
                  </tr>
                  <tr>
                    <td class="fw-semibold">{{ __('users.roles_permissions') }}</td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" checked disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" checked disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" checked disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" checked disabled></td>
                  </tr>
                  <tr>
                    <td class="fw-semibold">{{ __('users.providers') }}</td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" checked disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" checked disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" checked disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" disabled></td>
                  </tr>
                  <tr>
                    <td class="fw-semibold">{{ __('users.promos') }}</td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" checked disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" checked disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" checked disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" disabled></td>
                  </tr>
                  <tr>
                    <td class="fw-semibold">{{ __('users.billing') }}</td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" checked disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" disabled></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" disabled></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div> <!-- /.card -->

      </div> <!-- /.modal-body -->

      <!-- Footer -->
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">{{ __('users.close') }}</button>
        
      </div>
    </div> <!-- /.modal-content -->
  </div> <!-- /.modal-dialog -->
</div> <!-- /.modal -->



<!-- Create Role Modal -->
<div class="modal fade" id="roleCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title d-flex align-items-center gap-2">
          <iconify-icon icon="solar:shield-user-bold-duotone" class="text-primary"></iconify-icon>
          {{ __('users.create_role') }}
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <!-- 1) Role Details -->
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">1. {{ __('users.role_details') }}</h6>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="role_name" class="form-label">{{ __('users.role_name') }} <span class="text-danger">*</span></label>
                <input type="text" id="role_name" class="form-control" placeholder="{{ __('users.role_name_example') }}">
              </div>

              <div class="col-md-6">
                <label class="form-label d-block">{{ __('users.role_status') }}</label>
                <div class="d-flex gap-3">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="role_status" id="role_status_active" value="Active" checked>
                    <label class="form-check-label" for="role_status_active">{{ __('users.active') }}</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="role_status" id="role_status_inactive" value="Inactive">
                    <label class="form-check-label" for="role_status_inactive">{{ __('users.inactive') }}</label>
                  </div>
                </div>
              </div>

              <div class="col-12">
                <label for="role_desc" class="form-label">{{ __('users.description_optional') }}</label>
                <textarea id="role_desc" class="form-control" rows="2"
                          placeholder="{{ __('users.description_placeholder') }}"></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- 2) Permissions Matrix -->
        <div class="card">
          <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0">2. {{ __('users.permissions') }}</h6>
            <!-- quick toggles -->
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-light btn-sm" id="perm_check_all">{{ __('users.check_all') }}</button>
              <button type="button" class="btn btn-light btn-sm" id="perm_uncheck_all">{{ __('users.uncheck_all') }}</button>
            </div>
          </div>

          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead class="bg-light-subtle">
                  <tr>
                    <th>{{ __('users.module') }}</th>
                    <th class="text-center">{{ __('users.create') }}</th>
                    <th class="text-center">{{ __('users.read') }}</th>
                    <th class="text-center">{{ __('users.update') }}</th>
                    <th class="text-center">{{ __('users.delete') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Users & Admins -->
                  <tr data-module="users_admins">
                    <td class="fw-semibold">{{ __('users.users_admins') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Roles & Permissions -->
                  <tr data-module="roles_permissions">
                    <td class="fw-semibold">{{ __('users.roles_permissions') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Clients -->
                  <tr data-module="clients">
                    <td class="fw-semibold">{{ __('users.clients') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Providers -->
                  <tr data-module="providers">
                    <td class="fw-semibold">{{ __('users.providers') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Employees -->
                  <tr data-module="employees">
                    <td class="fw-semibold">{{ __('users.employees') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Bookings -->
                  <tr data-module="bookings">
                    <td class="fw-semibold">{{ __('users.bookings') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Services -->
                  <tr data-module="services">
                    <td class="fw-semibold">{{ __('users.services') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Categories -->
                  <tr data-module="categories">
                    <td class="fw-semibold">{{ __('users.categories') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Promo Codes -->
                  <tr data-module="promos">
                    <td class="fw-semibold">{{ __('users.promos') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Payment Settings -->
                  <tr data-module="payment_settings">
                    <td class="fw-semibold">{{ __('users.payment_settings') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Reports & Analytics -->
                  <tr data-module="reports_analytics">
                    <td class="fw-semibold">{{ __('users.reports_analytics') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Banners -->
                  <tr data-module="banners">
                    <td class="fw-semibold">{{ __('users.banners') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Static Pages -->
                  <tr data-module="static_pages">
                    <td class="fw-semibold">{{ __('users.static_pages') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Notifications -->
                  <tr data-module="notifications">
                    <td class="fw-semibold">{{ __('users.notifications') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Reviews -->
                  <tr data-module="reviews">
                    <td class="fw-semibold">{{ __('users.reviews') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Support & Tickets -->
                  <tr data-module="support_tickets">
                    <td class="fw-semibold">{{ __('users.support_tickets') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>

                  <!-- Settings -->
                  <tr data-module="settings">
                    <td class="fw-semibold">{{ __('users.settings') }}</td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="create"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="read"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="update"></td>
                    <td class="text-center"><input class="form-check-input perm" type="checkbox" data-key="delete"></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card-footer d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('users.cancel') }}</button>
            <button type="button" class="btn btn-primary" id="save_role_btn">{{ __('users.save_role') }}</button>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="card-title mb-0">{{ __('users.roles') }}</h4>
    <div class="d-flex gap-2">
      <input type="search" class="form-control form-control-sm" placeholder="{{ __('users.search_roles') }}" style="max-width: 120px;">
      <a href="!#" class="btn btn-primary btn-sm" data-bs-toggle="modal"
        data-bs-target="#roleCreateModal">
        <i class="bx bx-plus me-1"></i> {{ __('users.new_role') }}
      </a>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0 table-hover table-centered">
        <thead class="bg-light-subtle">
          <tr>
            <th>{{ __('users.role') }}</th>
            <th>{{ __('users.description') }}</th>
            <th>{{ __('users.permissions') }}</th>
            <th>{{ __('users.users') }}</th>
            <th>{{ __('users.status') }}</th>
            <th>{{ __('users.last_updated') }}</th>
            <th style="width: 120px;">{{ __('users.action') }}</th>
          </tr>
        </thead>

        <tbody>
          @forelse($roles as $role)
          <tr>
            <td class="fw-semibold">{{ $role['display_name'] }}</td>
            <td class="text-muted">{{ $role['display_name'] }} {{ __('users.role') }}</td>
            <td>
              @if($role['permissions_count'] > 0)
                <span class="badge bg-light-subtle text-muted border">{{ __('users.permissions_count', ['count' => $role['permissions_count']]) }}</span>
              @else
                <span class="badge bg-light-subtle text-muted border">{{ __('users.no_permissions') }}</span>
              @endif
            </td>
            <td>
              @if($role['users_count'] > 0)
                <div class="avatar-group">
                  <div class="avatar">
                    <span class="avatar-sm d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle fw-bold shadow">
                      {{ $role['users_count'] }}
                    </span>
                  </div>
                </div>
              @else
                <span class="text-muted">{{ __('users.no_users') }}</span>
              @endif
            </td>
            <td>
              <span class="badge bg-success-subtle text-success">{{ __('users.active') }}</span>
            </td>
            <td><small class="text-muted">{{ $role['created_at']->format('M d, Y • H:i') }}</small></td>
            <td>
              <div class="d-flex gap-2">
                <a href="{{ route('adminrole.editRole', $role['id']) }}" class="btn btn-light btn-sm" title="{{ __('users.edit') }}">
                  <iconify-icon icon="solar:pen-2-broken" class="align-middle fs-18"></iconify-icon>
                </a>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center py-4">
              <div class="text-muted">
                <iconify-icon icon="solar:folder-open-bold-duotone" class="fs-48 mb-2"></iconify-icon>
                <p>{{ __('users.no_roles_found') }}</p>
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="row g-0 align-items-center justify-content-between text-center text-sm-start p-3 border-top">
    <div class="col-sm">
      <div class="text-muted">
        {!! __('users.showing_roles_permissions', ['roles' => $stats['total_roles'], 'permissions' => $stats['total_permissions']]) !!}
      </div>
    </div>
  </div>
</div>

@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Check All / Uncheck All buttons
document.getElementById('perm_check_all')?.addEventListener('click', function() {
    document.querySelectorAll('.perm').forEach(checkbox => {
        checkbox.checked = true;
    });
});

document.getElementById('perm_uncheck_all')?.addEventListener('click', function() {
    document.querySelectorAll('.perm').forEach(checkbox => {
        checkbox.checked = false;
    });
});

// Save Role Button
document.getElementById('save_role_btn')?.addEventListener('click', function() {
    const roleName = document.getElementById('role_name').value.trim();
    const roleDesc = document.getElementById('role_desc').value.trim();
    const roleStatus = document.querySelector('input[name="role_status"]:checked')?.value;
    
    // Validate role name
    if (!roleName) {
        Swal.fire('{{ __('users.error') }}', '{{ __('users.fill_required_fields') }}', 'error');
        return;
    }
    
    // Collect permissions
    const permissions = [];
    document.querySelectorAll('.perm:checked').forEach(checkbox => {
        const row = checkbox.closest('tr');
        const module = row.dataset.module;
        const action = checkbox.dataset.key;
        permissions.push(`${module}.${action}`);
    });
    
    // Show loading
    Swal.fire({
        title: '{{ __('users.creating') }}',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    // Send to backend
    fetch('{{ route('adminrole.storeRole') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            name: roleName,
            description: roleDesc,
            status: roleStatus,
            permissions: permissions
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '{{ __('users.success') }}',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('{{ __('users.error') }}', data.message || 'Failed to create role', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('{{ __('users.error') }}', 'An error occurred while creating role', 'error');
    });
});
</script>
@endsection