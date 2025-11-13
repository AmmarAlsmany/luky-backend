@extends('layouts.vertical', ['title' => __('users.edit_role')])

@section('css')
@vite(['node_modules/choices.js/public/assets/styles/choices.min.css'])
@endsection

@section('content')

<div class="row justify-content-center g-3">
  <div class="col-12 col-xl-12">

    <form action="{{ route('adminrole.updateRole', $role->id) }}" method="POST" id="editRoleForm">
      @csrf
      @method('PUT')

    <!-- 1) Role Basics -->
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">1. {{ __('users.role_prefix') }} {{ ucwords(str_replace('_', ' ', $role->name)) }}</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-12">
            <label class="form-label">{{ __('users.role_name') }}</label>
            <input type="text" class="form-control" value="{{ ucwords(str_replace('_', ' ', $role->name)) }}" readonly>
            <small class="text-muted">{{ __('users.role_name_readonly') }}</small>
          </div>
        </div>
      </div>
    </div>

    <!-- 2) Permissions -->
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">2. {{ __('users.permissions') }}</h5>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="bg-light-subtle">
              <tr>
                <th>{{ __('users.permission') }}</th>
                <th class="text-center" style="width: 100px;">{{ __('users.assigned') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($allPermissions as $module => $permissions)
                <tr class="bg-light">
                  <td colspan="2" class="fw-bold text-uppercase small">{{ ucwords(str_replace('_', ' ', $module)) }}</td>
                </tr>
                @foreach($permissions as $permission)
                <tr>
                  <td>{{ __('permissions.' . $permission->name) }}</td>
                  <td class="text-center">
                    <input class="form-check-input" 
                           type="checkbox" 
                           name="permissions[]" 
                           value="{{ $permission->name }}"
                           {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }}>
                  </td>
                </tr>
                @endforeach
              @empty
                <tr>
                  <td colspan="2" class="text-center py-4">
                    <span class="text-muted">{{ __('users.no_permissions_available') }}</span>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="card-footer d-flex justify-content-between">
        <a href="{{ route('adminrole.roles') }}" class="btn btn-outline-secondary">
          <i class="bx bx-arrow-back me-1"></i> {{ __('users.back_to_roles') }}
        </a>
        <button type="submit" class="btn btn-primary">
          <i class="bx bx-save me-1"></i> {{ __('users.save_changes') }}
        </button>
      </div>
    </div>

    </form>

  </div>
</div>

@endsection

@section('script-bottom')
@vite(['resources/js/pages/app-ecommerce-product.js'])
@endsection