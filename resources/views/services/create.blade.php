@extends('layouts.vertical', ['title' => 'Add Service'])

@section('content')
    {{-- Page Header --}}
    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-1 fw-bold">Create New Service</h4>
                    <p class="text-muted mb-0">Add a new service to your platform</p>
                </div>
                <a href="{{ route('services.index') }}" class="btn btn-soft-secondary">
                    <i class="mdi mdi-arrow-left me-1"></i> Back to Services
                </a>
            </div>
        </div>
    </div>

    <form action="{{ route('services.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-xl-8">
                {{-- Service Information --}}
                <div class="card border shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="card-title mb-0 fw-semibold">
                            <i class="mdi mdi-information-outline text-primary me-2"></i>Service Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="provider_id" class="form-label fw-semibold">Provider <span class="text-danger">*</span></label>
                                <select class="form-select @error('provider_id') is-invalid @enderror"
                                    id="provider_id" name="provider_id" required>
                                    <option value="">Select Provider</option>
                                    @foreach($providers as $provider)
                                        <option value="{{ $provider->id }}"
                                            {{ old('provider_id', $selectedProviderId ?? null) == $provider->id ? 'selected' : '' }}>
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
                                <label for="name_en" class="form-label fw-semibold">Service Name (English) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name_en') is-invalid @enderror"
                                    id="name_en" name="name_en" value="{{ old('name_en') }}"
                                    placeholder="e.g., Hair Cut & Styling" required>
                                @error('name_en')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="name_ar" class="form-label fw-semibold">Service Name (Arabic) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name_ar') is-invalid @enderror"
                                    id="name_ar" name="name_ar" value="{{ old('name_ar') }}"
                                    placeholder="مثال: قص وتصفيف الشعر" required dir="rtl">
                                @error('name_ar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="description_en" class="form-label fw-semibold">Description (English)</label>
                                <textarea class="form-control @error('description_en') is-invalid @enderror"
                                    id="description_en" name="description_en" rows="4"
                                    placeholder="Describe the service in detail...">{{ old('description_en') }}</textarea>
                                @error('description_en')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="description_ar" class="form-label fw-semibold">Description (Arabic)</label>
                                <textarea class="form-control @error('description_ar') is-invalid @enderror"
                                    id="description_ar" name="description_ar" rows="4"
                                    placeholder="صف الخدمة بالتفصيل..." dir="rtl">{{ old('description_ar') }}</textarea>
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
                            <i class="mdi mdi-cash-multiple text-success me-2"></i>Pricing & Duration
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="price" class="form-label fw-semibold">Price (SAR) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="mdi mdi-currency-usd"></i></span>
                                    <input type="number" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror"
                                        id="price" name="price" value="{{ old('price') }}"
                                        placeholder="0.00" required>
                                    <span class="input-group-text bg-light">SAR</span>
                                    @error('price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="duration_minutes" class="form-label fw-semibold">Duration (Minutes) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="mdi mdi-clock-outline"></i></span>
                                    <input type="number" min="15" max="480" class="form-control @error('duration_minutes') is-invalid @enderror"
                                        id="duration_minutes" name="duration_minutes" value="{{ old('duration_minutes', 60) }}"
                                        placeholder="60" required>
                                    <span class="input-group-text bg-light">min</span>
                                    @error('duration_minutes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <small class="text-muted">Range: 15 - 480 minutes (8 hours)</small>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-info border-info mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="mdi mdi-information fs-20 me-2"></i>
                                        <div>
                                            <strong>Service Location Options</strong>
                                            <p class="mb-0 mt-1 small">Choose where this service can be provided</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="available_at_home"
                                        name="available_at_home" value="1" {{ old('available_at_home') ? 'checked' : '' }}
                                        onchange="toggleHomeServicePrice()">
                                    <label class="form-check-label fw-semibold" for="available_at_home">
                                        <i class="mdi mdi-home me-1 text-success"></i>Available at Customer's Home
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6" id="home_price_container" style="display: {{ old('available_at_home') ? 'block' : 'none' }};">
                                <label for="home_service_price" class="form-label fw-semibold">Home Service Price (SAR)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="mdi mdi-home"></i></span>
                                    <input type="number" step="0.01" min="0" class="form-control @error('home_service_price') is-invalid @enderror"
                                        id="home_service_price" name="home_service_price" value="{{ old('home_service_price') }}"
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

                {{-- Service Images --}}
                <div class="card border shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="card-title mb-0 fw-semibold">
                            <i class="mdi mdi-image-multiple text-info me-2"></i>Service Images
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info border-info mb-3">
                            <div class="d-flex align-items-center">
                                <i class="mdi mdi-information fs-20 me-2"></i>
                                <div>
                                    <strong>Image Guidelines</strong>
                                    <ul class="mb-0 mt-1 small ps-3">
                                        <li>Upload up to 3 images</li>
                                        <li>Supported formats: JPG, PNG</li>
                                        <li>Max size: 5MB per image</li>
                                        <li>Images will be shown to clients</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="service_images" class="form-label fw-semibold">
                                <i class="mdi mdi-camera me-1"></i>Upload Service Images
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
                            <small class="text-muted">You can select up to 3 images</small>
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
                            <i class="mdi mdi-cog-outline text-warning me-2"></i>Status & Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 p-3 bg-light rounded">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="is_active"
                                    name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="is_active">
                                    <i class="mdi mdi-check-circle text-success me-1"></i>Active
                                </label>
                            </div>
                            <small class="text-muted">Service will be visible and bookable by clients</small>
                        </div>

                        <div class="mb-3 p-3 bg-light rounded">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="is_featured"
                                    name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="is_featured">
                                    <i class="mdi mdi-star text-warning me-1"></i>Featured
                                </label>
                            </div>
                            <small class="text-muted">Highlight this service in featured section</small>
                        </div>

                        <div>
                            <label for="sort_order" class="form-label fw-semibold">Display Order</label>
                            <input type="number" class="form-control @error('sort_order') is-invalid @enderror"
                                id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}"
                                placeholder="0">
                            @error('sort_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Lower numbers appear first in listings</small>
                        </div>
                    </div>
                </div>

                {{-- Submit Actions --}}
                <div class="card border shadow-sm">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="mdi mdi-check-circle me-1"></i> Create Service
                            </button>
                            <a href="{{ route('services.index') }}" class="btn btn-soft-secondary">
                                <i class="mdi mdi-close-circle me-1"></i> Cancel
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Help Card --}}
                <div class="card border-0 bg-primary-subtle">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <i class="mdi mdi-lightbulb-outline fs-24 text-primary me-2"></i>
                            <div>
                                <h6 class="fw-semibold text-primary">Quick Tips</h6>
                                <ul class="small text-muted mb-0 ps-3">
                                    <li>Use clear, descriptive service names</li>
                                    <li>Set competitive pricing</li>
                                    <li>Accurate duration helps scheduling</li>
                                    <li>Add home service for flexibility</li>
                                </ul>
                            </div>
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
        const filesArray = Array.from(input.files).slice(0, 3); // Maximum 3 images

        filesArray.forEach((file, index) => {
            const reader = new FileReader();

            reader.onload = function(e) {
                const col = document.createElement('div');
                col.className = 'col-4';
                col.innerHTML = `
                    <div class="position-relative">
                        <img src="${e.target.result}" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">
                        <span class="badge bg-primary position-absolute top-0 start-0 m-1">${index + 1}</span>
                    </div>
                `;
                preview.appendChild(col);
            };

            reader.readAsDataURL(file);
        });

        // Show warning if more than 3 images selected
        if (input.files.length > 3) {
            alert('Maximum 3 images allowed. Only the first 3 will be uploaded.');
        }
    }
}
</script>
@endsection
