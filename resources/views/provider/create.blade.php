@extends('layouts.vertical', ['title' => __('providers.add_provider')])

@section('content')

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

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bx bx-error-circle me-2"></i>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex align-items-center justify-content-between">
                    <h4 class="card-title">{{ __('providers.add_provider') }}</h4>
                    <a href="{{ route('providers.index') }}" class="btn btn-sm btn-secondary">
                        <i class="bx bx-arrow-back me-1"></i>{{ __('common.back_to_list') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('providers.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row">
                        <!-- Basic Information -->
                        <div class="col-lg-6">
                            <h5 class="mb-3">{{ __('providers.basic_information') }}</h5>

                            <div class="mb-3">
                                <label for="name" class="form-label">{{ __('common.full_name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="{{ old('name') }}" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">{{ __('common.email') }} <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="{{ old('email') }}" required>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">{{ __('common.phone_number') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="phone" name="phone"
                                       value="{{ old('phone') }}" required>
                                <small class="text-muted">{{ __('providers.phone_otp_hint') }}</small>
                            </div>
                        </div>

                        <!-- Business Information -->
                        <div class="col-lg-6">
                            <h5 class="mb-3">{{ __('providers.business_info') }}</h5>

                            <div class="mb-3">
                                <label for="business_name" class="form-label">{{ __('providers.business_name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="business_name" name="business_name"
                                       value="{{ old('business_name') }}" required>
                            </div>

                            <div class="mb-3">
                                <label for="business_type" class="form-label">{{ __('providers.business_type') }} <span class="text-danger">*</span></label>
                                <select class="form-select" id="business_type" name="business_type" required>
                                    <option value="">{{ __('providers.select_type') }}</option>
                                    <option value="salon" {{ old('business_type') === 'salon' ? 'selected' : '' }}>{{ __('providers.salon') }}</option>
                                    <option value="clinic" {{ old('business_type') === 'clinic' ? 'selected' : '' }}>{{ __('providers.clinic') }}</option>
                                    <option value="makeup_artist" {{ old('business_type') === 'makeup_artist' ? 'selected' : '' }}>{{ __('providers.makeup_artist') }}</option>
                                    <option value="hair_stylist" {{ old('business_type') === 'hair_stylist' ? 'selected' : '' }}>{{ __('providers.hair_stylist') }}</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="city_id" class="form-label">{{ __('common.city') }} <span class="text-danger">*</span></label>
                                <select class="form-select" id="city_id" name="city_id" required>
                                    <option value="">{{ __('providers.select_city') }}</option>
                                    @if(!empty($cities))
                                        @foreach($cities as $city)
                                            <option value="{{ $city['id'] }}" {{ old('city_id') == $city['id'] ? 'selected' : '' }}>
                                                {{ app()->getLocale() === 'ar' ? ($city['name_ar'] ?? $city['name_en']) : ($city['name_en'] ?? $city['name_ar']) }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">{{ __('providers.address_location') }}</label>
                                <input type="text" class="form-control mb-2" id="address" name="address"
                                       value="{{ old('address') }}" placeholder="{{ __('providers.address_placeholder') }}">
                                <small class="text-muted">
                                    <i class="bx bx-map me-1"></i>{{ __('providers.maps_hint') }}
                                </small>
                            </div>

                            <div class="mb-3">
                                <label for="latitude" class="form-label">{{ __('providers.latitude') }}</label>
                                <input type="text" class="form-control" id="latitude" name="latitude"
                                       value="{{ old('latitude') }}" placeholder="e.g., 27.5173">
                            </div>

                            <div class="mb-3">
                                <label for="longitude" class="form-label">{{ __('providers.longitude') }}</label>
                                <input type="text" class="form-control" id="longitude" name="longitude"
                                       value="{{ old('longitude') }}" placeholder="e.g., 41.6992">
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">{{ __('common.description') }}</label>
                                <textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                            </div>

                            <!-- Contract Details -->
                            <h5 class="mb-3 mt-4">
                                <i class="bx bx-file-blank me-2"></i>{{ __('providers.contract_details') }}
                            </h5>

                            <div class="mb-3">
                                <label for="contract_start_date" class="form-label">{{ __('providers.contract_start_date') }} <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="contract_start_date" name="contract_start_date" 
                                       value="{{ old('contract_start_date') }}" required>
                            </div>

                            <div class="mb-3">
                                <label for="contract_end_date" class="form-label">{{ __('providers.contract_end_date') }}</label>
                                <input type="date" class="form-control" id="contract_end_date" name="contract_end_date" 
                                       value="{{ old('contract_end_date') }}">
                                <small class="text-muted">{{ __('providers.contract_end_date_hint') }}</small>
                            </div>

                            <div class="mb-3">
                                <label for="payment_terms" class="form-label">{{ __('providers.payment_terms') }}</label>
                                <textarea class="form-control" id="payment_terms" name="payment_terms" rows="2" 
                                          placeholder="{{ __('providers.payment_terms_placeholder') }}">{{ old('payment_terms') }}</textarea>
                            </div>

                            <div class="mb-3">
                                <label for="contract_notes" class="form-label">{{ __('providers.contract_notes') }}</label>
                                <textarea class="form-control" id="contract_notes" name="contract_notes" rows="2" 
                                          placeholder="{{ __('providers.contract_notes_placeholder') }}">{{ old('contract_notes') }}</textarea>
                            </div>
                        </div>

                        <!-- Working Hours -->
                        <div class="col-lg-6">
                            <h5 class="mb-3">{{ __('providers.working_hours') }}</h5>

                            <div class="mb-3">
                                <label class="form-label">{{ __('providers.working_days') }} <span class="text-danger">*</span></label>

                                @php
                                $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                                $dayLabels = [
                                    __('providers.sunday'),
                                    __('providers.monday'),
                                    __('providers.tuesday'),
                                    __('providers.wednesday'),
                                    __('providers.thursday'),
                                    __('providers.friday'),
                                    __('providers.saturday')
                                ];
                                @endphp

                                @foreach($days as $index => $day)
                                <div class="card mb-2">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input working-day-checkbox" type="checkbox"
                                                       id="day_{{ $day }}" name="working_days[]" value="{{ $day }}"
                                                       {{ old('working_days') && in_array($day, old('working_days')) ? 'checked' : '' }}>
                                                <label class="form-check-label fw-semibold" for="day_{{ $day }}">
                                                    {{ $dayLabels[$index] }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="row g-2 working-hours-inputs" style="display: none;">
                                            <div class="col-6">
                                                <label class="form-label">{{ __('providers.open_time') }}</label>
                                                <input type="time" class="form-control form-control-sm"
                                                       name="working_hours[{{ $day }}][open]"
                                                       value="{{ old('working_hours.'.$day.'.open', '09:00') }}">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">{{ __('providers.close_time') }}</label>
                                                <input type="time" class="form-control form-control-sm"
                                                       name="working_hours[{{ $day }}][close]"
                                                       value="{{ old('working_hours.'.$day.'.close', '18:00') }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                                <small class="text-muted">{{ __('providers.working_days_hint') }}</small>
                            </div>
                        </div>

                        <!-- Off Days -->
                        <div class="col-lg-6">
                            <h5 class="mb-3">{{ __('providers.special_off_days') }}</h5>

                            <div class="mb-3">
                                <label class="form-label">{{ __('providers.add_holidays') }}</label>
                                <div id="off-days-container">
                                    <div class="input-group mb-2">
                                        <input type="date" class="form-control" name="off_days[]"
                                               placeholder="Select date">
                                        <button type="button" class="btn btn-outline-success btn-add-off-day">
                                            <i class="bx bx-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted">{{ __('providers.off_days_hint') }}</small>
                            </div>
                        </div>

                        <!-- Provider Documents -->
                        <div class="col-12">
                            <hr class="my-4">
                            <h5 class="mb-3">
                                <i class="bx bx-file me-2"></i>{{ __('providers.provider_documents') }}
                            </h5>
                            <p class="text-muted">{{ __('providers.upload_documents_hint') }}</p>

                            <div class="row">
                                <!-- Freelance License -->
                                <div class="col-md-6 mb-3">
                                    <label for="freelance_license" class="form-label">
                                        <i class="bx bx-file-blank me-1"></i>{{ __('providers.freelance_license') }}
                                    </label>
                                    <input type="file" class="form-control" id="freelance_license" 
                                           name="documents[freelance_license]" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">{{ __('providers.file_format_hint') }}</small>
                                </div>

                                <!-- Commercial Register -->
                                <div class="col-md-6 mb-3">
                                    <label for="commercial_register" class="form-label">
                                        <i class="bx bx-file-blank me-1"></i>{{ __('providers.commercial_register') }}
                                    </label>
                                    <input type="file" class="form-control" id="commercial_register" 
                                           name="documents[commercial_register]" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">{{ __('providers.file_format_hint') }}</small>
                                </div>

                                <!-- Municipal License -->
                                <div class="col-md-6 mb-3">
                                    <label for="municipal_license" class="form-label">
                                        <i class="bx bx-file-blank me-1"></i>{{ __('providers.municipal_license') }}
                                    </label>
                                    <input type="file" class="form-control" id="municipal_license" 
                                           name="documents[municipal_license]" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">{{ __('providers.file_format_hint') }}</small>
                                </div>

                                <!-- National ID -->
                                <div class="col-md-6 mb-3">
                                    <label for="national_id" class="form-label">
                                        <i class="bx bx-file-blank me-1"></i>{{ __('providers.national_id') }}
                                    </label>
                                    <input type="file" class="form-control" id="national_id" 
                                           name="documents[national_id]" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">{{ __('providers.file_format_hint') }}</small>
                                </div>

                                <!-- Agreement Contract -->
                                <div class="col-md-6 mb-3">
                                    <label for="agreement_contract" class="form-label">
                                        <i class="bx bx-file-blank me-1"></i>{{ __('providers.agreement_contract') }}
                                    </label>
                                    <input type="file" class="form-control" id="agreement_contract" 
                                           name="documents[agreement_contract]" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">{{ __('providers.file_format_hint') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('providers.index') }}" class="btn btn-secondary">
                                    <i class="bx bx-x me-1"></i>{{ __('common.cancel') }}
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-save me-1"></i>{{ __('providers.create_provider') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script-bottom')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Google Maps coordinate extraction
    const addressInput = document.getElementById('address');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');

    addressInput.addEventListener('input', function() {
        const value = this.value.trim();

        // Check if it's a Google Maps link
        if (value.includes('google.com/maps') || value.includes('maps.app.goo.gl')) {
            extractCoordinates(value);
        }
    });

    function extractCoordinates(url) {
        let lat = null;
        let lng = null;

        // Pattern 1: @latitude,longitude
        const pattern1 = /@(-?\d+\.\d+),(-?\d+\.\d+)/;
        const match1 = url.match(pattern1);
        if (match1) {
            lat = match1[1];
            lng = match1[2];
        }

        // Pattern 2: q=latitude,longitude or ll=latitude,longitude
        if (!lat) {
            const pattern2 = /[?&](q|ll)=(-?\d+\.\d+),(-?\d+\.\d+)/;
            const match2 = url.match(pattern2);
            if (match2) {
                lat = match2[2];
                lng = match2[3];
            }
        }

        // Pattern 3: /place/.../@latitude,longitude
        if (!lat) {
            const pattern3 = /\/place\/[^/]+\/@(-?\d+\.\d+),(-?\d+\.\d+)/;
            const match3 = url.match(pattern3);
            if (match3) {
                lat = match3[1];
                lng = match3[2];
            }
        }

        if (lat && lng) {
            latInput.value = lat;
            lngInput.value = lng;

            // Show success feedback
            addressInput.classList.add('is-valid');
            setTimeout(() => {
                addressInput.classList.remove('is-valid');
            }, 2000);
        } else {
            // Show warning if no coordinates found
            if (url.includes('google.com/maps')) {
                addressInput.classList.add('is-invalid');
                setTimeout(() => {
                    addressInput.classList.remove('is-invalid');
                }, 2000);
            }
        }
    }

    // Working hours toggle
    const workingDayCheckboxes = document.querySelectorAll('.working-day-checkbox');
    workingDayCheckboxes.forEach(checkbox => {
        // Show/hide hours on page load based on old input
        const hoursDiv = checkbox.closest('.card-body').querySelector('.working-hours-inputs');
        if (checkbox.checked) {
            hoursDiv.style.display = 'flex';
        }

        // Toggle hours on checkbox change
        checkbox.addEventListener('change', function() {
            const hoursDiv = this.closest('.card-body').querySelector('.working-hours-inputs');
            if (this.checked) {
                hoursDiv.style.display = 'flex';
            } else {
                hoursDiv.style.display = 'none';
            }
        });
    });

    // Off days management
    const offDaysContainer = document.getElementById('off-days-container');

    // Add new off day field
    offDaysContainer.addEventListener('click', function(e) {
        if (e.target.closest('.btn-add-off-day')) {
            const newField = document.createElement('div');
            newField.className = 'input-group mb-2';
            newField.innerHTML = `
                <input type="date" class="form-control" name="off_days[]" placeholder="{{ __('providers.select_date') }}">
                <button type="button" class="btn btn-outline-danger btn-remove-off-day">
                    <i class="bx bx-trash"></i>
                </button>
            `;
            offDaysContainer.appendChild(newField);
        }
    });

    // Remove off day field
    offDaysContainer.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-off-day')) {
            e.target.closest('.input-group').remove();
        }
    });
});
</script>
@endsection
