<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Booking;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * Submit a review for a completed booking (Client only)
     */
    public function submitReview(Request $request, int $bookingId): JsonResponse
    {
        $user = $request->user();

        // Validate input
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        // Get the booking
        $booking = Booking::where('id', $bookingId)
            ->where('client_id', $user->id)
            ->firstOrFail();

        // Validate booking is completed
        if ($booking->status !== 'completed') {
            throw ValidationException::withMessages([
                'booking' => ['You can only review completed bookings.']
            ]);
        }

        // Check if booking was completed at least 5 minutes ago (as per contract requirement)
        if ($booking->completed_at && $booking->completed_at->gt(now()->subMinutes(5))) {
            throw ValidationException::withMessages([
                'booking' => ['You can review this booking 5 minutes after completion.']
            ]);
        }

        // Check if review already exists
        if ($booking->review()->exists()) {
            throw ValidationException::withMessages([
                'booking' => ['You have already reviewed this booking.']
            ]);
        }

        DB::beginTransaction();
        try {
            // Create review - rating is auto-approved, comment needs admin approval
            $review = Review::create([
                'booking_id' => $booking->id,
                'client_id' => $user->id,
                'provider_id' => $booking->provider_id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'is_visible' => true,
                'approval_status' => 'approved', // Rating is immediately visible
                'comment_approved' => false, // Comment needs admin approval
            ]);

            // Update provider average rating and total reviews immediately
            $this->updateProviderRating($booking->provider_id);

            DB::commit();

            $message = 'Review submitted successfully. Your rating is now visible.';
            if (!empty($validated['comment'])) {
                $message .= ' Your comment will be visible after admin approval.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'approval_status' => $review->approval_status,
                    'comment_approved' => $review->comment_approved,
                    'created_at' => $review->created_at->format('Y-m-d H:i:s'),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get reviews for a specific provider (Public)
     * Shows all ratings immediately, but only approved comments
     */
    public function getProviderReviews(Request $request, int $providerId): JsonResponse
    {
        $provider = ServiceProvider::findOrFail($providerId);

        $query = Review::with(['client:id,name', 'booking:id,booking_number,booking_date'])
            ->where('provider_id', $providerId)
            ->where('is_visible', true)
            ->where('approval_status', 'approved'); // All ratings are auto-approved

        // Filter by rating if provided
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        // Sort by newest first
        $reviews = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'provider' => [
                    'id' => $provider->id,
                    'business_name' => $provider->business_name,
                    'average_rating' => (float) $provider->average_rating,
                    'total_reviews' => $provider->total_reviews,
                ],
                'reviews' => collect($reviews->items())->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        // Only show comment if it's approved by admin
                        'comment' => $review->comment_approved ? $review->comment : null,
                        'client_name' => $review->client?->name ?? 'Anonymous',
                        'booking_number' => $review->booking?->booking_number ?? 'N/A',
                        'booking_date' => $review->booking?->booking_date?->format('Y-m-d') ?? null,
                        'created_at' => $review->created_at->format('Y-m-d H:i:s'),
                    ];
                })->values(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'total' => $reviews->total(),
                    'per_page' => $reviews->perPage(),
                ]
            ]
        ]);
    }

    /**
     * Get client's own submitted reviews
     */
    public function getMyReviews(Request $request): JsonResponse
    {
        $user = $request->user();

        $reviews = Review::with(['provider:id,business_name', 'booking:id,booking_number,booking_date'])
            ->where('client_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $reviews->items()->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'provider_name' => $review->provider->business_name,
                    'booking_number' => $review->booking->booking_number,
                    'booking_date' => $review->booking->booking_date->format('Y-m-d'),
                    'approval_status' => $review->approval_status,
                    'rejection_reason' => $review->rejection_reason,
                    'created_at' => $review->created_at->format('Y-m-d H:i:s'),
                ];
            }),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ]
        ]);
    }

    /**
     * Get reviews received by provider (Provider only)
     */
    public function getReceivedReviews(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider profile not found'
            ], 404);
        }

        $query = Review::with(['client:id,name', 'booking:id,booking_number,booking_date'])
            ->where('provider_id', $provider->id);

        // Filter by rating if provided
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        // Filter by visibility
        if ($request->has('is_visible')) {
            $query->where('is_visible', $request->boolean('is_visible'));
        }

        $reviews = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'average_rating' => (float) $provider->average_rating,
                    'total_reviews' => $provider->total_reviews,
                    'rating_breakdown' => $this->getRatingBreakdown($provider->id),
                ],
                'reviews' => collect($reviews->items())->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'comment_approved' => $review->comment_approved,
                        'comment_approved_at' => $review->comment_approved_at?->format('Y-m-d H:i:s'),
                        'client_name' => $review->client?->name ?? 'Unknown Client',
                        'booking_number' => $review->booking?->booking_number ?? 'N/A',
                        'booking_date' => $review->booking?->booking_date?->format('Y-m-d') ?? null,
                        'is_visible' => $review->is_visible,
                        'approval_status' => $review->approval_status,
                        'admin_response' => $review->admin_response,
                        'responded_at' => $review->responded_at?->format('Y-m-d H:i:s'),
                        'is_flagged' => $review->is_flagged,
                        'flag_reason' => $review->flag_reason,
                        'created_at' => $review->created_at->format('Y-m-d H:i:s'),
                    ];
                })->values(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'total' => $reviews->total(),
                ]
            ]
        ]);
    }

    /**
     * Update provider's average rating and total reviews count
     */
    protected function updateProviderRating(int $providerId): void
    {
        $stats = Review::where('provider_id', $providerId)
            ->where('is_visible', true)
            ->where('approval_status', 'approved')
            ->selectRaw('AVG(rating) as average, COUNT(*) as total')
            ->first();

        ServiceProvider::where('id', $providerId)->update([
            'average_rating' => $stats->average ? round($stats->average, 2) : 0,
            'total_reviews' => $stats->total ?? 0,
        ]);
    }

    /**
     * Provider responds to a review
     */
    public function respondToReview(Request $request, int $reviewId): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider profile not found'
            ], 404);
        }

        // Validate input
        $validated = $request->validate([
            'response' => 'required|string|max:500',
        ]);

        // Get the review
        $review = Review::where('id', $reviewId)
            ->where('provider_id', $provider->id)
            ->firstOrFail();

        // Update review response
        $review->update([
            'admin_response' => $validated['response'],
            'responded_by' => $user->id,
            'responded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Response added successfully',
            'data' => [
                'id' => $review->id,
                'response' => $review->admin_response,
                'responded_at' => $review->responded_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * Provider flags a review as inappropriate
     */
    public function flagReview(Request $request, int $reviewId): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider profile not found'
            ], 404);
        }

        // Validate input
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // Get the review
        $review = Review::where('id', $reviewId)
            ->where('provider_id', $provider->id)
            ->firstOrFail();

        // Flag the review
        $review->update([
            'is_flagged' => true,
            'flag_reason' => $validated['reason'],
            'flagged_by' => $user->id,
            'flagged_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review flagged for admin review',
            'data' => [
                'id' => $review->id,
                'is_flagged' => $review->is_flagged,
                'flagged_at' => $review->flagged_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * Get rating breakdown (how many 1-star, 2-star, etc.)
     */
    protected function getRatingBreakdown(int $providerId): array
    {
        $breakdown = Review::where('provider_id', $providerId)
            ->where('is_visible', true)
            ->where('approval_status', 'approved')
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        // Ensure all ratings 1-5 are present
        $result = [];
        for ($i = 1; $i <= 5; $i++) {
            $result[$i] = $breakdown[$i] ?? 0;
        }

        return $result;
    }
}
