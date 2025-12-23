@extends('layouts.vertical', ['title' => __('providers.pending_profile_changes')])

@section('content')

<!-- Flash Messages -->
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bx bx-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('common.close') }}"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bx bx-error-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('common.close') }}"></button>
    </div>
@endif

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="card-title">{{ __('providers.pending_profile_changes') }}</h4>
                        <p class="text-muted mb-0">{{ __('providers.review_approve_profile_updates') }}</p>
                    </div>
                    <div class="col-auto">
                        <span class="badge bg-warning fs-14">{{ count($pendingChanges ?? []) }} {{ __('common.pending') }}</span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('providers.provider') }}</th>
                                <th>{{ __('providers.business_name') }}</th>
                                <th>{{ __('providers.changed_fields') }}</th>
                                <th>{{ __('providers.submitted') }}</th>
                                <th class="text-center">{{ __('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($pendingChanges as $change)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <span class="fw-semibold">{{ $change['provider_name'] ?? __('common.na') }}</span>
                                            <br>
                                            <small class="text-muted">{{ __('providers.id') }}: {{ $change['provider_id'] }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $change['business_name'] ?? __('common.na') }}</td>
                                <td>
                                    <span class="badge bg-info-subtle text-info">
                                        {{ count($change['changed_fields']) }} {{ __('providers.fields') }}
                                    </span>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($change['created_at'])->format('M d, Y H:i') }}</td>
                                <td class="text-center">
                                    <div class="d-flex gap-2 justify-content-center">
                                        <button type="button"
                                                class="btn btn-light btn-sm"
                                                onclick="showChangesModal({{ json_encode($change) }})"
                                                title="{{ __('providers.view_changes') }}">
                                            <iconify-icon icon="solar:eye-broken" class="align-middle fs-18"></iconify-icon>
                                        </button>
                                        <button type="button"
                                                class="btn btn-success btn-sm"
                                                onclick="showApprovalModal({{ $change['id'] }}, '{{ $change['business_name'] ?? __('providers.provider') }}', 'approve')"
                                                title="{{ __('providers.approve') }}">
                                            <iconify-icon icon="solar:check-circle-bold" class="align-middle fs-18"></iconify-icon>
                                        </button>
                                        <button type="button"
                                                class="btn btn-danger btn-sm"
                                                onclick="showApprovalModal({{ $change['id'] }}, '{{ $change['business_name'] ?? __('providers.provider') }}', 'reject')"
                                                title="{{ __('providers.reject') }}">
                                            <iconify-icon icon="solar:close-circle-bold" class="align-middle fs-18"></iconify-icon>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted">
                                        <iconify-icon icon="solar:inbox-line-broken" class="fs-48 mb-2"></iconify-icon>
                                        <p>{{ __('providers.no_pending_changes') }}</p>
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

<!-- Changes Viewer Modal -->
<div class="modal fade" id="changesModal" tabindex="-1" aria-labelledby="changesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changesModalLabel">
                    <i class="bx bx-refresh me-2"></i>{{ __('providers.profile_changes') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.close') }}"></button>
            </div>
            <div class="modal-body" id="changesContent">
                <!-- Changes will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Approval/Rejection Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approvalModalLabel">{{ __('providers.confirm_action') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.close') }}"></button>
            </div>
            <form id="approvalForm" method="POST">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="action" id="approvalAction">

                    <div class="alert alert-info" id="approvalMessage">
                        <!-- Message will be set dynamically -->
                    </div>

                    <div class="mb-3" id="notesDiv">
                        <label for="approvalNotes" class="form-label">{{ __('providers.notes') }} <span class="text-muted">({{ __('providers.optional') }})</span></label>
                        <textarea class="form-control" id="approvalNotes" name="admin_notes" rows="3"
                                  placeholder="{{ __('providers.add_notes_placeholder') }}"></textarea>
                    </div>

                    <div class="mb-3" id="rejectionReasonDiv" style="display: none;">
                        <label for="rejectionReason" class="form-label">{{ __('providers.rejection_reason') }} <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectionReason" name="rejection_reason" rows="3"
                                  placeholder="{{ __('providers.rejection_reason_placeholder') }}"
                                  required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn" id="approvalSubmitBtn">{{ __('common.confirm') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('script-bottom')
<script>
const translations = {
    field: @json(__('providers.field')),
    oldValue: @json(__('providers.old_value')),
    newValue: @json(__('providers.new_value')),
    provider: @json(__('providers.provider')),
    profileChanges: @json(__('providers.profile_changes')),
    approveProfileChanges: @json(__('providers.approve_profile_changes')),
    rejectProfileChanges: @json(__('providers.reject_profile_changes')),
    approveConfirmMsg: @json(__('providers.approve_changes_confirm')),
    rejectConfirmMsg: @json(__('providers.reject_changes_confirm')),
    approveChanges: @json(__('providers.approve_changes')),
    rejectChanges: @json(__('providers.reject_changes')),
    na: @json(__('common.na')),
    // Field translations
    businessName: @json(__('providers.business_name')),
    description: @json(__('common.description')),
    address: @json(__('common.address')),
    workingHours: @json(__('providers.working_hours')),
    offDays: @json(__('providers.off_days')),
    businessType: @json(__('providers.business_type')),
    from: @json(__('providers.from')),
    to: @json(__('providers.to')),
};

// Field name translation mapping
const fieldTranslations = {
    'business_name': translations.businessName,
    'description': translations.description,
    'address': translations.address,
    'working_hours': translations.workingHours,
    'off_days': translations.offDays,
    'business_type': translations.businessType,
};

// Fields to hide from display
const hiddenFields = ['latitude', 'longitude'];

function formatValue(field, value) {
    if (value === null || value === undefined) {
        return translations.na;
    }

    // Handle working_hours object
    if (field === 'working_hours' && typeof value === 'object' && !Array.isArray(value)) {
        return `${translations.from}: ${value.from || 'N/A'} - ${translations.to}: ${value.to || 'N/A'}`;
    }

    // Handle off_days array
    if (field === 'off_days' && Array.isArray(value)) {
        return value.map(day => day.charAt(0).toUpperCase() + day.slice(1)).join(', ');
    }

    // Handle other arrays
    if (Array.isArray(value)) {
        return value.join(', ');
    }

    // Handle objects
    if (typeof value === 'object') {
        return '<pre class="mb-0 small">' + JSON.stringify(value, null, 2) + '</pre>';
    }

    return value;
}

function showChangesModal(change) {
    const modal = new bootstrap.Modal(document.getElementById('changesModal'));
    const content = document.getElementById('changesContent');

    let html = '<div class="table-responsive"><table class="table table-bordered">';
    html += `<thead class="table-light"><tr><th>${translations.field}</th><th>${translations.oldValue}</th><th>${translations.newValue}</th></tr></thead>`;
    html += '<tbody>';

    const changedFields = change.changed_fields || {};
    const oldValues = change.old_values || {};

    for (const [field, newValue] of Object.entries(changedFields)) {
        // Skip hidden fields
        if (hiddenFields.includes(field)) {
            continue;
        }

        const oldValue = oldValues[field];

        // Get translated field name or format it nicely
        const displayField = fieldTranslations[field] || field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

        const displayNewValue = formatValue(field, newValue);
        const displayOldValue = formatValue(field, oldValue);

        html += `
            <tr>
                <td class="fw-semibold">${displayField}</td>
                <td class="text-muted">${displayOldValue}</td>
                <td class="text-success">${displayNewValue}</td>
            </tr>
        `;
    }

    html += '</tbody></table></div>';
    content.innerHTML = html;

    document.getElementById('changesModalLabel').innerHTML =
        `<i class="bx bx-refresh me-2"></i>${change.business_name || translations.provider} - ${translations.profileChanges}`;

    modal.show();
}

function showApprovalModal(changeId, providerName, action) {
    const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
    const form = document.getElementById('approvalForm');
    const submitBtn = document.getElementById('approvalSubmitBtn');
    const message = document.getElementById('approvalMessage');
    const notesDiv = document.getElementById('notesDiv');
    const rejectionReasonDiv = document.getElementById('rejectionReasonDiv');
    const rejectionReason = document.getElementById('rejectionReason');

    // Set form action URL
    if (action === 'approve') {
        form.action = `/provider/pending-changes/${changeId}/approve`;
    } else {
        form.action = `/provider/pending-changes/${changeId}/reject`;
    }

    // Set action type
    document.getElementById('approvalAction').value = action;

    // Update modal content based on action
    if (action === 'approve') {
        document.getElementById('approvalModalLabel').textContent = translations.approveProfileChanges;
        message.className = 'alert alert-success';
        message.innerHTML = `<i class="bx bx-check-circle me-2"></i>${translations.approveConfirmMsg.replace(':name', `<strong>${providerName}</strong>`)}`;
        submitBtn.className = 'btn btn-success';
        submitBtn.innerHTML = `<i class="bx bx-check me-1"></i>${translations.approveChanges}`;
        notesDiv.style.display = 'block';
        rejectionReasonDiv.style.display = 'none';
        rejectionReason.removeAttribute('required');
    } else {
        document.getElementById('approvalModalLabel').textContent = translations.rejectProfileChanges;
        message.className = 'alert alert-danger';
        message.innerHTML = `<i class="bx bx-error-circle me-2"></i>${translations.rejectConfirmMsg.replace(':name', `<strong>${providerName}</strong>`)}`;
        submitBtn.className = 'btn btn-danger';
        submitBtn.innerHTML = `<i class="bx bx-x me-1"></i>${translations.rejectChanges}`;
        notesDiv.style.display = 'block';
        rejectionReasonDiv.style.display = 'block';
        rejectionReason.setAttribute('required', 'required');
    }

    // Clear fields
    document.getElementById('approvalNotes').value = '';
    document.getElementById('rejectionReason').value = '';

    modal.show();
}
</script>
@endsection
