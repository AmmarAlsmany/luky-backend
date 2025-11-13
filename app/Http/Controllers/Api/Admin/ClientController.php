<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    /**
     * Get list of clients with pagination and filters
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $search = $request->input('search');
        $status = $request->input('status');
        $cityId = $request->input('city_id');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = User::whereHas('roles', function ($q) {
            $q->where('name', 'client');
        })
        ->with(['city', 'roles'])
        ->withCount('bookings as bookings_count')
        ->withSum('bookings as total_spent', 'total_amount');

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        // Status filter
        if ($status) {
            $query->where('status', $status);
        }

        // City filter
        if ($cityId) {
            $query->where('city_id', $cityId);
        }

        // Date range filter
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Sorting
        $query->orderBy($sortBy, $sortOrder);

        $clients = $query->paginate($perPage);

        // Get stats
        $stats = [
            'total_clients' => User::whereHas('roles', fn($q) => $q->where('name', 'client'))->count(),
            'active_clients' => User::whereHas('roles', fn($q) => $q->where('name', 'client'))
                ->where('status', 'active')->count(),
            'inactive_clients' => User::whereHas('roles', fn($q) => $q->where('name', 'client'))
                ->where('status', 'inactive')->count(),
            'new_clients' => User::whereHas('roles', fn($q) => $q->where('name', 'client'))
                ->whereDate('created_at', '>=', now()->subDays(30))->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'clients' => $clients->map(function ($client) {
                    return [
                        'id' => $client->id,
                        'name' => $client->name,
                        'email' => $client->email,
                        'phone' => $client->phone,
                        'city' => $client->city ? $client->city->name_en : null,
                        'city_id' => $client->city_id,
                        'status' => $client->status,
                        'bookings_count' => $client->bookings_count ?? 0,
                        'total_spent' => $client->total_spent ?? 0,
                        'avatar' => $client->avatar,
                        'avatar_url' => $client->avatar,
                        'created_at' => $client->created_at,
                        'last_login_at' => $client->last_login_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $clients->currentPage(),
                    'per_page' => $clients->perPage(),
                    'total' => $clients->total(),
                    'last_page' => $clients->lastPage(),
                    'from' => $clients->firstItem(),
                    'to' => $clients->lastItem(),
                ],
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Register a new client (Admin only)
     */
    public function store(Request $request)
    {
        // Use PhoneNumberService for normalization (same as mobile app)
        $phoneService = new \App\Services\PhoneNumberService();
        $normalizedPhone = $phoneService->normalize($request->input('phone'));

        // Saudi phone number validation rule (same as mobile app)
        $phoneRule = new \App\Rules\SaudiPhoneNumber();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email,NULL,id,deleted_at,NULL', // Optional (aligned with mobile)
            'phone' => [
                'required',
                'string',
                $phoneRule, // Saudi phone format validation
                'unique:users,phone,NULL,id,deleted_at,NULL'
            ],
            'city_id' => 'required|exists:cities,id', // Required (aligned with mobile)
            'date_of_birth' => 'required|date|before:today', // Required (aligned with mobile)
            'gender' => 'required|in:male,female', // Required (aligned with mobile)
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create user with normalized phone (same as mobile app)
            $client = User::create([
                'name' => $request->name,
                'email' => $request->email, // Optional field
                'phone' => $normalizedPhone, // Normalized phone number
                'city_id' => $request->city_id,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'user_type' => 'client',
                'status' => 'active',
                'is_active' => true,
                'phone_verified_at' => now(), // Auto-verify for admin-created accounts
            ]);

            // Assign client role
            $client->assignRole('client');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => ['client' => $client],
                'message' => 'Client registered successfully',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to register client: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get client details
     */
    public function show($id)
    {
        $client = User::whereHas('roles', function ($q) {
            $q->where('name', 'client');
        })
        ->with(['city', 'roles'])
        ->withCount('bookings')
        ->withSum('bookings as total_spent', 'total_amount')
        ->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found',
            ], 404);
        }

        // Format addresses array (convert single address to array format for dashboard)
        $addresses = [];
        if ($client->address) {
            $addresses[] = [
                'label' => 'Primary Address',
                'full_address' => $client->address,
                'city' => $client->city ? $client->city->name_en : null,
                'latitude' => $client->latitude,
                'longitude' => $client->longitude,
                'is_default' => true,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'date_of_birth' => $client->date_of_birth,
                    'gender' => $client->gender,
                    'city' => $client->city,
                    'address' => $client->address,
                    'latitude' => $client->latitude,
                    'longitude' => $client->longitude,
                    'status' => $client->status,
                    'is_active' => $client->is_active,
                    'avatar' => $client->avatar,
                    'avatar_url' => $client->avatar,
                    'total_bookings' => $client->bookings_count ?? 0,
                    'bookings_count' => $client->bookings_count ?? 0,
                    'total_spent' => $client->total_spent ?? 0,
                    'phone_verified_at' => $client->phone_verified_at,
                    'created_at' => $client->created_at,
                    'last_login_at' => $client->last_login_at,
                    'addresses' => $addresses,
                ],
            ],
        ]);
    }

    /**
     * Update client
     */
    public function update(Request $request, $id)
    {
        $client = User::whereHas('roles', fn($q) => $q->where('name', 'client'))->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $client->id . ',id,deleted_at,NULL',
            'phone' => 'sometimes|string|unique:users,phone,' . $client->id . ',id,deleted_at,NULL',
            'date_of_birth' => 'sometimes|date',
            'gender' => 'sometimes|in:male,female',
            'city_id' => 'sometimes|exists:cities,id',
            'address' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $client->update($request->only([
            'name', 'email', 'phone', 'date_of_birth', 'gender', 'city_id', 'address'
        ]));

        return response()->json([
            'success' => true,
            'data' => ['client' => $client],
            'message' => 'Client updated successfully',
        ]);
    }

    /**
     * Update client status
     */
    public function updateStatus(Request $request, $id)
    {
        $client = User::whereHas('roles', fn($q) => $q->where('name', 'client'))->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found',
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

        $client->update([
            'status' => $request->status,
            'is_active' => $request->status === 'active',
        ]);

        return response()->json([
            'success' => true,
            'data' => ['client' => $client],
            'message' => 'Client status updated successfully',
        ]);
    }

    /**
     * Delete client (soft delete)
     */
    public function destroy($id)
    {
        $client = User::whereHas('roles', fn($q) => $q->where('name', 'client'))->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found',
            ], 404);
        }

        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'Client deleted successfully',
        ]);
    }

    /**
     * Get client bookings
     */
    public function bookings($id)
    {
        $client = User::whereHas('roles', fn($q) => $q->where('name', 'client'))->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found',
            ], 404);
        }

        $bookings = Booking::where('client_id', $id)
            ->with(['provider.user', 'items.service'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => [
                'bookings' => $bookings->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'booking_date' => $booking->booking_date,
                        'booking_time' => $booking->booking_time,
                        'status' => $booking->status,
                        'total_amount' => $booking->total_amount,
                        'service' => $booking->items->first()?->service ? [
                            'id' => $booking->items->first()->service->id,
                            'name' => $booking->items->first()->service->name,
                        ] : null,
                        'provider' => $booking->provider ? [
                            'id' => $booking->provider->id,
                            'business_name' => $booking->provider->business_name,
                            'name' => $booking->provider->user->name ?? null,
                        ] : null,
                        'created_at' => $booking->created_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $bookings->currentPage(),
                    'per_page' => $bookings->perPage(),
                    'total' => $bookings->total(),
                    'last_page' => $bookings->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get client transactions
     */
    public function transactions($id)
    {
        $client = User::whereHas('roles', fn($q) => $q->where('name', 'client'))->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found',
            ], 404);
        }

        $transactions = Payment::whereHas('booking', function ($q) use ($id) {
            $q->where('client_id', $id);
        })
        ->with('booking')
        ->orderBy('created_at', 'desc')
        ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'status' => $payment->status,
                        'type' => $payment->payment_method ?? 'payment',
                        'description' => $payment->booking ? "Booking #{$payment->booking->id}" : 'Payment',
                        'payment_method' => $payment->payment_method,
                        'transaction_id' => $payment->transaction_id,
                        'created_at' => $payment->created_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'last_page' => $transactions->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Export clients to CSV
     */
    public function export(Request $request)
    {
        // TODO: Implement CSV export using Maatwebsite\Excel
        return response()->json([
            'success' => true,
            'message' => 'Export functionality coming soon',
        ]);
    }

    /**
     * Get client statistics
     */
    public function stats()
    {
        $stats = [
            'total_clients' => User::whereHas('roles', fn($q) => $q->where('name', 'client'))->count(),
            'active_clients' => User::whereHas('roles', fn($q) => $q->where('name', 'client'))
                ->where('status', 'active')->count(),
            'inactive_clients' => User::whereHas('roles', fn($q) => $q->where('name', 'client'))
                ->where('status', 'inactive')->count(),
            'suspended_clients' => User::whereHas('roles', fn($q) => $q->where('name', 'client'))
                ->where('status', 'suspended')->count(),
            'new_this_month' => User::whereHas('roles', fn($q) => $q->where('name', 'client'))
                ->whereMonth('created_at', now()->month)->count(),
            'total_bookings' => Booking::count(),
            'total_revenue' => Payment::where('status', 'completed')->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
