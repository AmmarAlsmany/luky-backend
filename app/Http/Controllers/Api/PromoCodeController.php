<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PromoCodeController extends Controller
{
    /**
     * Validate and apply promo code
     */
    public function validatePromoCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'service_ids' => 'required|array',
            'service_ids.*' => 'exists:services,id',
            'order_amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $code = strtoupper($request->code);
        $serviceIds = $request->service_ids;
        $orderAmount = (float) $request->order_amount;
        $userId = Auth::id();

        // Find promo code
        $promoCode = PromoCode::where('code', $code)->first();

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid promo code',
            ], 404);
        }

        // Check if promo code is provider-specific
        if ($promoCode->provider_id) {
            // Validate that all services belong to the same provider as the promo code
            $services = Service::whereIn('id', $serviceIds)->get();

            foreach ($services as $service) {
                if ($service->provider_id !== $promoCode->provider_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This promo code is not valid for the selected provider',
                    ], 400);
                }
            }
        }

        // Check if promo code is valid
        if (!$promoCode->isValid()) {
            $message = 'This promo code is ';
            if (!$promoCode->is_active) {
                $message .= 'inactive';
            } elseif ($promoCode->valid_from && now()->lt($promoCode->valid_from)) {
                $message .= 'not yet active';
            } elseif ($promoCode->valid_until && now()->gt($promoCode->valid_until)) {
                $message .= 'expired';
            } elseif ($promoCode->usage_limit && $promoCode->used_count >= $promoCode->usage_limit) {
                $message .= 'no longer available (maximum uses reached)';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 400);
        }

        // Check if user can use this promo code
        if (!$promoCode->canBeUsedByUser($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached the maximum usage limit for this promo code',
            ], 400);
        }

        // Check minimum booking amount
        if ($promoCode->min_booking_amount && $orderAmount < $promoCode->min_booking_amount) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Minimum order amount is %s SAR to use this promo code',
                    $promoCode->min_booking_amount
                ),
            ], 400);
        }

        // Check if promo code is applicable to the services
        if (!empty($promoCode->applicable_services)) {
            $applicableServices = [];
            foreach ($serviceIds as $serviceId) {
                if ($promoCode->isApplicableToService($serviceId)) {
                    $applicableServices[] = $serviceId;
                }
            }

            if (empty($applicableServices)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This promo code is not applicable to the selected services',
                ], 400);
            }
        }

        // Calculate discount
        $discountAmount = $promoCode->calculateDiscount($orderAmount);
        $finalAmount = max(0, $orderAmount - $discountAmount);

        return response()->json([
            'success' => true,
            'message' => 'Promo code applied successfully',
            'data' => [
                'promo_code_id' => $promoCode->id,
                'code' => $promoCode->code,
                'description' => $promoCode->description,
                'discount_type' => $promoCode->discount_type,
                'discount_value' => $promoCode->discount_value,
                'discount_amount' => $discountAmount,
                'original_amount' => $orderAmount,
                'final_amount' => $finalAmount,
            ],
        ]);
    }
}
