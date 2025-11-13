@extends('layouts.vertical', ['title' => __('clients.list_title')])
@section('css')
    @vite(['node_modules/flatpickr/dist/flatpickr.min.css'])
@endsection
@section('content')
    {{-- Stats Cards --}}
    <div class="row">
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="avatar-md bg-primary bg-opacity-10 rounded">
                            <iconify-icon icon="solar:users-group-two-rounded-bold-duotone"
                                class="fs-32 text-primary avatar-title"></iconify-icon>
                        </div>
                        <div>
                            <h4 class="mb-0">{{ __('clients.all_clients') }}</h4>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="text-muted fw-medium fs-22 mb-0">
                            {{ number_format($stats['total_clients'] ?? 0) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="avatar-md bg-primary bg-opacity-10 rounded d-flex align-items-center justify-content-center">
                            <iconify-icon icon="solar:user-plus-bold-duotone"
                                class="fs-32 text-primary avatar-title"></iconify-icon>
                        </div>
                        <div>
                            <h4 class="mb-0">{{ __('clients.new_clients') }}</h4>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="text-muted fw-medium fs-22 mb-0">
                            {{ number_format($stats['new_clients'] ?? 0) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="avatar-md bg-primary bg-opacity-10 rounded">
                            <iconify-icon icon="solar:user-check-bold-duotone"
                                class="fs-32 text-primary avatar-title"></iconify-icon>
                        </div>
                        <div>
                            <h4 class="mb-0">{{ __('common.active') }} {{ __('common.clients') }}</h4>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="text-muted fw-medium fs-22 mb-0">
                            {{ number_format($stats['active_clients'] ?? 0) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="avatar-md bg-primary bg-opacity-10 rounded">
                            <iconify-icon icon="solar:user-block-bold-duotone"
                                class="fs-32 text-primary avatar-title"></iconify-icon>
                        </div>
                        <div>
                            <h4 class="mb-0">{{ __('common.inactive') }} {{ __('common.clients') }}</h4>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="text-muted fw-medium fs-22 mb-0">
                            {{ number_format($stats['inactive_clients'] ?? 0) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Client Table --}}
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="card-title mb-0">{{ __('clients.list_title') }}</h4>
                        </div>
                        <div class="col-auto">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#registerClientModal">
                                    <i class="bx bx-user-plus me-1"></i>{{ __('clients.add_client') }}
                                </button>
                                <div class="dropdown">
                                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="bx bx-download me-1"></i>{{ __('common.export') }}
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="{{ route('clients.export', ['format' => 'csv']) }}">{{ __('common.export') }} CSV</a></li>
                                        <li><a class="dropdown-item" href="{{ route('clients.export', ['format' => 'xlsx']) }}">{{ __('common.export') }} Excel</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Filters Section --}}
                <div class="card-body border-bottom">
                    <form method="GET" action="{{ route('clients.index') }}" id="filterForm">
                        <div class="row g-3">
                            {{-- Search --}}
                            <div class="col-md-3">
                                <label class="form-label">{{ __('common.search') }}</label>
                                <div class="search-box">
                                    <input type="text" name="search" class="form-control"
                                           placeholder="{{ __('clients.search_clients') }}" value="{{ request('search') }}">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                            </div>

                            {{-- Status Filter --}}
                            <div class="col-md-2">
                                <label class="form-label">{{ __('common.status') }}</label>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">{{ __('common.all') }} {{ __('common.status') }}</option>
                                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('common.active') }}</option>
                                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>{{ __('common.inactive') }}</option>
                                    <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>{{ __('common.suspended') }}</option>
                                </select>
                            </div>

                            {{-- City Filter --}}
                            <div class="col-md-2">
                                <label class="form-label">{{ __('common.city') }}</label>
                                <select name="city_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">{{ __('common.all') }} {{ __('common.cities') }}</option>
                                    @if(!empty($cities))
                                        @foreach($cities as $city)
                                            <option value="{{ $city['id'] }}" {{ request('city_id') == $city['id'] ? 'selected' : '' }}>
                                                {{ $city['name'] }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            {{-- Sort By --}}
                            <div class="col-md-2">
                                <label class="form-label">{{ __('common.sort_by') }}</label>
                                <select name="sort_by" class="form-select" onchange="this.form.submit()">
                                    <option value="created_at" {{ request('sort_by', 'created_at') == 'created_at' ? 'selected' : '' }}>{{ __('common.date_joined') }}</option>
                                    <option value="name" {{ request('sort_by') == 'name' ? 'selected' : '' }}>{{ __('common.name') }}</option>
                                    <option value="total_spent" {{ request('sort_by') == 'total_spent' ? 'selected' : '' }}>{{ __('clients.total_spent') }}</option>
                                    <option value="bookings_count" {{ request('sort_by') == 'bookings_count' ? 'selected' : '' }}>{{ __('common.bookings') }}</option>
                                </select>
                            </div>

                            {{-- Sort Order --}}
                            <div class="col-md-1">
                                <label class="form-label">{{ __('common.order') }}</label>
                                <select name="sort_order" class="form-select" onchange="this.form.submit()">
                                    <option value="desc" {{ request('sort_order', 'desc') == 'desc' ? 'selected' : '' }}>↓</option>
                                    <option value="asc" {{ request('sort_order') == 'asc' ? 'selected' : '' }}>↑</option>
                                </select>
                            </div>

                            {{-- Per Page --}}
                            <div class="col-md-2">
                                <label class="form-label">{{ __('common.show') }}</label>
                                <div class="d-flex gap-2">
                                    <select name="per_page" class="form-select" onchange="this.form.submit()">
                                        <option value="10" {{ request('per_page', 20) == 10 ? 'selected' : '' }}>10</option>
                                        <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20</option>
                                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                                    </select>
                                    @if(request()->has('search') || request()->has('status') || request()->has('city_id'))
                                        <a href="{{ route('clients.index') }}" class="btn btn-light" title="{{ __('common.clear_filters') }}">
                                            <i class="bx bx-x"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Active Filters Badge --}}
                @if(request()->has('search') || request()->has('status') || request()->has('city_id') || request()->has('sort_by'))
                <div class="card-body py-2 border-bottom bg-light">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <small class="text-muted">{{ __('common.active_filters') }}:</small>

                        @if(request('search'))
                            <span class="badge bg-info-subtle text-info">
                                {{ __('common.search') }}: {{ request('search') }}
                                <a href="{{ route('clients.index', array_merge(request()->except('search'))) }}" class="text-info ms-1">×</a>
                            </span>
                        @endif

                        @if(request('status'))
                            <span class="badge bg-primary-subtle text-primary">
                                {{ __('common.status') }}: {{ ucfirst(request('status')) }}
                                <a href="{{ route('clients.index', request()->except('status')) }}" class="text-primary ms-1">×</a>
                            </span>
                        @endif

                        @if(request('city_id'))
                            @php
                                $selectedCity = collect($cities)->firstWhere('id', request('city_id'));
                            @endphp
                            <span class="badge bg-success-subtle text-success">
                                {{ __('common.city') }}: {{ $selectedCity['name'] ?? 'Unknown' }}
                                <a href="{{ route('clients.index', request()->except('city_id')) }}" class="text-success ms-1">×</a>
                            </span>
                        @endif

                        @if(request('sort_by') && request('sort_by') != 'created_at')
                            <span class="badge bg-warning-subtle text-warning">
                                {{ __('common.sorted_by') }}: {{ ucfirst(str_replace('_', ' ', request('sort_by'))) }}
                            </span>
                        @endif

                        <a href="{{ route('clients.index') }}" class="badge bg-danger-subtle text-danger text-decoration-none">
                            {{ __('common.clear_all') }}
                        </a>
                    </div>
                </div>
                @endif

                <div class="card-body p-0">
                    @if(!empty($clients) && count($clients) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('common.name') }}</th>
                                    <th>{{ __('common.email') }}</th>
                                    <th>{{ __('common.phone') }}</th>
                                    <th>{{ __('common.city') }}</th>
                                    <th>{{ __('common.status') }}</th>
                                    <th>{{ __('common.bookings') }}</th>
                                    <th>{{ __('clients.total_spent') }}</th>
                                    <th>{{ __('common.joined') }}</th>
                                    <th>{{ __('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clients as $client)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @if(!empty($client['avatar_url']))
                                            <img src="{{ $client['avatar_url'] }}" alt="avatar" class="avatar-xs rounded-circle">
                                            @else
                                            <div class="avatar-xs">
                                                <div class="avatar-title bg-soft-primary text-primary rounded-circle">
                                                    {{ substr($client['name'] ?? 'U', 0, 1) }}
                                                </div>
                                            </div>
                                            @endif
                                            <span class="fw-medium">{{ $client['name'] ?? 'N/A' }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $client['email'] ?? 'N/A' }}</td>
                                    <td>{{ $client['phone'] ?? 'N/A' }}</td>
                                    <td>{{ $client['city'] ?? 'N/A' }}</td>
                                    <td>
                                        @php
                                            $statusClass = match($client['status'] ?? 'unknown') {
                                                'active' => 'success',
                                                'inactive' => 'warning',
                                                'suspended' => 'danger',
                                                default => 'secondary'
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $statusClass }}">
                                            {{ ucfirst($client['status'] ?? 'Unknown') }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info">
                                            {{ $client['bookings_count'] ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="fw-medium">
                                        {{ __('common.sar') }} {{ number_format($client['total_spent'] ?? 0, 2) }}
                                    </td>
                                    <td>
                                        {{ isset($client['created_at']) ? date('d M, Y', strtotime($client['created_at'])) : 'N/A' }}
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('clients.show', $client['id']) }}"
                                               class="btn btn-sm btn-soft-primary"
                                               title="{{ __('clients.view_details') }}">
                                                <i class="bx bx-show"></i>
                                            </a>

                                            {{-- Dynamic Status Change Dropdown --}}
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-soft-secondary dropdown-toggle"
                                                        type="button"
                                                        data-bs-toggle="dropdown"
                                                        aria-expanded="false"
                                                        title="{{ __('common.change_status') }}">
                                                    <i class="bx bx-dots-vertical-rounded"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    @if($client['status'] !== 'active')
                                                        <li>
                                                            <a class="dropdown-item text-success"
                                                               href="javascript:void(0);"
                                                               onclick="updateStatus({{ $client['id'] }}, 'active')">
                                                                <i class="bx bx-check-circle me-1"></i> {{ __('common.activate') }}
                                                            </a>
                                                        </li>
                                                    @endif

                                                    @if($client['status'] !== 'inactive')
                                                        <li>
                                                            <a class="dropdown-item text-warning"
                                                               href="javascript:void(0);"
                                                               onclick="updateStatus({{ $client['id'] }}, 'inactive')">
                                                                <i class="bx bx-pause-circle me-1"></i> {{ __('common.deactivate') }}
                                                            </a>
                                                        </li>
                                                    @endif

                                                    @if($client['status'] !== 'suspended')
                                                        <li>
                                                            <a class="dropdown-item text-danger"
                                                               href="javascript:void(0);"
                                                               onclick="updateStatus({{ $client['id'] }}, 'suspended')">
                                                                <i class="bx bx-block me-1"></i> {{ __('common.suspend') }}
                                                            </a>
                                                        </li>
                                                    @endif

                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item"
                                                           href="{{ route('clients.show', $client['id']) }}">
                                                            <i class="bx bx-info-circle me-1"></i> {{ __('clients.view_details') }}
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination --}}
                    @if(isset($pagination) && $pagination['total'] > 0)
                    <div class="card-footer bg-light">
                        <div class="row align-items-center">
                            <div class="col-sm-6">
                                <div class="text-muted fw-medium">
                                    {{ __('common.showing') }} {{ $pagination['from'] ?? 1 }} {{ __('common.to') }} {{ $pagination['to'] ?? 0 }}
                                    {{ __('common.of') }} {{ number_format($pagination['total']) }} {{ __('common.clients') }}
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-end mb-0">
                                        {{-- Previous --}}
                                        @if($pagination['current_page'] > 1)
                                        <li class="page-item">
                                            <a class="page-link"
                                               href="{{ route('clients.index', array_merge(request()->query(), ['page' => $pagination['current_page'] - 1])) }}">
                                                {{ __('common.previous') }}
                                            </a>
                                        </li>
                                        @endif

                                        {{-- Page Numbers --}}
                                        @for($i = 1; $i <= $pagination['last_page']; $i++)
                                            @if($i == 1 || $i == $pagination['last_page'] || abs($i - $pagination['current_page']) <= 2)
                                            <li class="page-item {{ $i == $pagination['current_page'] ? 'active' : '' }}">
                                                <a class="page-link"
                                                   href="{{ route('clients.index', array_merge(request()->query(), ['page' => $i])) }}">
                                                    {{ $i }}
                                                </a>
                                            </li>
                                            @elseif(abs($i - $pagination['current_page']) == 3)
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                            @endif
                                        @endfor

                                        {{-- Next --}}
                                        @if($pagination['current_page'] < $pagination['last_page'])
                                        <li class="page-item">
                                            <a class="page-link"
                                               href="{{ route('clients.index', array_merge(request()->query(), ['page' => $pagination['current_page'] + 1])) }}">
                                                {{ __('common.next') }}
                                            </a>
                                        </li>
                                        @endif
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                    @endif

                    @else
                    {{-- Empty State --}}
                    <div class="p-5 text-center">
                        <div class="avatar-lg mx-auto mb-4">
                            <div class="avatar-title bg-soft-primary text-primary display-4 rounded-circle">
                                <i class="bx bx-user-x"></i>
                            </div>
                        </div>
                        <h5>{{ __('common.no_clients_found') }}</h5>
                        <p class="text-muted">
                            @if(request('search'))
                                {{ __('common.no_clients_match_search') }}
                            @else
                                {{ __('common.no_clients_available') }}
                            @endif
                        </p>
                        @if(request('search'))
                        <a href="{{ route('clients.index') }}" class="btn btn-primary">
                            {{ __('common.clear_search') }}
                        </a>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Error Alert --}}
    @if(session('error'))
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div class="toast show" role="alert">
            <div class="toast-header bg-danger text-white">
                <strong class="me-auto">{{ __('common.error') }}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                {{ session('error') }}
            </div>
        </div>
    </div>
    @endif

    {{-- Success Alert --}}
    @if(session('success'))
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div class="toast show" role="alert">
            <div class="toast-header bg-success text-white">
                <strong class="me-auto">{{ __('common.success') }}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                {{ session('success') }}
            </div>
        </div>
    </div>
    @endif

    {{-- Register Client Modal --}}
    <div class="modal fade" id="registerClientModal" tabindex="-1" aria-labelledby="registerClientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registerClientModalLabel">{{ __('clients.add_client') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.close') }}"></button>
                </div>
                <form id="registerClientForm">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('common.full_name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required placeholder="{{ __('common.enter_full_name') }}">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('common.email') }}</label>
                                <input type="email" class="form-control" name="email" placeholder="{{ __('common.enter_email') }}">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('common.phone') }} <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="phone" required placeholder="{{ __('common.enter_phone') }}">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('common.city') }} <span class="text-danger">*</span></label>
                                <select class="form-select" name="city_id" required>
                                    <option value="">{{ __('common.select_city') }}</option>
                                    @if(!empty($cities))
                                        @foreach($cities as $city)
                                            <option value="{{ $city['id'] }}">{{ $city['name'] }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('common.gender') }} <span class="text-danger">*</span></label>
                                <select class="form-select" name="gender" required>
                                    <option value="">{{ __('common.select_gender') }}</option>
                                    <option value="male">{{ __('common.male') }}</option>
                                    <option value="female">{{ __('common.female') }}</option>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('common.date_of_birth') }} <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_of_birth" required placeholder="{{ __('common.select_date') }}">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bx bx-check me-1"></i>{{ __('clients.add_client') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function updateStatus(clientId, status) {
    // Prepare user-friendly messages
    const statusMessages = {
        'active': {
            title: '{{ __('common.activate_client') }}',
            text: '{{ __('common.activate_client_text') }}',
            icon: 'info',
            confirmButton: '{{ __('common.yes_activate') }}',
            confirmColor: '#28a745'
        },
        'inactive': {
            title: '{{ __('common.deactivate_client') }}',
            text: '{{ __('common.deactivate_client_text') }}',
            icon: 'warning',
            confirmButton: '{{ __('common.yes_deactivate') }}',
            confirmColor: '#ffc107'
        },
        'suspended': {
            title: '{{ __('common.suspend_client') }}',
            text: '{{ __('common.suspend_client_text') }}',
            icon: 'warning',
            confirmButton: '{{ __('common.yes_suspend') }}',
            confirmColor: '#dc3545'
        }
    };

    const config = statusMessages[status] || statusMessages['inactive'];

    Swal.fire({
        title: config.title,
        text: config.text,
        icon: config.icon,
        showCancelButton: true,
        confirmButtonColor: config.confirmColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: config.confirmButton,
        cancelButtonText: '{{ __('common.cancel') }}',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch(`/clients/${clientId}/status`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ status: status })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => Promise.reject(err));
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to update status');
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(
                    `Request failed: ${error.message || 'Unknown error'}`
                );
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            Swal.fire({
                icon: 'success',
                title: '{{ __('common.status_updated') }}',
                text: result.value.message || '{{ __('common.client_status_updated') }}',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    });
}

// Handle client registration form submission
document.getElementById('registerClientForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const submitBtn = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);

    // Clear previous errors
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');

    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>{{ __('common.registering') }}...';

    // Convert FormData to JSON
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    fetch('{{ route('clients.store') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw err;
            });
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('registerClientModal'));
            modal.hide();

            // Reset form
            form.reset();

            // Show success message
            Swal.fire({
                icon: 'success',
                title: '{{ __('common.client_registered') }}',
                text: result.message || '{{ __('common.client_registered_success') }}',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                // Reload page to show new client
                location.reload();
            });
        }
    })
    .catch(error => {
        // Handle validation errors
        if (error.errors && Object.keys(error.errors).length > 0) {
            Object.keys(error.errors).forEach(field => {
                const input = form.querySelector(`[name="${field}"]`);
                if (input) {
                    input.classList.add('is-invalid');
                    const feedback = input.nextElementSibling;
                    if (feedback && feedback.classList.contains('invalid-feedback')) {
                        // Show proper validation message
                        const errorMsg = Array.isArray(error.errors[field]) ? error.errors[field][0] : error.errors[field];
                        feedback.textContent = errorMsg;
                        feedback.style.display = 'block';
                    }
                }
            });

            // Show alert with specific error
            const firstErrorArray = Object.values(error.errors)[0];
            const firstError = Array.isArray(firstErrorArray) ? firstErrorArray[0] : firstErrorArray;
            Swal.fire({
                icon: 'error',
                title: '{{ __('common.validation_error') }}',
                text: firstError || error.message || '{{ __('common.check_form') }}'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: '{{ __('common.registration_failed') }}',
                text: error.message || '{{ __('common.unexpected_error') }}'
            });
        }
    })
    .finally(() => {
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bx bx-check me-1"></i>{{ __('clients.add_client') }}';
    });
});
</script>
@endsection
