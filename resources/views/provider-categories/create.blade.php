@extends('layouts.vertical', ['title' => 'Add Provider Category'])

@section('css')
    @vite(['node_modules/dropzone/dist/dropzone.css'])
@endsection

@section('content')
    {{-- Page Header --}}
    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-1 fw-bold">Create New Provider Business Type</h4>
                    <p class="text-muted mb-0">Add a new business type that providers can select during registration</p>
                </div>
                <a href="{{ route('provider-categories.index') }}" class="btn btn-soft-secondary">
                    <i class="mdi mdi-arrow-left me-1"></i> Back to Business Types
                </a>
            </div>
        </div>
    </div>

    <form action="{{ route('provider-categories.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-xl-8">
                {{-- Category Information --}}
                <div class="card border shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="card-title mb-0 fw-semibold">
                            <i class="mdi mdi-information-outline text-primary me-2"></i>Business Type Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name_en" class="form-label fw-semibold">Business Type Name (English) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name_en') is-invalid @enderror"
                                    id="name_en" name="name_en" value="{{ old('name_en') }}"
                                    placeholder="e.g., Women's Beauty Salon" required>
                                @error('name_en')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="name_ar" class="form-label fw-semibold">Business Type Name (Arabic) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name_ar') is-invalid @enderror"
                                    id="name_ar" name="name_ar" value="{{ old('name_ar') }}"
                                    placeholder="مثال: صالون تجميل نسائي" required dir="rtl">
                                @error('name_ar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="description_en" class="form-label fw-semibold">Description (English)</label>
                                <textarea class="form-control @error('description_en') is-invalid @enderror"
                                    id="description_en" name="description_en" rows="4"
                                    placeholder="Describe this business type in English...">{{ old('description_en') }}</textarea>
                                @error('description_en')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="description_ar" class="form-label fw-semibold">Description (Arabic)</label>
                                <textarea class="form-control @error('description_ar') is-invalid @enderror"
                                    id="description_ar" name="description_ar" rows="4"
                                    placeholder="صف هذا النوع من الأعمال بالعربية..." dir="rtl">{{ old('description_ar') }}</textarea>
                                @error('description_ar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Visual Settings --}}
                <div class="card border shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="card-title mb-0 fw-semibold">
                            <i class="mdi mdi-palette-outline text-info me-2"></i>Visual Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="icon" class="form-label fw-semibold">Icon Name</label>
                                <input type="text" class="form-control @error('icon') is-invalid @enderror"
                                    id="icon" name="icon" value="{{ old('icon') }}"
                                    placeholder="e.g., content-cut, spa, makeup">
                                @error('icon')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    <i class="mdi mdi-information-outline me-1"></i>
                                    Use Material Design Icon names (without 'mdi-' prefix).
                                    <a href="https://pictogrammers.com/library/mdi/" target="_blank">Browse icons</a>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="color" class="form-label fw-semibold">Brand Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color @error('color') is-invalid @enderror"
                                        id="color" name="color" value="{{ old('color', '#FF69B4') }}"
                                        title="Choose brand color">
                                    <input type="text" class="form-control" id="color-text"
                                        value="{{ old('color', '#FF69B4') }}"
                                        placeholder="#FF69B4"
                                        onchange="document.getElementById('color').value = this.value">
                                </div>
                                @error('color')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    <i class="mdi mdi-information-outline me-1"></i>
                                    Color used for category identification in the app
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="sort_order" class="form-label fw-semibold">Sort Order</label>
                                <input type="number" class="form-control @error('sort_order') is-invalid @enderror"
                                    id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}"
                                    placeholder="0">
                                @error('sort_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    <i class="mdi mdi-information-outline me-1"></i>
                                    Lower numbers appear first. Default is 0.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Category Image --}}
                <div class="card border shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="card-title mb-0 fw-semibold">
                            <i class="mdi mdi-image-outline text-info me-2"></i>Business Type Icon/Image
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="image" class="form-label fw-semibold">Upload Icon/Image</label>
                            <input type="file" class="form-control @error('image') is-invalid @enderror"
                                id="image" name="image" accept="image/*" onchange="previewImage(event)">
                            @error('image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                <i class="mdi mdi-information-outline me-1"></i>
                                Recommended: 200x200px, Max: 2MB (JPG, PNG)
                            </div>
                        </div>

                        <div id="image-preview" class="mt-3 text-center" style="display: none;">
                            <div class="border rounded-3 p-3 bg-light">
                                <p class="text-muted mb-2 small">Image Preview</p>
                                <img id="preview-img" src="" alt="Preview" class="img-thumbnail" style="max-height: 250px; max-width: 100%;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                {{-- Status --}}
                <div class="card border shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="card-title mb-0 fw-semibold">
                            <i class="mdi mdi-toggle-switch text-success me-2"></i>Availability Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="p-3 bg-light rounded">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                <label class="form-check-label fw-semibold" for="is_active">
                                    <i class="mdi mdi-eye text-success me-1"></i>Active
                                </label>
                            </div>
                            <p class="text-muted fs-13 mb-0">When active, this business type will be available for providers to select during registration</p>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="card border shadow-sm">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="mdi mdi-check-circle me-1"></i> Create Business Type
                            </button>
                            <a href="{{ route('provider-categories.index') }}" class="btn btn-soft-secondary">
                                <i class="mdi mdi-close-circle me-1"></i> Cancel
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Help Card --}}
                <div class="card border-0 bg-info-subtle">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <i class="mdi mdi-lightbulb-outline fs-24 text-info me-2"></i>
                            <div>
                                <h6 class="fw-semibold text-info">Business Type Tips</h6>
                                <ul class="small text-muted mb-0 ps-3">
                                    <li>Use clear, industry-standard names</li>
                                    <li>Choose distinctive colors for each type</li>
                                    <li>Select appropriate icons that represent the business</li>
                                    <li>Keep descriptions helpful for providers</li>
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
        // Sync color picker with text input
        document.getElementById('color').addEventListener('input', function(e) {
            document.getElementById('color-text').value = e.target.value.toUpperCase();
        });

        document.getElementById('color-text').addEventListener('input', function(e) {
            const color = e.target.value;
            if (/^#[0-9A-F]{6}$/i.test(color)) {
                document.getElementById('color').value = color;
            }
        });

        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-img').src = e.target.result;
                    document.getElementById('image-preview').style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        }
    </script>
@endsection
