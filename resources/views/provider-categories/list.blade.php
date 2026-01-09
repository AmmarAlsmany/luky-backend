@extends('layouts.vertical', ['title' => __('common.provider_categories')])

@section('content')
    {{-- Page Header --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-1 fw-bold">{{ __('common.provider_categories') }} ({{ __('common.business_types') }})</h4>
                    <p class="text-muted mb-0">{{ __('common.add_business_type_desc') }}</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('provider-categories.create') }}" class="btn btn-primary">
                        <i class="mdi mdi-plus-circle me-1"></i> {{ __('common.add_provider_category') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row">
        <div class="col-md-4">
            <div class="card card-animate overflow-hidden">
                <div class="position-absolute end-0 top-0 p-3">
                    <div class="avatar-md bg-primary-subtle rounded-circle">
                        <iconify-icon icon="solar:buildings-2-bold-duotone" class="fs-32 text-primary avatar-title"></iconify-icon>
                    </div>
                </div>
                <div class="card-body" style="z-index: 1">
                    <p class="text-muted text-uppercase fw-semibold fs-13 mb-2">{{ __('common.total_business_types') }}</p>
                    <h3 class="mb-3 fw-bold">{{ number_format($stats['total'] ?? 0) }}</h3>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-light text-dark">
                            <i class="mdi mdi-view-grid"></i> {{ __('common.all_types') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-animate overflow-hidden">
                <div class="position-absolute end-0 top-0 p-3">
                    <div class="avatar-md bg-success-subtle rounded-circle">
                        <iconify-icon icon="solar:check-circle-bold-duotone" class="fs-32 text-success avatar-title"></iconify-icon>
                    </div>
                </div>
                <div class="card-body" style="z-index: 1">
                    <p class="text-muted text-uppercase fw-semibold fs-13 mb-2">{{ __('common.active_business_types') }}</p>
                    <h3 class="mb-3 fw-bold">{{ number_format($stats['active'] ?? 0) }}</h3>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success-subtle text-success">
                            <i class="mdi mdi-eye"></i> {{ __('common.available') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-animate overflow-hidden">
                <div class="position-absolute end-0 top-0 p-3">
                    <div class="avatar-md bg-danger-subtle rounded-circle">
                        <iconify-icon icon="solar:close-circle-bold-duotone" class="fs-32 text-danger avatar-title"></iconify-icon>
                    </div>
                </div>
                <div class="card-body" style="z-index: 1">
                    <p class="text-muted text-uppercase fw-semibold fs-13 mb-2">{{ __('common.inactive_business_types') }}</p>
                    <h3 class="mb-3 fw-bold">{{ number_format($stats['inactive'] ?? 0) }}</h3>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger-subtle text-danger">
                            <i class="mdi mdi-eye-off"></i> {{ __('common.hidden') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Categories Grid --}}
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-semibold">{{ __('common.all_provider_business_types') }}</h5>
                    </div>
                </div>

                <div class="card-body">
                    {{-- Filters --}}
                    <form method="GET" action="{{ route('provider-categories.index') }}" class="mb-4">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('common.search_business_types') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="mdi mdi-magnify"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control border-start-0"
                                        placeholder="{{ __('common.search_by_business_type_name') }}"
                                        value="{{ $filters['search'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">{{ __('common.status_filter') }}</label>
                                <select name="status" class="form-select">
                                    <option value="">{{ __('common.all_status') }}</option>
                                    <option value="active" {{ ($filters['status'] ?? '') == 'active' ? 'selected' : '' }}>{{ __('common.active_only') }}</option>
                                    <option value="inactive" {{ ($filters['status'] ?? '') == 'inactive' ? 'selected' : '' }}>{{ __('common.inactive_only') }}</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-fill">
                                    <i class="mdi mdi-filter-variant me-1"></i> {{ __('common.apply') }}
                                </button>
                                <a href="{{ route('provider-categories.index') }}" class="btn btn-soft-secondary">
                                    <i class="mdi mdi-refresh"></i>
                                </a>
                            </div>
                        </div>
                    </form>

                    {{-- Categories Grid --}}
                    <div class="row g-4">
                        @forelse($categories as $category)
                            <div class="col-md-6 col-xl-4 col-xxl-3">
                                <div class="card mb-0 category-card h-100 border shadow-sm hover-shadow-lg transition">
                                    <div class="card-body text-center">
                                        <div class="position-relative mb-3">
                                            @if(!empty($category['image']))
                                                <div class="rounded-3 bg-light d-flex align-items-center justify-content-center mx-auto overflow-hidden"
                                                     style="height: 180px; width: 100%;">
                                                    <img src="{{ $category['image'] }}" alt="{{ $category['name'] ?? '' }}"
                                                         class="img-fluid category-image"
                                                         style="max-height: 100%; max-width: 100%; object-fit: cover; transition: transform 0.3s ease;">
                                                </div>
                                            @elseif(!empty($category['color']))
                                                <div class="rounded-3 d-flex align-items-center justify-content-center mx-auto"
                                                     style="height: 180px; background: linear-gradient(135deg, {{ $category['color'] }}20 0%, {{ $category['color'] }}40 100%);">
                                                    @if(!empty($category['icon']))
                                                        <i class="mdi mdi-{{ $category['icon'] }} fs-48 opacity-75" style="color: {{ $category['color'] }};"></i>
                                                    @else
                                                        <iconify-icon icon="solar:buildings-2-bold-duotone" class="fs-48 opacity-75" style="color: {{ $category['color'] }};"></iconify-icon>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="rounded-3 bg-gradient bg-primary bg-opacity-10 d-flex align-items-center justify-content-center mx-auto"
                                                     style="height: 180px;">
                                                    <iconify-icon icon="solar:buildings-2-bold-duotone" class="fs-48 text-primary opacity-75"></iconify-icon>
                                                </div>
                                            @endif

                                            <div class="position-absolute top-0 end-0 m-2">
                                                @if($category['is_active'] ?? false)
                                                    <span class="badge bg-success shadow-sm">
                                                        <i class="mdi mdi-check-circle"></i> {{ __('common.active') }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-danger shadow-sm">
                                                        <i class="mdi mdi-close-circle"></i> {{ __('common.inactive') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <h5 class="mt-3 mb-2 fw-bold text-dark">{{ $category['name'] ?? 'N/A' }}</h5>
                                        @if(!empty($category['description']))
                                            <p class="text-muted fs-13 mb-3">{{ Str::limit($category['description'], 70) }}</p>
                                        @else
                                            <p class="text-muted fs-13 mb-3">{{ __('common.no_description') }}</p>
                                        @endif

                                        {{-- Providers Count Badge --}}
                                        <div class="mb-3">
                                            <span class="badge bg-primary-subtle text-primary">
                                                <i class="mdi mdi-account-group me-1"></i>
                                                {{ $category['providers_count'] ?? 0 }} {{ __('common.providers_count') }}
                                            </span>
                                            @if(!empty($category['color']))
                                                <span class="badge ms-1" style="background-color: {{ $category['color'] }}20; color: {{ $category['color'] }};">
                                                    <i class="mdi mdi-palette"></i> {{ $category['color'] }}
                                                </span>
                                            @endif
                                        </div>

                                        <div class="d-flex justify-content-center gap-2 mt-3 pt-3 border-top">
                                            <a href="{{ route('provider-categories.edit', $category['id']) }}"
                                               class="btn btn-soft-primary btn-sm"
                                               data-bs-toggle="tooltip"
                                               title="{{ __('common.edit') }} {{ __('common.business_types') }}">
                                                <iconify-icon icon="solar:pen-2-broken" class="align-middle fs-18"></iconify-icon>
                                            </a>
                                            <button onclick="toggleStatus({{ $category['id'] }})"
                                                    class="btn btn-soft-warning btn-sm"
                                                    data-bs-toggle="tooltip"
                                                    title="{{ __('common.status') }}">
                                                <iconify-icon icon="solar:power-bold-duotone" class="align-middle fs-18"></iconify-icon>
                                            </button>
                                            <button onclick="deleteCategory({{ $category['id'] }}, '{{ addslashes($category['name'] ?? 'this category') }}', {{ $category['providers_count'] ?? 0 }})"
                                                    class="btn btn-soft-danger btn-sm"
                                                    data-bs-toggle="tooltip"
                                                    title="{{ __('common.delete') }} {{ __('common.business_types') }}">
                                                <iconify-icon icon="solar:trash-bin-minimalistic-2-broken" class="align-middle fs-18"></iconify-icon>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <div class="avatar-xl bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                                        <iconify-icon icon="solar:inbox-line-bold-duotone" class="fs-48 text-muted"></iconify-icon>
                                    </div>
                                    <h5 class="text-muted">{{ __('common.no_business_types_found') }}</h5>
                                    <p class="text-muted mb-3">{{ __('common.add_business_types_desc') }}</p>
                                    <a href="{{ route('provider-categories.create') }}" class="btn btn-primary">
                                        <i class="mdi mdi-plus-circle me-1"></i> {{ __('common.create_first_business_type') }}
                                    </a>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .category-card {
            transition: all 0.3s ease;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
        }
        .category-card:hover .category-image {
            transform: scale(1.1);
        }
        .hover-shadow-lg:hover {
            box-shadow: 0 1rem 3rem rgba(0,0,0,.175) !important;
        }
    </style>

    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });

        function toggleStatus(categoryId) {
            fetch(`/provider-categories/${categoryId}/toggle-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '{{ __('common.updated') }}!',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => location.reload());
                } else if (data.warning) {
                    Swal.fire({
                        title: '{{ __('common.warning') }}!',
                        html: `<p>${data.message}</p>
                               <div class="alert alert-warning mt-3">
                                   <i class="mdi mdi-alert me-1"></i>
                                   <strong>${data.providers_count} {{ __('common.providers_count') }}</strong> will still be active but this business type will be hidden from new registrations.
                               </div>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#f59e0b',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '{{ __('common.yes_update') }}',
                        cancelButtonText: '{{ __('common.cancel') }}'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch(`/provider-categories/${categoryId}`, {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ force_toggle: true })
                            })
                            .then(response => response.json())
                            .then(data => {
                                Swal.fire({
                                    icon: 'success',
                                    title: '{{ __('common.updated') }}!',
                                    text: 'Provider business type has been deactivated.',
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => location.reload());
                            });
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '{{ __('common.error') }}!',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('{{ __('common.error') }}!', 'Something went wrong!', 'error');
            });
        }

        function deleteCategory(categoryId, categoryName, providersCount) {
            let warningHtml = `<p class="mb-2">Are you sure you want to delete the provider business type <strong>"${categoryName}"</strong>?</p>`;

            if (providersCount > 0) {
                warningHtml += `
                    <div class="alert alert-danger mt-3 mb-2">
                        <i class="mdi mdi-alert-circle-outline me-1"></i>
                        <strong>Warning:</strong> This business type has <strong>${providersCount} {{ __('common.providers_count') }}</strong> assigned to it.
                    </div>
                    <p class="text-muted small">Deleting this business type may affect all associated providers. Please reassign providers to another business type before deletion.</p>
                `;
            } else {
                warningHtml += `<p class="text-muted small">{{ __('common.cannot_undone') }}</p>`;
            }

            Swal.fire({
                title: '{{ __('common.delete') }} {{ __('common.business_types') }}?',
                html: warningHtml,
                icon: providersCount > 0 ? 'error' : 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="mdi mdi-delete me-1"></i> {{ __('common.yes_delete') }}!',
                cancelButtonText: '<i class="mdi mdi-close me-1"></i> {{ __('common.cancel') }}',
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: '{{ __('common.deleting') }}',
                        text: 'Please wait while we delete the business type',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch(`/provider-categories/${categoryId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '{{ __('common.deleted') }}!',
                                text: data.message || 'Provider business type has been deleted successfully.',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '{{ __('common.error') }}!',
                                text: data.message || 'Failed to delete business type.',
                                confirmButtonColor: '#3085d6'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __('common.error') }}!',
                            text: 'Something went wrong! Please try again.',
                            confirmButtonColor: '#3085d6'
                        });
                    });
                }
            });
        }
    </script>
@endsection
