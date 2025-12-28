<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Review;
use App\Models\ProviderPendingChange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProviderManagementController extends Controller
{
    /**
     * Get list of providers with pagination and filters
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $search = $request->input('search');
        $status = $request->input('status');
        $businessType = $request->input('business_type');
        $cityId = $request->input('city_id');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $query = User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })->with(['city', 'providerProfile']);

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhereHas('providerProfile', function ($q) use ($search) {
                      $q->where('business_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Status filter
        if ($status) {
            $query->where('status', $status);
        }

        // Business type filter
        if ($businessType) {
            $query->whereHas('providerProfile', function ($q) use ($businessType) {
                $q->where('business_type', $businessType);
            });
        }

        // City filter
        if ($cityId) {
            $query->where('city_id', $cityId);
        }

        // Sorting
        $query->orderBy($sortBy, $sortOrder);

        $providers = $query->paginate($perPage);

        // Transform provider data
        $providers->getCollection()->transform(function ($provider) {
            $providerProfile = $provider->providerProfile;

            return [
                'id' => $provider->id,
                'name' => $provider->name,
                'email' => $provider->email,
                'phone' => $provider->phone,
                'status' => $provider->status,
                'avatar_url' => $provider->avatar_url,
                'business_name' => $providerProfile->business_name ?? null,
                'business_type' => $providerProfile->business_type ?? null,
                'verification_status' => $providerProfile->verification_status ?? 'pending',
                'rating' => $providerProfile->rating ?? 0,
                'total_reviews' => $providerProfile->total_reviews ?? 0,
                'total_bookings' => Booking::where('provider_id', $provider->id)->count(),
                'total_revenue' => Booking::where('provider_id', $provider->id)
                    ->where('status', 'completed')
                    ->sum('total_amount'),
                'address' => $providerProfile->address ?? null,
                'city' => $provider->city ? [
                    'id' => $provider->city->id,
                    'name_en' => $provider->city->name_en,
                    'name_ar' => $provider->city->name_ar,
                ] : null,
                'created_at' => $provider->created_at,
                'updated_at' => $provider->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'providers' => $providers->items(),
                'pagination' => [
                    'current_page' => $providers->currentPage(),
                    'from' => $providers->firstItem(),
                    'to' => $providers->lastItem(),
                    'per_page' => $providers->perPage(),
                    'total' => $providers->total(),
                    'last_page' => $providers->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get provider statistics
     */
    public function stats()
    {
        $stats = [
            'total' => User::whereHas('roles', fn($q) => $q->where('name', 'provider'))->count(),
            'active' => User::whereHas('roles', fn($q) => $q->where('name', 'provider'))
                ->where('status', 'active')->count(),
            'inactive' => User::whereHas('roles', fn($q) => $q->where('name', 'provider'))
                ->where('status', 'inactive')->count(),
            'total_revenue' => Booking::where('status', 'completed')
                ->sum('total_amount') ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get providers pending approval
     */
    public function pendingApproval()
    {
        $providers = User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })->whereHas('providerProfile', function ($q) {
            $q->where('verification_status', 'pending');
        })->with(['providerProfile', 'city'])->get();

        return response()->json([
            'success' => true,
            'data' => ['providers' => $providers],
        ]);
    }

    /**
     * Get single provider details
     */
    public function show($id)
    {
        $provider = User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })->with(['city', 'providerProfile'])->find($id);

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider not found',
            ], 404);
        }

        $providerProfile = $provider->providerProfile;

        // Get provider statistics
        $totalBookings = Booking::where('provider_id', $id)->count();
        $completedBookings = Booking::where('provider_id', $id)->where('status', 'completed')->count();
        $cancelledBookings = Booking::where('provider_id', $id)->where('status', 'cancelled')->count();
        $pendingBookings = Booking::where('provider_id', $id)->whereIn('status', ['pending', 'confirmed'])->count();

        $totalRevenue = Booking::where('provider_id', $id)
            ->where('status', 'completed')
            ->sum('total_amount');

        $providerData = [
            'id' => $provider->id,
            'name' => $provider->name,
            'email' => $provider->email,
            'phone' => $provider->phone,
            'status' => $provider->status,
            'avatar_url' => $provider->avatar_url,
            'business_name' => $providerProfile->business_name ?? null,
            'business_type' => $providerProfile->business_type ?? null,
            'business_description' => $providerProfile->business_description ?? null,
            'business_license' => $providerProfile->business_license ?? null,
            'verification_status' => $providerProfile->verification_status ?? 'pending',
            'verification_notes' => $providerProfile->verification_notes ?? null,
            'rating' => $providerProfile->rating ?? 0,
            'total_reviews' => $providerProfile->total_reviews ?? 0,
            'logo_url' => $providerProfile->logo_url ?? null,
            'cover_image_url' => $providerProfile->cover_image_url ?? null,
            'address' => $providerProfile->address ?? null,
            'latitude' => $providerProfile->latitude ?? null,
            'longitude' => $providerProfile->longitude ?? null,
            'working_hours' => $providerProfile->working_hours ?? null,
            'city' => $provider->city ? [
                'id' => $provider->city->id,
                'name_en' => $provider->city->name_en,
                'name_ar' => $provider->city->name_ar,
            ] : null,
            'statistics' => [
                'total_bookings' => $totalBookings,
                'completed_bookings' => $completedBookings,
                'cancelled_bookings' => $cancelledBookings,
                'pending_bookings' => $pendingBookings,
                'total_revenue' => $totalRevenue,
            ],
            'documents' => $providerProfile->documents ?? [],
            'gallery' => $providerProfile->gallery ?? [],
            'created_at' => $provider->created_at,
            'updated_at' => $provider->updated_at,
        ];

        return response()->json([
            'success' => true,
            'data' => ['provider' => $providerData],
        ]);
    }

    /**
     * Update provider details
     */
    public function update(Request $request, $id)
    {
        $provider = User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })->find($id);

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'email|unique:users,email,' . $id,
            'phone' => 'string|unique:users,phone,' . $id,
            'business_name' => 'string|max:255',
            'business_type' => 'in:salon,clinic,makeup_artist,hair_stylist,individual,company,establishment',
            'business_description' => 'string',
            'address' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $provider->update($request->only(['name', 'email', 'phone']));

        if ($provider->providerProfile) {
            $provider->providerProfile->update($request->only([
                'business_name',
                'business_type',
                'business_description',
                'address',
            ]));
        }

        return response()->json([
            'success' => true,
            'message' => 'Provider updated successfully',
            'data' => ['provider' => $provider->fresh(['providerProfile'])],
        ]);
    }

    /**
     * Update provider status
     */
    public function updateStatus(Request $request, $id)
    {
        $provider = User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })->find($id);

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive,suspended',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $provider->update([
            'status' => $request->status,
            'is_active' => $request->status === 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Provider status updated successfully',
            'data' => ['provider' => $provider],
        ]);
    }

    /**
     * Verify provider (approve/reject)
     */
    public function verifyProvider(Request $request, $id)
    {
        $provider = User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })->with('providerProfile')->find($id);

        if (!$provider || !$provider->providerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Provider not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'notes' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = $request->action === 'approve' ? 'verified' : 'rejected';

        $provider->providerProfile->update([
            'verification_status' => $status,
            'verification_notes' => $request->notes,
            'verified_at' => $request->action === 'approve' ? now() : null,
        ]);

        // If approved, activate the provider account
        if ($request->action === 'approve') {
            $provider->update([
                'status' => 'active',
                'is_active' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $request->action === 'approve'
                ? 'Provider approved successfully'
                : 'Provider rejected successfully',
            'data' => ['provider' => $provider->fresh(['providerProfile'])],
        ]);
    }

    /**
     * Delete provider
     */
    public function destroy($id)
    {
        $provider = User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })->find($id);

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider not found',
            ], 404);
        }

        $provider->delete();

        return response()->json([
            'success' => true,
            'message' => 'Provider deleted successfully',
        ]);
    }

    /**
     * Get provider services
     */
    public function services($id)
    {
        $provider = User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })->find($id);

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider not found',
            ], 404);
        }

        $services = Service::where('provider_id', $id)
            ->with(['category'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['services' => $services],
        ]);
    }

    /**
     * Get provider bookings
     */
    public function bookings(Request $request, $id)
    {
        $provider = User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })->find($id);

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider not found',
            ], 404);
        }

        $perPage = $request->input('per_page', 10);
        $limit = $request->input('limit');

        $query = Booking::where('provider_id', $id)
            ->with(['client', 'service', 'address'])
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $bookings = $query->limit($limit)->get();

            return response()->json([
                'success' => true,
                'data' => ['bookings' => $bookings],
            ]);
        }

        $bookings = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'bookings' => $bookings->items(),
                'pagination' => [
                    'current_page' => $bookings->currentPage(),
                    'from' => $bookings->firstItem(),
                    'to' => $bookings->lastItem(),
                    'per_page' => $bookings->perPage(),
                    'total' => $bookings->total(),
                    'last_page' => $bookings->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get provider revenue
     */
    public function revenue(Request $request, $id)
    {
        $provider = User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })->find($id);

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider not found',
            ], 404);
        }

        $totalRevenue = Booking::where('provider_id', $id)
            ->where('status', 'completed')
            ->sum('total_amount');

        $monthlyRevenue = Booking::where('provider_id', $id)
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->sum('total_amount');

        $yearlyRevenue = Booking::where('provider_id', $id)
            ->where('status', 'completed')
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');

        // Get revenue by category
        $categoryRevenue = DB::table('bookings')
            ->join('booking_items', 'bookings.id', '=', 'booking_items.booking_id')
            ->join('services', 'booking_items.service_id', '=', 'services.id')
            ->join('service_categories', 'services.category_id', '=', 'service_categories.id')
            ->where('bookings.provider_id', $id)
            ->where('bookings.status', 'completed')
            ->select(
                'service_categories.id as category_id',
                'service_categories.name_en',
                'service_categories.name_ar',
                DB::raw('SUM(booking_items.total_price) as revenue')
            )
            ->groupBy('service_categories.id', 'service_categories.name_en', 'service_categories.name_ar')
            ->get();

        // Calculate percentages and format categories
        $categories = [];
        if ($totalRevenue > 0) {
            foreach ($categoryRevenue as $cat) {
                $percentage = ($cat->revenue / $totalRevenue) * 100;
                $categories[] = [
                    'id' => $cat->category_id,
                    'name' => $cat->name_en ?? $cat->name_ar,
                    'revenue' => (float) $cat->revenue,
                    'percentage' => round($percentage, 2),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'monthly_revenue' => $monthlyRevenue,
                'yearly_revenue' => $yearlyRevenue,
                'categories' => $categories,
            ],
        ]);
    }

    /**
     * Get provider reviews
     */
    public function reviews(Request $request, $id)
    {
        $provider = User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })->find($id);

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider not found',
            ], 404);
        }

        $perPage = $request->input('per_page', 10);

        $reviews = Review::where('provider_id', $id)
            ->with(['client', 'booking'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $reviews->items(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'from' => $reviews->firstItem(),
                    'to' => $reviews->lastItem(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'last_page' => $reviews->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get provider activity logs
     */
    public function activityLogs(Request $request, $id)
    {
        $provider = User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })->find($id);

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider not found',
            ], 404);
        }

        // Get activity logs from different sources
        $activities = collect();

        // Bookings
        $bookingActivities = Booking::where('provider_id', $id)
            ->select('id', 'created_at', 'status')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($booking) {
                return [
                    'type' => 'booking',
                    'title' => 'Booking ' . ucfirst($booking->status),
                    'description' => 'Booking #' . $booking->id . ' status changed to ' . $booking->status,
                    'created_at' => $booking->created_at,
                ];
            });

        $activities = $activities->merge($bookingActivities);

        // Services
        $serviceActivities = Service::where('provider_id', $id)
            ->select('id', 'created_at', 'name')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($service) {
                return [
                    'type' => 'service',
                    'title' => 'Service Added',
                    'description' => 'Added service: ' . $service->name,
                    'created_at' => $service->created_at,
                ];
            });

        $activities = $activities->merge($serviceActivities);

        // Sort by created_at
        $activities = $activities->sortByDesc('created_at')->values();

        return response()->json([
            'success' => true,
            'data' => ['activities' => $activities],
        ]);
    }

    /**
     * Export providers
     */
    public function export(Request $request)
    {
        $format = $request->get('format', 'csv');

        $providers = User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })->with(['city', 'providerProfile'])->get();

        if ($format === 'csv') {
            $filename = 'providers-' . date('Y-m-d') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($providers) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Name', 'Email', 'Phone', 'Business Name', 'Status', 'Verification Status', 'City', 'Created At']);

                foreach ($providers as $provider) {
                    fputcsv($file, [
                        $provider->id,
                        $provider->name,
                        $provider->email,
                        $provider->phone,
                        $provider->providerProfile->business_name ?? '',
                        $provider->status,
                        $provider->providerProfile->verification_status ?? '',
                        $provider->city->name_en ?? '',
                        $provider->created_at,
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unsupported export format',
        ], 400);
    }

    /**
     * Get all pending provider profile changes
     */
    public function getPendingChanges(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 20);
            $providerId = $request->input('provider_id');

            \Log::info('=== FETCHING PENDING CHANGES ===', [
                'per_page' => $perPage,
                'provider_id' => $providerId
            ]);

            $query = ProviderPendingChange::with(['provider.user', 'reviewer'])
                ->where('status', 'pending')
                ->orderBy('created_at', 'asc');

            // Filter by specific provider if requested
            if ($providerId) {
                $query->where('provider_id', $providerId);
            }

            $pendingChanges = $query->paginate($perPage);

            \Log::info('=== PENDING CHANGES FETCHED ===', [
                'count' => $pendingChanges->count()
            ]);

            $data = $pendingChanges->map(function ($change) {
                return [
                    'id' => $change->id,
                    'provider_id' => $change->provider_id,
                    'provider_name' => $change->provider && $change->provider->user ? $change->provider->user->name : 'N/A',
                    'business_name' => $change->provider ? $change->provider->business_name : 'N/A',
                    'changed_fields' => $change->changed_fields,
                    'old_values' => $change->old_values,
                    'status' => $change->status,
                    'created_at' => $change->created_at,
                    'updated_at' => $change->updated_at,
                ];
            });

            \Log::info('=== DATA MAPPED ===', [
                'data_count' => $data->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'total' => $pendingChanges->total(),
                    'per_page' => $pendingChanges->perPage(),
                    'current_page' => $pendingChanges->currentPage(),
                    'last_page' => $pendingChanges->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('=== ERROR FETCHING PENDING CHANGES ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching pending changes: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get details of a specific pending change
     */
    public function getPendingChangeDetails($id)
    {
        $change = ProviderPendingChange::with(['provider.user', 'provider.city', 'reviewer'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $change->id,
                'provider' => [
                    'id' => $change->provider->id,
                    'name' => $change->provider->user->name ?? 'N/A',
                    'business_name' => $change->provider->business_name,
                    'email' => $change->provider->user->email ?? 'N/A',
                    'phone' => $change->provider->user->phone ?? 'N/A',
                    'city' => $change->provider->city->name_en ?? 'N/A',
                ],
                'changed_fields' => $change->changed_fields,
                'old_values' => $change->old_values,
                'status' => $change->status,
                'reviewed_by' => $change->reviewer->name ?? null,
                'reviewed_at' => $change->reviewed_at,
                'rejection_reason' => $change->rejection_reason,
                'admin_notes' => $change->admin_notes,
                'created_at' => $change->created_at,
                'updated_at' => $change->updated_at,
            ]
        ]);
    }

    /**
     * Approve pending provider profile changes
     */
    public function approvePendingChange(Request $request, $id)
    {
        $admin = $request->user();
        $change = ProviderPendingChange::with('provider')->findOrFail($id);

        if ($change->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This change request has already been processed.',
            ], 400);
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            // Apply the changes to the provider profile
            $change->provider->update($change->changed_fields);

            // Mark the change as approved
            $change->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_notes' => $validated['admin_notes'] ?? null,
            ]);

            DB::commit();

            \Log::info('=== PROVIDER CHANGE APPROVED ===', [
                'change_id' => $change->id,
                'provider_id' => $change->provider_id,
                'approved_by' => $admin->id,
                'changed_fields' => $change->changed_fields,
            ]);

            // Send notification to provider
            app(\App\Services\NotificationService::class)->sendProfileChangeApproved(
                $change->provider->user_id,
                $change->provider->business_name
            );

            return response()->json([
                'success' => true,
                'message' => 'Provider profile changes have been approved and applied.',
                'data' => [
                    'change_id' => $change->id,
                    'status' => $change->status,
                    'reviewed_at' => $change->reviewed_at,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error approving provider change: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reject pending provider profile changes
     */
    public function rejectPendingChange(Request $request, $id)
    {
        $admin = $request->user();
        $change = ProviderPendingChange::findOrFail($id);

        if ($change->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This change request has already been processed.',
            ], 400);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $change->update([
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        \Log::info('=== PROVIDER CHANGE REJECTED ===', [
            'change_id' => $change->id,
            'provider_id' => $change->provider_id,
            'rejected_by' => $admin->id,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        // Send notification to provider with rejection reason
        $provider = \App\Models\ServiceProvider::find($change->provider_id);
        if ($provider) {
            app(\App\Services\NotificationService::class)->sendProfileChangeRejected(
                $provider->user_id,
                $provider->business_name,
                $validated['rejection_reason']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Provider profile changes have been rejected.',
            'data' => [
                'change_id' => $change->id,
                'status' => $change->status,
                'reviewed_at' => $change->reviewed_at,
                'rejection_reason' => $change->rejection_reason,
            ]
        ]);
    }
}
