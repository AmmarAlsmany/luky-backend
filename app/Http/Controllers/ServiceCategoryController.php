<?php

namespace App\Http\Controllers;

use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceCategoryController extends Controller
{
    /**
     * Display a listing of categories
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = ServiceCategory::withCount('services')->orderBy('sort_order', 'asc')->orderBy('created_at', 'desc');

        // Search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name_ar', 'LIKE', "%{$search}%")
                  ->orWhere('name_en', 'LIKE', "%{$search}%");
            });
        }

        // Status filter
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $categories = $query->paginate(20);

        // Transform categories data
        $categoriesData = $categories->getCollection()->map(function($category) {
            return [
                'id' => $category->id,
                'name' => app()->getLocale() === 'ar' ? $category->name_ar : $category->name_en,
                'name_en' => $category->name_en,
                'name_ar' => $category->name_ar,
                'description' => $category->description_en ?? $category->description_ar ?? null,
                'icon' => $category->icon,
                'color' => $category->color,
                'image' => $category->image_url ?? null,
                'is_active' => $category->is_active,
                'sort_order' => $category->sort_order,
                'services_count' => $category->services_count,
                'created_at' => $category->created_at,
            ];
        });

        // Get statistics
        $stats = [
            'total' => ServiceCategory::count(),
            'active' => ServiceCategory::where('is_active', true)->count(),
            'inactive' => ServiceCategory::where('is_active', false)->count(),
        ];

        $pagination = [
            'current_page' => $categories->currentPage(),
            'last_page' => $categories->lastPage(),
            'per_page' => $categories->perPage(),
            'total' => $categories->total(),
        ];

        $filters = compact('search', 'status');

        return view('services.categories.list', [
            'categories' => $categoriesData,
            'stats' => $stats,
            'pagination' => $pagination,
            'filters' => $filters
        ]);
    }

    /**
     * Show the form for creating a new category
     */
    public function create()
    {
        return view('services.categories.create');
    }

    /**
     * Store a newly created category
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_en' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle checkbox - if not present, it means unchecked
        $validated['is_active'] = $request->has('is_active') ? true : false;

        // Remove image from validated data (handled separately)
        unset($validated['image']);

        $category = ServiceCategory::create($validated);

        // Handle image upload if provided using Spatie Media Library
        // This automatically creates optimized (200x200) and thumb (80x80) versions
        if ($request->hasFile('image')) {
            $category->addMediaFromRequest('image')
                ->toMediaCollection('category_icon');
        }

        return redirect()->route('categories.index')
            ->with('success', 'Category created successfully');
    }

    /**
     * Display the specified category
     */
    public function show($id)
    {
        $category = ServiceCategory::withCount('services')->findOrFail($id);

        return view('services.categories.show', compact('category'));
    }

    /**
     * Show the form for editing the specified category
     */
    public function edit($id)
    {
        $cat = ServiceCategory::findOrFail($id);
        
        // Transform to array format for view
        $category = [
            'id' => $cat->id,
            'name_en' => $cat->name_en,
            'name_ar' => $cat->name_ar,
            'description_en' => $cat->description_en,
            'description_ar' => $cat->description_ar,
            'icon' => $cat->icon,
            'color' => $cat->color,
            'image' => $cat->image_url,
            'is_active' => $cat->is_active,
            'sort_order' => $cat->sort_order,
            'created_at' => $cat->created_at,
            'updated_at' => $cat->updated_at,
        ];

        return view('services.categories.edit', compact('category'));
    }

    /**
     * Update the specified category
     */
    public function update(Request $request, $id)
    {
        $category = ServiceCategory::findOrFail($id);

        // Handle force toggle from AJAX
        if ($request->has('force_toggle') && $request->force_toggle) {
            $category->update(['is_active' => false]);
            return response()->json([
                'success' => true,
                'message' => 'Category deactivated successfully',
            ]);
        }

        $validated = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_en' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle checkbox - if not present, it means unchecked
        $validated['is_active'] = $request->has('is_active') ? true : false;

        // Remove image from validated data (handled separately)
        unset($validated['image']);

        $category->update($validated);

        // Handle image upload if provided using Spatie Media Library
        // This automatically creates optimized (200x200) and thumb (80x80) versions
        if ($request->hasFile('image')) {
            $category->addMediaFromRequest('image')
                ->toMediaCollection('category_icon');
        }

        return redirect()->route('categories.index')
            ->with('success', 'Category updated successfully');
    }

    /**
     * Remove the specified category
     */
    public function destroy($id)
    {
        $category = ServiceCategory::withCount('services')->findOrFail($id);

        // Check if category has any services
        if ($category->services_count > 0) {
            // Check if any of these services have active bookings
            $activeBookingsCount = \App\Models\BookingItem::whereHas('service', function($q) use ($id) {
                $q->where('category_id', $id);
            })
            ->whereHas('booking', function($q) {
                $q->whereIn('status', ['pending', 'confirmed', 'in_progress']);
            })
            ->count();

            // Check for any bookings (paid or unpaid)
            $totalBookingsCount = \App\Models\BookingItem::whereHas('service', function($q) use ($id) {
                $q->where('category_id', $id);
            })
            ->count();

            if ($activeBookingsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete this category. It has {$category->services_count} service(s) with {$activeBookingsCount} active booking(s). Please complete or cancel these bookings first.",
                    'error_type' => 'active_bookings',
                    'details' => [
                        'services_count' => $category->services_count,
                        'active_bookings' => $activeBookingsCount,
                        'total_bookings' => $totalBookingsCount
                    ]
                ], 422);
            }

            if ($totalBookingsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "This category has {$category->services_count} service(s) with {$totalBookingsCount} booking history. Deleting will affect booking records. Please reassign services to another category first.",
                    'error_type' => 'has_bookings',
                    'details' => [
                        'services_count' => $category->services_count,
                        'total_bookings' => $totalBookingsCount
                    ]
                ], 422);
            }

            // Category has services but no bookings
            return response()->json([
                'success' => false,
                'message' => "Cannot delete category with {$category->services_count} active service(s). Please reassign or delete these services first.",
                'error_type' => 'has_services',
                'details' => [
                    'services_count' => $category->services_count
                ]
            ], 422);
        }

        // Safe to delete - no services in this category
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    /**
     * Toggle category status
     */
    public function toggleStatus($id)
    {
        $category = ServiceCategory::withCount('services')->findOrFail($id);
        
        // If trying to deactivate a category
        if ($category->is_active) {
            // Check for active services in this category
            $activeServicesCount = \App\Models\Service::where('category_id', $id)
                ->where('is_active', true)
                ->count();

            if ($activeServicesCount > 0) {
                // Check for active bookings on these services
                $activeBookingsCount = \App\Models\BookingItem::whereHas('service', function($q) use ($id) {
                    $q->where('category_id', $id)
                      ->where('is_active', true);
                })
                ->whereHas('booking', function($q) {
                    $q->whereIn('status', ['pending', 'confirmed', 'in_progress']);
                })
                ->count();

                if ($activeBookingsCount > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => "Cannot deactivate this category. It has {$activeServicesCount} active service(s) with {$activeBookingsCount} ongoing booking(s). Please complete or cancel these bookings first.",
                    ], 422);
                }
                
                // Has services but no active bookings - warn user
                return response()->json([
                    'success' => false,
                    'message' => "This category has {$activeServicesCount} active service(s). Deactivating the category will hide it from users, but services will remain active. Are you sure?",
                    'warning' => true,
                    'services_count' => $activeServicesCount,
                ], 422);
            }
        }

        $category->update(['is_active' => !$category->is_active]);

        $status = $category->is_active ? 'activated' : 'deactivated';
        $message = "Category {$status} successfully";
        
        // Add info about services if deactivating
        if (!$category->is_active && $category->services_count > 0) {
            $message .= ". Note: {$category->services_count} service(s) in this category remain active.";
        }
        
        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
