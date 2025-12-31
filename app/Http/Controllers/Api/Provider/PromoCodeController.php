<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PromoCodeController extends Controller
{
    /**
     * Get all promo codes for authenticated provider
     * GET /provider/promo-codes
     */
    public function index(Request $request)
    {
        $provider = Auth::user();
        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);
        $isActive = $request->input('is_active');

        $query = PromoCode::where('provider_id', $provider->id);

        // Filter by active status if provided
        if ($isActive !== null) {
            $query->where('is_active', $isActive == '1');
        }

        $promoCodes = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Transform data to match mobile app expectations
        $transformedData = $promoCodes->getCollection()->map(function ($code) {
            return $this->transformPromoCode($code);
        });

        return response()->json([
            'success' => true,
            'data' => $transformedData,
            'pagination' => [
                'current_page' => $promoCodes->currentPage(),
                'last_page' => $promoCodes->lastPage(),
                'per_page' => $promoCodes->perPage(),
                'total' => $promoCodes->total(),
            ],
        ]);
    }

    /**
     * Get single promo code by ID
     * GET /provider/promo-codes/{id}
     */
    public function show($id)
    {
        $provider = Auth::user();

        $promoCode = PromoCode::where('id', $id)
            ->where('provider_id', $provider->id)
            ->first();

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformPromoCode($promoCode),
        ]);
    }

    /**
     * Create new promo code
     * POST /provider/promo-codes
     */
    public function store(Request $request)
    {
        $provider = Auth::user();

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:20|min:3|unique:promo_codes,code',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed_amount,free_service',
            'discount_value' => 'required_if:discount_type,percentage,fixed_amount|nullable|numeric|min:0',
            'free_service_id' => 'required_if:discount_type,free_service|nullable|exists:services,id',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
            'max_total_uses' => 'nullable|integer|min:1',
            'max_uses_per_client' => 'nullable|integer|min:1',
            'min_order_value' => 'nullable|numeric|min:0',
            'applicable_service_ids' => 'nullable|array',
            'applicable_service_ids.*' => 'exists:services,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate percentage value
        if ($request->discount_type === 'percentage' && $request->discount_value > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage discount cannot exceed 100',
            ], 422);
        }

        // Map discount_type from mobile app format to database format
        $discountType = $request->discount_type === 'fixed_amount' ? 'fixed' : $request->discount_type;

        $promoCode = PromoCode::create([
            'provider_id' => $provider->id,
            'created_by' => $provider->id,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'discount_type' => $discountType,
            'discount_value' => $request->discount_value,
            'free_service_id' => $request->free_service_id,
            'min_booking_amount' => $request->min_order_value,
            'usage_limit' => $request->max_total_uses,
            'usage_limit_per_user' => $request->max_uses_per_client ?? 1,
            'used_count' => 0,
            'valid_from' => Carbon::parse($request->valid_from),
            'valid_until' => Carbon::parse($request->valid_until),
            'is_active' => true,
            'applicable_to' => !empty($request->applicable_service_ids) ? 'specific_services' : 'all',
            'applicable_services' => $request->applicable_service_ids,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->transformPromoCode($promoCode),
            'message' => 'Promo code created successfully',
        ], 201);
    }

    /**
     * Update existing promo code
     * PUT /provider/promo-codes/{id}
     */
    public function update(Request $request, $id)
    {
        $provider = Auth::user();

        $promoCode = PromoCode::where('id', $id)
            ->where('provider_id', $provider->id)
            ->first();

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|max:20|min:3|unique:promo_codes,code,' . $id,
            'description' => 'nullable|string',
            'discount_type' => 'sometimes|in:percentage,fixed_amount,free_service',
            'discount_value' => 'nullable|numeric|min:0',
            'free_service_id' => 'nullable|exists:services,id',
            'valid_from' => 'sometimes|date',
            'valid_until' => 'sometimes|date',
            'max_total_uses' => 'nullable|integer|min:1',
            'max_uses_per_client' => 'nullable|integer|min:1',
            'min_order_value' => 'nullable|numeric|min:0',
            'applicable_service_ids' => 'nullable|array',
            'applicable_service_ids.*' => 'exists:services,id',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate percentage value
        if ($request->has('discount_type') && $request->discount_type === 'percentage'
            && $request->has('discount_value') && $request->discount_value > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage discount cannot exceed 100',
            ], 422);
        }

        $updateData = [];

        if ($request->has('code')) {
            $updateData['code'] = strtoupper($request->code);
        }
        if ($request->has('description')) {
            $updateData['description'] = $request->description;
        }
        if ($request->has('discount_type')) {
            // Map discount_type from mobile app format to database format
            $updateData['discount_type'] = $request->discount_type === 'fixed_amount' ? 'fixed' : $request->discount_type;
        }
        if ($request->has('discount_value')) {
            $updateData['discount_value'] = $request->discount_value;
        }
        if ($request->has('free_service_id')) {
            $updateData['free_service_id'] = $request->free_service_id;
        }
        if ($request->has('valid_from')) {
            $updateData['valid_from'] = Carbon::parse($request->valid_from);
        }
        if ($request->has('valid_until')) {
            $updateData['valid_until'] = Carbon::parse($request->valid_until);
        }
        if ($request->has('max_total_uses')) {
            $updateData['usage_limit'] = $request->max_total_uses;
        }
        if ($request->has('max_uses_per_client')) {
            $updateData['usage_limit_per_user'] = $request->max_uses_per_client;
        }
        if ($request->has('min_order_value')) {
            $updateData['min_booking_amount'] = $request->min_order_value;
        }
        if ($request->has('applicable_service_ids')) {
            $updateData['applicable_services'] = $request->applicable_service_ids;
            $updateData['applicable_to'] = !empty($request->applicable_service_ids) ? 'specific_services' : 'all';
        }
        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->is_active;
        }

        $promoCode->update($updateData);

        return response()->json([
            'success' => true,
            'data' => $this->transformPromoCode($promoCode->fresh()),
            'message' => 'Promo code updated successfully',
        ]);
    }

    /**
     * Delete promo code
     * DELETE /provider/promo-codes/{id}
     */
    public function destroy($id)
    {
        $provider = Auth::user();

        $promoCode = PromoCode::where('id', $id)
            ->where('provider_id', $provider->id)
            ->first();

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code not found',
            ], 404);
        }

        // Check if promo code has been used
        $usageCount = PromoCodeUsage::where('promo_code_id', $id)->count();

        if ($usageCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete promo code that has been used. Consider deactivating it instead.',
            ], 422);
        }

        $promoCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Promo code deleted successfully',
        ]);
    }

    /**
     * Toggle promo code active status
     * PATCH /provider/promo-codes/{id}/toggle
     */
    public function toggle(Request $request, $id)
    {
        $provider = Auth::user();

        $promoCode = PromoCode::where('id', $id)
            ->where('provider_id', $provider->id)
            ->first();

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code not found',
            ], 404);
        }

        // Accept is_active value from request, or toggle if not provided
        $newStatus = $request->has('is_active')
            ? (bool) $request->is_active
            : !$promoCode->is_active;

        $promoCode->update(['is_active' => $newStatus]);

        return response()->json([
            'success' => true,
            'data' => $this->transformPromoCode($promoCode->fresh()),
            'message' => 'Promo code status updated successfully',
        ]);
    }

    /**
     * Validate promo code for client usage
     * POST /provider/promo-codes/validate
     */
    public function validate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'cart_total' => 'required|numeric|min:0',
            'service_ids' => 'required|array',
            'service_ids.*' => 'exists:services,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $code = strtoupper($request->code);
        $cartTotal = (float) $request->cart_total;
        $serviceIds = $request->service_ids;
        $userId = Auth::id();

        // Find promo code
        $promoCode = PromoCode::where('code', $code)->first();

        if (!$promoCode) {
            return response()->json([
                'success' => true,
                'data' => [
                    'is_valid' => false,
                    'error_message' => 'Invalid promo code',
                ],
            ]);
        }

        // Check if promo code is valid
        if (!$promoCode->isValid()) {
            $message = $this->getValidationErrorMessage($promoCode);
            return response()->json([
                'success' => true,
                'data' => [
                    'is_valid' => false,
                    'error_message' => $message,
                ],
            ]);
        }

        // Check per-user usage limit
        if ($promoCode->usage_limit_per_user) {
            $userUsageCount = PromoCodeUsage::where('promo_code_id', $promoCode->id)
                ->where('user_id', $userId)
                ->count();

            if ($userUsageCount >= $promoCode->usage_limit_per_user) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'is_valid' => false,
                        'error_message' => 'You have reached the maximum usage limit for this promo code',
                        'client_usage_count' => $userUsageCount,
                    ],
                ]);
            }
        }

        // Check minimum booking amount
        if ($promoCode->min_booking_amount && $cartTotal < $promoCode->min_booking_amount) {
            return response()->json([
                'success' => true,
                'data' => [
                    'is_valid' => false,
                    'error_message' => sprintf(
                        'Minimum order value is %s SAR to use this promo code',
                        $promoCode->min_booking_amount
                    ),
                ],
            ]);
        }

        // Check if promo code is applicable to the services
        if (!empty($promoCode->applicable_services)) {
            $hasApplicableService = false;
            foreach ($serviceIds as $serviceId) {
                if (in_array($serviceId, $promoCode->applicable_services)) {
                    $hasApplicableService = true;
                    break;
                }
            }

            if (!$hasApplicableService) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'is_valid' => false,
                        'error_message' => 'This promo code is not applicable to the selected services',
                    ],
                ]);
            }
        }

        // Calculate discount
        $discountAmount = $promoCode->calculateDiscount($cartTotal);

        return response()->json([
            'success' => true,
            'data' => [
                'is_valid' => true,
                'promo_code' => $this->transformPromoCode($promoCode),
                'discount_amount' => $discountAmount,
                'client_usage_count' => PromoCodeUsage::where('promo_code_id', $promoCode->id)
                    ->where('user_id', $userId)
                    ->count(),
            ],
        ]);
    }

    /**
     * Transform promo code to match mobile app expectations
     */
    private function transformPromoCode(PromoCode $code)
    {
        // Map discount_type from database format to mobile app format
        $discountType = $code->discount_type === 'fixed' ? 'fixed_amount' : $code->discount_type;

        return [
            'id' => $code->id,
            'provider_id' => $code->provider_id,
            'code' => $code->code,
            'description' => $code->description,
            'discount_type' => $discountType,
            'discount_value' => $code->discount_value,
            'free_service_id' => $code->free_service_id,
            'valid_from' => $code->valid_from ? $code->valid_from->toISOString() : null,
            'valid_until' => $code->valid_until ? $code->valid_until->toISOString() : null,
            'max_total_uses' => $code->usage_limit,
            'max_uses_per_client' => $code->usage_limit_per_user,
            'min_order_value' => $code->min_booking_amount,
            'applicable_service_ids' => $code->applicable_services,
            'current_total_uses' => $code->used_count,
            'is_active' => $code->is_active,
            'created_at' => $code->created_at ? $code->created_at->toISOString() : null,
            'updated_at' => $code->updated_at ? $code->updated_at->toISOString() : null,
        ];
    }

    /**
     * Get validation error message
     */
    private function getValidationErrorMessage(PromoCode $promoCode)
    {
        if (!$promoCode->is_active) {
            return 'This promo code is inactive';
        }

        $today = Carbon::today();
        if ($today->lt($promoCode->valid_from)) {
            return 'This promo code is not yet valid';
        }
        if ($today->gt($promoCode->valid_until)) {
            return 'This promo code has expired';
        }

        if ($promoCode->usage_limit && $promoCode->used_count >= $promoCode->usage_limit) {
            return 'This promo code has reached its usage limit';
        }

        return 'Invalid promo code';
    }
}
