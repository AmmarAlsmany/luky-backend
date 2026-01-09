<?php

namespace App\Http\Controllers;

use App\Models\ProviderCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProviderCategoryController extends Controller
{
    /**
     * Display a listing of provider categories
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = ProviderCategory::withCount('providers')->orderBy('sort_order', 'asc')->orderBy('created_at', 'desc');

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
            $locale = app()->getLocale();
            return [
                'id' => $category->id,
                'name' => $locale === 'ar' ? $category->name_ar : $category->name_en,
                'name_en' => $category->name_en,
                'name_ar' => $category->name_ar,
                'description' => $locale === 'ar'
                    ? ($category->description_ar ?? $category->description_en)
                    : ($category->description_en ?? $category->description_ar),
                'icon' => $category->icon,
                'color' => $category->color,
                'image' => $category->icon_url ?? null,
                'is_active' => $category->is_active,
                'sort_order' => $category->sort_order,
                'providers_count' => $category->providers_count,
                'created_at' => $category->created_at,
            ];
        });

        // Get statistics
        $stats = [
            'total' => ProviderCategory::count(),
            'active' => ProviderCategory::where('is_active', true)->count(),
            'inactive' => ProviderCategory::where('is_active', false)->count(),
        ];

        $pagination = [
            'current_page' => $categories->currentPage(),
            'last_page' => $categories->lastPage(),
            'per_page' => $categories->perPage(),
            'total' => $categories->total(),
        ];

        $filters = compact('search', 'status');

        return view('provider-categories.list', [
            'categories' => $categoriesData,
            'stats' => $stats,
            'pagination' => $pagination,
            'filters' => $filters
        ]);
    }

    /**
     * Show the form for creating a new provider category
     */
    public function create()
    {
        return view('provider-categories.create');
    }

    /**
     * Store a newly created provider category
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

        $category = ProviderCategory::create($validated);

        // Handle image upload if provided using Spatie Media Library
        // This automatically creates optimized (200x200) and thumb (100x100) versions
        if ($request->hasFile('image')) {
            $category->addMediaFromRequest('image')
                ->toMediaCollection('category_icon');
        }

        return redirect()->route('provider-categories.index')
            ->with('success', 'Provider category created successfully');
    }

    /**
     * Display the specified provider category
     */
    public function show($id)
    {
        $category = ProviderCategory::withCount('providers')->findOrFail($id);

        return view('provider-categories.show', compact('category'));
    }

    /**
     * Show the form for editing the specified provider category
     */
    public function edit($id)
    {
        $cat = ProviderCategory::findOrFail($id);

        // Transform to array format for view
        $category = [
            'id' => $cat->id,
            'name_en' => $cat->name_en,
            'name_ar' => $cat->name_ar,
            'description_en' => $cat->description_en,
            'description_ar' => $cat->description_ar,
            'icon' => $cat->icon,
            'color' => $cat->color,
            'image' => $cat->icon_url,
            'is_active' => $cat->is_active,
            'sort_order' => $cat->sort_order,
            'created_at' => $cat->created_at,
            'updated_at' => $cat->updated_at,
        ];

        return view('provider-categories.edit', compact('category'));
    }

    /**
     * Update the specified provider category
     */
    public function update(Request $request, $id)
    {
        $category = ProviderCategory::findOrFail($id);

        // Handle force toggle from AJAX
        if ($request->has('force_toggle') && $request->force_toggle) {
            $category->update(['is_active' => false]);
            return response()->json([
                'success' => true,
                'message' => 'Provider category deactivated successfully',
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
        // This automatically creates optimized (200x200) and thumb (100x100) versions
        if ($request->hasFile('image')) {
            $category->addMediaFromRequest('image')
                ->toMediaCollection('category_icon');
        }

        return redirect()->route('provider-categories.index')
            ->with('success', 'Provider category updated successfully');
    }

    /**
     * Remove the specified provider category
     */
    public function destroy($id)
    {
        $category = ProviderCategory::withCount('providers')->findOrFail($id);

        // Check if category has any providers
        if ($category->providers_count > 0) {
            // Check if any of these providers have active bookings
            $activeBookingsCount = \App\Models\Booking::whereHas('provider', function($q) use ($id) {
                $q->where('provider_category_id', $id);
            })
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->count();

            // Check for any bookings (paid or unpaid)
            $totalBookingsCount = \App\Models\Booking::whereHas('provider', function($q) use ($id) {
                $q->where('provider_category_id', $id);
            })
            ->count();

            if ($activeBookingsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete this provider category. It has {$category->providers_count} provider(s) with {$activeBookingsCount} active booking(s). Please complete or cancel these bookings first.",
                    'error_type' => 'active_bookings',
                    'details' => [
                        'providers_count' => $category->providers_count,
                        'active_bookings' => $activeBookingsCount,
                        'total_bookings' => $totalBookingsCount
                    ]
                ], 422);
            }

            if ($totalBookingsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "This provider category has {$category->providers_count} provider(s) with {$totalBookingsCount} booking history. Deleting will affect booking records. Please reassign providers to another category first.",
                    'error_type' => 'has_bookings',
                    'details' => [
                        'providers_count' => $category->providers_count,
                        'total_bookings' => $totalBookingsCount
                    ]
                ], 422);
            }

            // Category has providers but no bookings
            return response()->json([
                'success' => false,
                'message' => "Cannot delete provider category with {$category->providers_count} active provider(s). Please reassign or delete these providers first.",
                'error_type' => 'has_providers',
                'details' => [
                    'providers_count' => $category->providers_count
                ]
            ], 422);
        }

        // Safe to delete - no providers in this category
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Provider category deleted successfully',
        ]);
    }

    /**
     * Toggle provider category status
     */
    public function toggleStatus($id)
    {
        $category = ProviderCategory::withCount('providers')->findOrFail($id);

        // If trying to deactivate a category
        if ($category->is_active) {
            // Check for active providers in this category
            $activeProvidersCount = \App\Models\ServiceProvider::where('provider_category_id', $id)
                ->where('is_active', true)
                ->count();

            if ($activeProvidersCount > 0) {
                // Check for active bookings from these providers
                $activeBookingsCount = \App\Models\Booking::whereHas('provider', function($q) use ($id) {
                    $q->where('provider_category_id', $id)
                      ->where('is_active', true);
                })
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->count();

                if ($activeBookingsCount > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => "Cannot deactivate this provider category. It has {$activeProvidersCount} active provider(s) with {$activeBookingsCount} ongoing booking(s). Please complete or cancel these bookings first.",
                    ], 422);
                }

                // Has providers but no active bookings - warn user
                return response()->json([
                    'success' => false,
                    'message' => "This provider category has {$activeProvidersCount} active provider(s). Deactivating the category will hide it from new provider registrations, but existing providers will remain active. Are you sure?",
                    'warning' => true,
                    'providers_count' => $activeProvidersCount,
                ], 422);
            }
        }

        $category->update(['is_active' => !$category->is_active]);

        $status = $category->is_active ? 'activated' : 'deactivated';
        $message = "Provider category {$status} successfully";

        // Add info about providers if deactivating
        if (!$category->is_active && $category->providers_count > 0) {
            $message .= ". Note: {$category->providers_count} provider(s) in this category remain active.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
