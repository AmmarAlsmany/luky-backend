@extends('layouts.vertical', ['title' => __('services.edit_service')])

@section('content')
    {{-- Page Header --}}
    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-1 fw-bold">{{ __('services.edit_service') }}</h4>
                    <p class="text-muted mb-0">{{ __('services.update_service_info') }}</p>
                </div>
                <a href="{{ route('services.index') }}" class="btn btn-soft-secondary">
                    <i class="mdi mdi-arrow-left me-1"></i> {{ __('services.back_to_services') }}
                </a>
            </div>
        </div>
    </div>

    <form action="{{ route('services.update', $service->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-xl-8">
                {{-- Service Information --}}
                <div class="card border shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="card-title mb-0 fw-semibold">
                            <i class="mdi mdi-information-outline text-primary me-2"></i>{{ __('services.service_information') }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="provider_id" class="form-label fw-semibold">{{ __('services.provider') }} <span class="text-danger">*</span></label>
                                <select class="form-select @error('provider_id') is-invalid @enderror"
                                    id="provider_id" name="provider_id" required>
                                    <option value="">{{ __('services.select_provider') }}</option>
                                    @foreach($providers as $provider)
                                        <option value="{{ $provider->id }}"
                                            {{ old('provider_id', $service->provider_id) == $provider->id ? 'selected' : '' }}>
                                            {{ $provider->business_name ?? $provider->user->name ?? 'N/A' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('provider_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Category field removed - ServiceCategory system deprecated --}}
                            {{-- Providers now use custom categories via ProviderServiceCategory --}}

                            <div class="col-12">
                                <label for="name_en" class="form-label fw-semibold">{{ __('services.service_name_en') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name_en') is-invalid @enderror"
                                    id="name_en" name="name_en" value="{{ old('name_en', $service->name_en) }}"
                                    placeholder="{{ __('services.eg_hair_cut') }}" required>
                                @error('name_en')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="name_ar" class="form-label fw-semibold">{{ __('services.service_name_ar') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name_ar') is-invalid @enderror"
                                    id="name_ar" name="name_ar" value="{{ old('name_ar', $service->name_ar) }}"
                                    placeholder="{{ __('services.eg_hair_cut_ar') }}" required dir="rtl">
                                @error('name_ar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="description_en" class="form-label fw-semibold">{{ __('services.description_en') }}</label>
                                <textarea class="form-control @error('description_en') is-invalid @enderror"
                                    id="description_en" name="description_en" rows="4"
                                    placeholder="{{ __('services.describe_service') }}">{{ old('description_en', $service->description_en) }}</textarea>
                                @error('description_en')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="description_ar" class="form-label fw-semibold">{{ __('services.description_ar') }}</label>
                                <textarea class="form-control @error('description_ar') is-invalid @enderror"
                                    id="description_ar" name="description_ar" rows="4"
                                    placeholder="{{ __('services.describe_service_ar') }}" dir="rtl">{{ old('description_ar', $service->description_ar) }}</textarea>
                                @error('description_ar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pricing & Duration --}}
                <div class="card border shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="card-title mb-0 fw-semibold">
                            <i class="mdi mdi-cash-multiple text-success me-2"></i>{{ __('services.pricing_duration') }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="price" class="form-label fw-semibold">{{ __('services.price_sar') }} <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="mdi mdi-currency-usd"></i></span>
                                    <input type="number" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror"
                                        id="price" name="price" value="{{ old('price', $service->price) }}"
                                        placeholder="0.00" required>
                                    <span class="input-group-text bg-light">SAR</span>
                                    @error('price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="duration_minutes" class="form-label fw-semibold">{{ __('services.duration_minutes') }} <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="mdi mdi-clock-outline"></i></span>
                                    <input type="number" min="15" max="480" class="form-control @error('duration_minutes') is-invalid @enderror"
                                        id="duration_minutes" name="duration_minutes" value="{{ old('duration_minutes', $service->duration_minutes) }}"
                                        placeholder="60" required>
                                    <span class="input-group-text bg-light">min</span>
                                    @error('duration_minutes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <small class="text-muted">{{ __('services.duration_range') }}</small>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-info border-info mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="mdi mdi-information fs-20 me-2"></i>
                                        <div>
                                            <strong>{{ __('services.service_location_options') }}</strong>
                                            <p class="mb-0 mt-1 small">{{ __('services.choose_service_location') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="available_at_home"
                                        name="available_at_home" value="1"
                                        {{ old('available_at_home', $service->available_at_home) ? 'checked' : '' }}
                                        onchange="toggleHomeServicePrice()">
                                    <label class="form-check-label fw-semibold" for="available_at_home">
                                        <i class="mdi mdi-home me-1 text-success"></i>{{ __('services.available_at_home') }}
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6" id="home_price_container"
                                style="display: {{ old('available_at_home', $service->available_at_home) ? 'block' : 'none' }};">
                                <label for="home_service_price" class="form-label fw-semibold">{{ __('services.home_service_price') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="mdi mdi-home"></i></span>
                                    <input type="number" step="0.01" min="0" class="form-control @error('home_service_price') is-invalid @enderror"
                                        id="home_service_price" name="home_service_price"
                                        value="{{ old('home_service_price', $service->home_service_price) }}"
                                        placeholder="0.00">
                                    <span class="input-group-text bg-light">SAR</span>
                                    @error('home_service_price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                {{-- Service Images --}}
                <div class="card border shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="card-title mb-0 fw-semibold">
                            <i class="mdi mdi-image-multiple text-info me-2"></i>Service Images
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Current Images -->
                        @if($service->getMedia('service_images')->count() > 0)
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Current Images ({{ $service->getMedia('service_images')->count() }})</label>
                                <div class="row g-2">
                                    @foreach($service->getMedia('service_images') as $media)
                                        <div class="col-4">
                                            <div class="position-relative">
                                                <img src="{{ $media->getUrl() }}" class="img-thumbnail" style="width: 100%; height: 80px; object-fit: cover;">
                                                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1"
                                                        onclick="deleteImage({{ $media->id }})" title="Delete">
                                                    <i class="mdi mdi-delete"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="alert alert-info border-info mb-3">
                            <div class="d-flex align-items-center">
                                <i class="mdi mdi-information fs-20 me-2"></i>
                                <div>
                                    <strong>Image Guidelines</strong>
                                    <ul class="mb-0 mt-1 small ps-3">
                                        <li>Upload up to 3 images total</li>
                                        <li>Supported formats: JPG, PNG</li>
                                        <li>Max size: 5MB per image</li>
                                        <li>Images will be shown to clients</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="service_images" class="form-label fw-semibold">
                                <i class="mdi mdi-camera me-1"></i>Add More Images (max {{ 3 - $service->getMedia('service_images')->count() }})
                            </label>
                            <input type="file"
                                   class="form-control @error('service_images') is-invalid @enderror"
                                   id="service_images"
                                   name="service_images[]"
                                   accept="image/jpeg,image/png,image/jpg"
                                   multiple
                                   onchange="previewImages(this)">
                            @error('service_images')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">You can add up to {{ 3 - $service->getMedia('service_images')->count() }} more image(s)</small>
                        </div>

                        <!-- Image Preview -->
                        <div id="image-preview" class="row g-2 mt-2"></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                {{-- Status & Settings --}}
                <div class="card border shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="card-title mb-0 fw-semibold">
                            <i class="mdi mdi-cog-outline text-warning me-2"></i>{{ __('services.status_settings') }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 p-3 bg-light rounded">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="is_active"
                                    name="is_active" value="1"
                                    {{ old('is_active', $service->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="is_active">
                                    <i class="mdi mdi-check-circle text-success me-1"></i>{{ __('services.active') }}
                                </label>
                            </div>
                            <small class="text-muted">{{ __('services.service_visible_bookable') }}</small>
                        </div>

                        <div class="mb-3 p-3 bg-light rounded">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="is_featured"
                                    name="is_featured" value="1"
                                    {{ old('is_featured', $service->is_featured) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="is_featured">
                                    <i class="mdi mdi-star text-warning me-1"></i>{{ __('services.featured') }}
                                </label>
                            </div>
                            <small class="text-muted">{{ __('services.highlight_featured') }}</small>
                        </div>

                        <div>
                            <label for="sort_order" class="form-label fw-semibold">{{ __('services.display_order') }}</label>
                            <input type="number" class="form-control @error('sort_order') is-invalid @enderror"
                                id="sort_order" name="sort_order" value="{{ old('sort_order', $service->sort_order) }}"
                                placeholder="0">
                            @error('sort_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">{{ __('services.lower_numbers_first') }}</small>
                        </div>
                    </div>
                </div>

                {{-- Submit Actions --}}
                <div class="card border shadow-sm">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="mdi mdi-check-circle me-1"></i> {{ __('services.update_service') }}
                            </button>
                            <a href="{{ route('services.index') }}" class="btn btn-soft-secondary">
                                <i class="mdi mdi-close-circle me-1"></i> {{ __('services.cancel') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('script')
<script>
function toggleHomeServicePrice() {
    const checkbox = document.getElementById('available_at_home');
    const container = document.getElementById('home_price_container');
    const input = document.getElementById('home_service_price');

    if (checkbox.checked) {
        container.style.display = 'block';
        input.required = true;
    } else {
        container.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}

function previewImages(input) {
    const preview = document.getElementById('image-preview');
    preview.innerHTML = '';

    if (input.files) {
        const maxImages = {{ 3 - $service->getMedia('service_images')->count() }};
        const filesArray = Array.from(input.files).slice(0, maxImages);

        filesArray.forEach((file, index) => {
            const reader = new FileReader();

            reader.onload = function(e) {
                const col = document.createElement('div');
                col.className = 'col-4';
                col.innerHTML = `
                    <div class="position-relative">
                        <img src="${e.target.result}" class="img-thumbnail" style="width: 100%; height: 80px; object-fit: cover;">
                        <span class="badge bg-success position-absolute top-0 start-0 m-1">New ${index + 1}</span>
                    </div>
                `;
                preview.appendChild(col);
            };

            reader.readAsDataURL(file);
        });

        if (input.files.length > maxImages) {
            alert(`Maximum ${maxImages} images can be added. Only the first ${maxImages} will be uploaded.`);
        }
    }
}

function deleteImage(mediaId) {
    if (!confirm('Are you sure you want to delete this image?')) {
        return;
    }

    fetch(`/admin/services/media/${mediaId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error deleting image');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting image');
    });
}
</script>
@endsection
