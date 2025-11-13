@extends('layouts.vertical', ['title' => __('Static Pages')])

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box">
            <h4 class="page-title">{{ __('Content Management') }}</h4>
        </div>
    </div>
</div>

<!-- Flash Messages -->
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bx bx-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bx bx-error-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">{{ __('common.static_pages') }}</h4>
                <a href="{{ route('static-pages.create') }}" class="btn btn-primary btn-sm">
                    <i class="bx bx-plus me-1"></i>{{ __('common.create_new_page') }}
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>{{ __('common.title') }}</th>
                                <th>{{ __('common.slug') }}</th>
                                <th>{{ __('common.status') }}</th>
                                <th>{{ __('common.last_updated') }}</th>
                                <th class="text-center">{{ __('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pages as $page)
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-semibold">{{ app()->getLocale() === 'ar' ? $page->title_ar : $page->title_en }}</span>
                                            @if(in_array($page->slug, ['terms-and-conditions', 'privacy-policy', 'about-us', 'faq']))
                                                <small class="text-muted">
                                                    <i class="bx bx-lock-alt"></i> {{ __('common.system_page') }}
                                                </small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <code>{{ $page->slug }}</code>
                                    </td>
                                    <td>
                                        @if($page->is_published)
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="bx bx-check-circle"></i> {{ __('common.published') }}
                                            </span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning">
                                                <i class="bx bx-time"></i> {{ __('common.draft') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $page->updated_at ? \Carbon\Carbon::parse($page->updated_at)->format('Y-m-d H:i') : '-' }}
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <a href="{{ route('static-pages.edit', $page->id) }}" class="btn btn-soft-primary btn-sm" title="{{ __('common.edit') }}">
                                                <i class="bx bx-edit"></i>
                                            </a>
                                            
                                            <button type="button" class="btn btn-soft-{{ $page->is_published ? 'warning' : 'success' }} btn-sm" 
                                                    onclick="toggleStatus({{ $page->id }}, {{ $page->is_published ? '1' : '0' }})" 
                                                    title="{{ $page->is_published ? __('common.unpublish') : __('common.publish') }}">
                                                <i class="bx bx-{{ $page->is_published ? 'hide' : 'show' }}"></i>
                                            </button>

                                            @if(!in_array($page->slug, ['terms-and-conditions', 'privacy-policy', 'about-us', 'faq']))
                                                <button type="button" class="btn btn-soft-danger btn-sm" 
                                                        onclick="deletePage({{ $page->id }}, '{{ addslashes($page->title_en) }}')" 
                                                        title="{{ __('common.delete') }}">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bx bx-file fs-1 d-block mb-2"></i>
                                            <p class="mb-0">{{ __('common.no_pages_found') }}</p>
                                            <a href="{{ route('static-pages.create') }}" class="btn btn-primary btn-sm mt-2">
                                                <i class="bx bx-plus me-1"></i>{{ __('common.create_your_first_page') }}
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script-bottom')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function toggleStatus(id, currentStatus) {
    const isPublished = currentStatus === 1;
    const actionText = !isPublished ? '{{ __("common.publish") }}' : '{{ __("common.unpublish") }}';
    
    Swal.fire({
        title: actionText + ' {{ __("common.page") }}?',
        text: '{{ __("common.are_you_sure") }}',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: '{{ __("common.confirm") }}',
        cancelButtonText: '{{ __("common.cancel") }}'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/content/pages/${id}/toggle-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('{{ __("common.success") }}!', data.message, 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('{{ __("common.error") }}!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('{{ __("common.error") }}!', '{{ __("common.operation_failed") }}', 'error');
            });
        }
    });
}

function deletePage(id, title) {
    Swal.fire({
        title: '{{ __("common.delete") }} {{ __("common.page") }}?',
        text: `{{ __("common.are_you_sure") }}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: '{{ __("common.delete") }}',
        cancelButtonText: '{{ __("common.cancel") }}'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/content/pages/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('{{ __("common.deleted_successfully") }}!', data.message, 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('{{ __("common.error") }}!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('{{ __("common.error") }}!', '{{ __("common.operation_failed") }}', 'error');
            });
        }
    });
}
</script>
@endsection
