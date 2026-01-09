<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\PromoCodeUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PromoCodeController extends Controller
{
    /**
     * Display promo codes list
     */
    public function index(Request $request)
    {
        $status = $request->input('status');
        $search = $request->input('search');

        $query = PromoCode::with('createdBy')->orderBy('created_at', 'desc');

        // Search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Status filter
        if ($status) {
            $today = Carbon::today();
            switch ($status) {
                case 'active':
                    $query->where('is_active', true)
                          ->where('valid_from', '<=', $today)
                          ->where('valid_until', '>=', $today)
                          ->where(function($q) {
                              $q->whereNull('usage_limit')
                                ->orWhereRaw('used_count < usage_limit');
                          });
                    break;
                case 'scheduled':
                    $query->where('is_active', true)
                          ->where('valid_from', '>', $today);
                    break;
                case 'expired':
                    $query->where('valid_until', '<', $today);
                    break;
                case 'disabled':
                    $query->where('is_active', false);
                    break;
            }
        }

        $promoCodes = $query->paginate(20);

        // Transform for view
        $promoCodes->getCollection()->transform(function($promo) {
            $today = Carbon::today();
            
            // Determine status
            if (!$promo->is_active) {
                $statusLabel = 'Disabled';
                $statusClass = 'secondary';
            } elseif ($today->lt($promo->valid_from)) {
                $statusLabel = 'Scheduled';
                $statusClass = 'info';
            } elseif ($today->gt($promo->valid_until)) {
                $statusLabel = 'Expired';
                $statusClass = 'danger';
            } elseif ($promo->usage_limit && $promo->used_count >= $promo->usage_limit) {
                $statusLabel = 'Limit Reached';
                $statusClass = 'warning';
            } else {
                $statusLabel = 'Active';
                $statusClass = 'success';
            }

            return [
                'id' => $promo->id,
                'code' => $promo->code,
                'description' => $promo->description,
                'discount_type' => $promo->discount_type,
                'discount_value' => $promo->discount_value,
                'max_discount_amount' => $promo->max_discount_amount,
                'min_booking_amount' => $promo->min_booking_amount,
                'usage_limit' => $promo->usage_limit,
                'usage_limit_per_user' => $promo->usage_limit_per_user,
                'used_count' => $promo->used_count,
                'valid_from' => $promo->valid_from,
                'valid_until' => $promo->valid_until,
                'is_active' => $promo->is_active,
                'status_label' => $statusLabel,
                'status_class' => $statusClass,
                'applicable_services' => $promo->applicable_services ?? [],
                'applicable_categories' => $promo->applicable_categories ?? [],
                'created_by_name' => $promo->createdBy ? $promo->createdBy->name : 'System',
                'created_at' => $promo->created_at,
            ];
        });

        // Statistics
        $today = Carbon::today();
        $stats = [
            'total' => PromoCode::count(),
            'active' => PromoCode::where('is_active', true)
                ->where('valid_from', '<=', $today)
                ->where('valid_until', '>=', $today)
                ->count(),
            'scheduled' => PromoCode::where('is_active', true)
                ->where('valid_from', '>', $today)
                ->count(),
            'expired' => PromoCode::where('valid_until', '<', $today)->count(),
            'total_discount_given' => PromoCodeUsage::sum('discount_amount') ?? 0,
        ];

        $pagination = [
            'current_page' => $promoCodes->currentPage(),
            'last_page' => $promoCodes->lastPage(),
            'per_page' => $promoCodes->perPage(),
            'total' => $promoCodes->total(),
            'from' => $promoCodes->firstItem(),
            'to' => $promoCodes->lastItem(),
        ];

        $filters = compact('status', 'search');

        return view('promos.list', [
            'promoCodes' => $promoCodes->items(),
            'stats' => $stats,
            'pagination' => $pagination,
            'filters' => $filters
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        $services = Service::where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ar']);
        
        // ServiceCategory removed - categories no longer available for promo codes
        $categories = [];

        return view('promos.create', compact('services', 'categories'));
    }

    /**
     * Store new promo code
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code',
            'description' => 'nullable|string|max:500',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_booking_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
            'applicable_to' => 'nullable|in:all,specific_services,specific_categories',
            'applicable_services' => 'nullable|array',
            'applicable_services.*' => 'exists:services,id',
            'applicable_categories' => 'nullable|array',
            'applicable_categories.*' => 'exists:service_categories,id',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->has('is_active');
        $validated['created_by'] = auth()->id();
        $validated['used_count'] = 0;
        
        // Set applicable_to if not provided
        if (!isset($validated['applicable_to'])) {
            $validated['applicable_to'] = 'all';
        }
        
        // Clean up applicable fields based on applicable_to
        if ($validated['applicable_to'] === 'all') {
            $validated['applicable_services'] = null;
            $validated['applicable_categories'] = null;
        } elseif ($validated['applicable_to'] === 'specific_services') {
            $validated['applicable_categories'] = null;
        } elseif ($validated['applicable_to'] === 'specific_categories') {
            $validated['applicable_services'] = null;
        }

        PromoCode::create($validated);

        return redirect()->route('promos.index')
            ->with('success', 'Promo code created successfully');
    }

    /**
     * Display promo code details
     */
    public function show($id)
    {
        $promoCode = PromoCode::with(['createdBy', 'usages.user', 'usages.booking'])
            ->findOrFail($id);

        $usageStats = [
            'total_uses' => $promoCode->used_count,
            'total_discount' => $promoCode->usages()->sum('discount_amount'),
            'unique_users' => $promoCode->usages()->distinct('user_id')->count(),
            'recent_uses' => $promoCode->usages()
                ->with(['user', 'booking'])
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get(),
        ];

        return view('promos.show', compact('promoCode', 'usageStats'));
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $promoCode = PromoCode::findOrFail($id);
        
        $services = Service::where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ar']);
        
        // ServiceCategory removed - categories no longer available for promo codes
        $categories = [];

        return view('promos.edit', compact('promoCode', 'services', 'categories'));
    }

    /**
     * Update promo code
     */
    public function update(Request $request, $id)
    {
        $promoCode = PromoCode::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code,' . $id,
            'description' => 'nullable|string|max:500',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_booking_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'applicable_to' => 'nullable|in:all,specific_services,specific_categories',
            'applicable_services' => 'nullable|array',
            'applicable_services.*' => 'exists:services,id',
            'applicable_categories' => 'nullable|array',
            'applicable_categories.*' => 'exists:service_categories,id',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->has('is_active');
        
        // Clean up applicable fields based on applicable_to
        if ($validated['applicable_to'] === 'all') {
            $validated['applicable_services'] = null;
            $validated['applicable_categories'] = null;
        } elseif ($validated['applicable_to'] === 'specific_services') {
            $validated['applicable_categories'] = null;
        } elseif ($validated['applicable_to'] === 'specific_categories') {
            $validated['applicable_services'] = null;
        }

        $promoCode->update($validated);

        return redirect()->route('promos.index')
            ->with('success', 'Promo code updated successfully');
    }

    /**
     * Toggle promo code status
     */
    public function toggleStatus($id)
    {
        $promoCode = PromoCode::findOrFail($id);
        $promoCode->update(['is_active' => !$promoCode->is_active]);

        $status = $promoCode->is_active ? 'activated' : 'deactivated';
        
        return response()->json([
            'success' => true,
            'message' => "Promo code {$status} successfully",
        ]);
    }

    /**
     * Delete promo code
     */
    public function destroy($id)
    {
        $promoCode = PromoCode::findOrFail($id);

        // Check if promo code has been used
        if ($promoCode->used_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete promo code that has been used. Total uses: {$promoCode->used_count}",
            ], 422);
        }

        $promoCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Promo code deleted successfully',
        ]);
    }
}
