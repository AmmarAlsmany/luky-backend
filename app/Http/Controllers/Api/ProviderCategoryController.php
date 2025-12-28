<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProviderCategory;
use Illuminate\Http\JsonResponse;

class ProviderCategoryController extends Controller
{
    /**
     * Get all active provider categories
     */
    public function index(): JsonResponse
    {
        $categories = ProviderCategory::active()->get();

        return response()->json([
            'success' => true,
            'data' => $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name_ar' => $category->name_ar,
                    'name_en' => $category->name_en,
                    'description_ar' => $category->description_ar,
                    'description_en' => $category->description_en,
                    'icon' => $category->icon,
                    'icon_url' => $category->icon_url,
                    'color' => $category->color,
                    'sort_order' => $category->sort_order,
                ];
            }),
        ]);
    }
}
