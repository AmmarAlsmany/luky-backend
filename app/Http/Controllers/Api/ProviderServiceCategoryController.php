<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProviderServiceCategory;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProviderServiceCategoryController extends Controller
{
    /**
     * Get provider's own service categories
     * GET /api/provider/categories
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $provider = $user->serviceProvider;

            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => __('categories.provider_not_found', [], 'en'),
                    'message_ar' => __('categories.provider_not_found', [], 'ar'),
                ], 404);
            }

            $categories = ProviderServiceCategory::forProvider($provider->id)
                ->active()
                ->orderBy('sort_order')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('categories.failed_to_load_categories', [], 'en') . ': ' . $e->getMessage(),
                'message_ar' => __('categories.failed_to_load_categories', [], 'ar') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new provider service category
     * POST /api/provider/categories
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $provider = $user->serviceProvider;

            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => __('categories.provider_not_found', [], 'en'),
                    'message_ar' => __('categories.provider_not_found', [], 'ar'),
                ], 404);
            }

            $validated = $request->validate([
                'name_en' => 'required|string|max:50|min:3',
                'name_ar' => 'required|string|max:50|min:3',
                'description_en' => 'nullable|string|max:200',
                'description_ar' => 'nullable|string|max:200',
                'color' => 'nullable|string|max:7', // Hex color like #FF5733
            ]);

            // Check for duplicate names for this provider
            $existingCategory = ProviderServiceCategory::forProvider($provider->id)
                ->where(function ($query) use ($validated) {
                    $query->where('name_en', $validated['name_en'])
                        ->orWhere('name_ar', $validated['name_ar']);
                })
                ->first();

            if ($existingCategory) {
                return response()->json([
                    'success' => false,
                    'message' => __('categories.duplicate_category_name', [], 'en'),
                    'message_ar' => __('categories.duplicate_category_name', [], 'ar'),
                ], 409); // 409 Conflict
            }

            // Get next sort order
            $sortOrder = ProviderServiceCategory::getNextSortOrder($provider->id);

            $category = ProviderServiceCategory::create([
                'provider_id' => $provider->id,
                'name_en' => $validated['name_en'],
                'name_ar' => $validated['name_ar'],
                'description_en' => $validated['description_en'] ?? null,
                'description_ar' => $validated['description_ar'] ?? null,
                'color' => $validated['color'] ?? null,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);

            // Reload to get service_count attribute
            $category = $category->fresh();

            return response()->json([
                'success' => true,
                'message' => __('categories.category_created_success', [], 'en'),
                'message_ar' => __('categories.category_created_success', [], 'ar'),
                'data' => $category,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => __('categories.validation_failed', [], 'en'),
                'message_ar' => __('categories.validation_failed', [], 'ar'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('categories.failed_to_create_category', [], 'en') . ': ' . $e->getMessage(),
                'message_ar' => __('categories.failed_to_create_category', [], 'ar') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing provider service category
     * PUT /api/provider/categories/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $provider = $user->serviceProvider;

            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => __('categories.provider_not_found', [], 'en'),
                    'message_ar' => __('categories.provider_not_found', [], 'ar'),
                ], 404);
            }

            $category = ProviderServiceCategory::forProvider($provider->id)
                ->findOrFail($id);

            $validated = $request->validate([
                'name_en' => 'sometimes|string|max:50|min:3',
                'name_ar' => 'sometimes|string|max:50|min:3',
                'description_en' => 'nullable|string|max:200',
                'description_ar' => 'nullable|string|max:200',
                'color' => 'nullable|string|max:7',
                'is_active' => 'sometimes|boolean',
            ]);

            // Check for duplicate names (excluding current category)
            if (isset($validated['name_en']) || isset($validated['name_ar'])) {
                $duplicateExists = ProviderServiceCategory::forProvider($provider->id)
                    ->where('id', '!=', $id)
                    ->where(function ($query) use ($validated) {
                        if (isset($validated['name_en'])) {
                            $query->where('name_en', $validated['name_en']);
                        }
                        if (isset($validated['name_ar'])) {
                            $query->orWhere('name_ar', $validated['name_ar']);
                        }
                    })
                    ->exists();

                if ($duplicateExists) {
                    return response()->json([
                        'success' => false,
                        'message' => __('categories.duplicate_category_name', [], 'en'),
                        'message_ar' => __('categories.duplicate_category_name', [], 'ar'),
                    ], 409);
                }
            }

            $category->update($validated);

            // Reload to get fresh service_count
            $category = $category->fresh();

            return response()->json([
                'success' => true,
                'message' => __('categories.category_updated_success', [], 'en'),
                'message_ar' => __('categories.category_updated_success', [], 'ar'),
                'data' => $category,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => __('categories.validation_failed', [], 'en'),
                'message_ar' => __('categories.validation_failed', [], 'ar'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('categories.failed_to_update_category', [], 'en') . ': ' . $e->getMessage(),
                'message_ar' => __('categories.failed_to_update_category', [], 'ar') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a provider service category
     * DELETE /api/provider/categories/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $provider = $user->serviceProvider;

            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => __('categories.provider_not_found', [], 'en'),
                    'message_ar' => __('categories.provider_not_found', [], 'ar'),
                ], 404);
            }

            $category = ProviderServiceCategory::forProvider($provider->id)
                ->findOrFail($id);

            // Check if category has services
            if (!$category->canDelete()) {
                $serviceCount = $category->services()->count();
                return response()->json([
                    'success' => false,
                    'message' => __('categories.cannot_delete_category_with_services', ['count' => $serviceCount], 'en'),
                    'message_ar' => __('categories.cannot_delete_category_with_services', ['count' => $serviceCount], 'ar'),
                ], 422); // 422 Unprocessable Entity
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => __('categories.category_deleted_success', [], 'en'),
                'message_ar' => __('categories.category_deleted_success', [], 'ar'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('categories.failed_to_delete_category', [], 'en') . ': ' . $e->getMessage(),
                'message_ar' => __('categories.failed_to_delete_category', [], 'ar') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Migrate existing services to provider-owned categories
     * POST /api/provider/categories/migrate
     */
    public function migrate(Request $request): JsonResponse
    {
        // DEPRECATED: ServiceCategory system has been removed
        // Migration is no longer needed or possible as category_id column doesn't exist
        return response()->json([
            'success' => true,
            'message' => 'Migration is no longer needed. The old category system has been removed.',
            'message_ar' => 'الترحيل لم يعد مطلوبًا. تم إزالة نظام الفئات القديم.',
            'data' => [
                'is_completed' => true,
                'total_services' => 0,
                'migrated_services' => 0,
                'categories_created' => 0,
            ],
        ]);
    }

    /**
     * Check if provider has migrated to custom categories
     * GET /api/provider/categories/migration-status
     */
    public function checkMigrationStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $provider = $user->serviceProvider;

            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => __('categories.provider_not_found', [], 'en'),
                    'message_ar' => __('categories.provider_not_found', [], 'ar'),
                ], 404);
            }

            // Check if provider has any provider_service_categories
            $hasProviderCategories = ProviderServiceCategory::forProvider($provider->id)->exists();

            // Check if any services have provider_service_category_id set
            $hasServicesMigrated = Service::where('provider_id', $provider->id)
                ->whereNotNull('provider_service_category_id')
                ->exists();

            $isMigrated = $hasProviderCategories || $hasServicesMigrated;

            return response()->json([
                'success' => true,
                'data' => [
                    'is_migrated' => $isMigrated,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('categories.failed_to_check_migration', [], 'en') . ': ' . $e->getMessage(),
                'message_ar' => __('categories.failed_to_check_migration', [], 'ar') . ': ' . $e->getMessage(),
            ], 500);
        }
    }
}
