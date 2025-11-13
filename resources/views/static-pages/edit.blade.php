@extends('layouts.vertical', ['title' => __('common.edit') . ' ' . __('common.page')])

@section('css')
<!-- Quill Editor CSS -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="py-3 mb-4">
            <span class="text-muted fw-light">{{ __('common.content_management') }} /</span>
            {{ __('common.edit') . ' ' . __('common.page') }}
        </h4>

        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="{{ route('static-pages.update', $page->id) }}" method="POST" id="staticPageForm" onsubmit="return syncQuillContent();">
                            @csrf
                            @method('PUT')

                            <!-- System Page Notice -->
                            @if(in_array($page->slug, ['terms-and-conditions', 'privacy-policy', 'about-us', 'faq']))
                                <div class="alert alert-info mb-3">
                                    <i class="bx bx-info-circle me-2"></i>{{ __('common.system_page_notice') }}
                                </div>
                            @endif

                            <!-- English Title -->
                            <div class="mb-3">
                                <label for="title_en" class="form-label">{{ __('common.title') }} ({{ __('common.english') }})</label>
                                <input type="text" class="form-control @error('title_en') is-invalid @enderror" 
                                       id="title_en" name="title_en" value="{{ old('title_en', $page->title_en) }}" required>
                                @error('title_en')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Arabic Title -->
                            <div class="mb-3">
                                <label for="title_ar" class="form-label">{{ __('common.title') }} ({{ __('common.arabic') }})</label>
                                <input type="text" class="form-control @error('title_ar') is-invalid @enderror" 
                                       id="title_ar" name="title_ar" value="{{ old('title_ar', $page->title_ar) }}" required dir="rtl">
                                @error('title_ar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Slug -->
                            <div class="mb-3">
                                <label for="slug" class="form-label">{{ __('common.slug') }}</label>
                                <input type="text" class="form-control @error('slug') is-invalid @enderror" 
                                       id="slug" name="slug" value="{{ old('slug', $page->slug) }}" 
                                       {{ in_array($page->slug, ['terms-and-conditions', 'privacy-policy', 'about-us', 'faq']) ? 'readonly' : 'required' }}>
                                <small class="text-muted">{{ __('common.slug_help') }}</small>
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- English Content -->
                            <div class="mb-3">
                                <label for="content_en" class="form-label">{{ __('common.content') }} ({{ __('common.english') }})</label>
                                <div id="editor_en" style="height: 300px;">{!! old('content_en', $page->content_en) !!}</div>
                                <input type="hidden" name="content_en" id="content_en">
                                @error('content_en')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Arabic Content -->
                            <div class="mb-3">
                                <label for="content_ar" class="form-label">{{ __('common.content') }} ({{ __('common.arabic') }})</label>
                                <div id="editor_ar" style="height: 300px;" dir="rtl">{!! old('content_ar', $page->content_ar) !!}</div>
                                <input type="hidden" name="content_ar" id="content_ar">
                                @error('content_ar')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- English Meta Description -->
                            <div class="mb-3">
                                <label for="meta_description_en" class="form-label">{{ __('common.meta_description') }} ({{ __('common.english') }})</label>
                                <textarea class="form-control @error('meta_description_en') is-invalid @enderror" 
                                          id="meta_description_en" name="meta_description_en" rows="3">{{ old('meta_description_en', $page->meta_description_en) }}</textarea>
                                @error('meta_description_en')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Arabic Meta Description -->
                            <div class="mb-3">
                                <label for="meta_description_ar" class="form-label">{{ __('common.meta_description') }} ({{ __('common.arabic') }})</label>
                                <textarea class="form-control @error('meta_description_ar') is-invalid @enderror" 
                                          id="meta_description_ar" name="meta_description_ar" rows="3" dir="rtl">{{ old('meta_description_ar', $page->meta_description_ar) }}</textarea>
                                @error('meta_description_ar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Status -->
                            <div class="mb-3">
                                <label for="is_published" class="form-label">{{ __('common.status') }}</label>
                                <select class="form-select @error('is_published') is-invalid @enderror" id="is_published" name="is_published" required>
                                    <option value="0" {{ old('is_published', $page->is_published) == 0 ? 'selected' : '' }}>{{ __('common.draft') }}</option>
                                    <option value="1" {{ old('is_published', $page->is_published) == 1 ? 'selected' : '' }}>{{ __('common.published') }}</option>
                                </select>
                                @error('is_published')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Buttons -->
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bx bx-save me-1"></i>{{ __('common.update') }}
                                </button>
                                <a href="{{ route('static-pages.index') }}" class="btn btn-outline-secondary">
                                    <i class="bx bx-x me-1"></i>{{ __('common.cancel') }}
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<!-- Quill Editor JS -->
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
// Global Quill instances
let quillEn, quillAr;

// Function to sync content before form submission
function syncQuillContent() {
    console.log('syncQuillContent called');
    
    if (!quillEn || !quillAr) {
        console.error('Quill editors not initialized!');
        alert('Editors are not ready. Please wait and try again.');
        return false;
    }
    
    // Get content from Quill editors
    let contentEn = quillEn.root.innerHTML;
    let contentAr = quillAr.root.innerHTML;
    
    console.log('Raw EN:', contentEn);
    console.log('Raw AR:', contentAr);
    
    // Quill returns <p><br></p> for empty content, treat as empty
    if (contentEn === '<p><br></p>') contentEn = '';
    if (contentAr === '<p><br></p>') contentAr = '';
    
    // Set values to hidden fields
    document.getElementById('content_en').value = contentEn;
    document.getElementById('content_ar').value = contentAr;
    
    console.log('Final EN:', document.getElementById('content_en').value);
    console.log('Final AR:', document.getElementById('content_ar').value);
    
    return true; // Allow form submission
}

// Wait for both DOM and Quill to be ready
window.addEventListener('load', function() {
    setTimeout(function() {
        console.log('Initializing Quill editors...');
        console.log('Quill available:', typeof Quill !== 'undefined');
        
        if (typeof Quill === 'undefined') {
            console.error('Quill is not loaded!');
            alert('Error: Rich text editor failed to load. Please refresh the page.');
            return;
        }
        
        try {
            // Auto-generate slug from English title (only for non-system pages)
            @if(!in_array($page->slug, ['terms-and-conditions', 'privacy-policy', 'about-us', 'faq']))
            document.getElementById('title_en').addEventListener('input', function(e) {
                const slug = e.target.value
                    .toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .trim();
                document.getElementById('slug').value = slug;
            });
            @endif

            // Initialize Quill editors
            console.log('Creating English editor...');
            quillEn = new Quill('#editor_en', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'align': [] }],
                        ['link', 'image'],
                        ['clean']
                    ]
                }
            });
            console.log('English editor created:', quillEn);

            console.log('Creating Arabic editor...');
            quillAr = new Quill('#editor_ar', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'align': [] }],
                        ['link', 'image'],
                        ['clean']
                    ]
                }
            });
            console.log('Arabic editor created:', quillAr);
            
            console.log('Quill editors initialized successfully!');
        } catch (error) {
            console.error('Error initializing Quill:', error);
            alert('Error initializing editor: ' + error.message);
        }
    }, 500);
});
</script>
@endsection
