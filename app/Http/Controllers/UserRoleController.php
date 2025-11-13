<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserRoleController extends Controller
{
    /**
     * Display dashboard users list
     */
    public function users(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $role = $request->input('role');

        // Dashboard roles only
        $dashboardRoles = ['super_admin', 'admin', 'manager', 'support_agent', 'content_manager', 'analyst'];

        $query = User::whereHas('roles', function ($q) use ($dashboardRoles) {
            $q->whereIn('name', $dashboardRoles);
        })->with(['roles']);

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
            $query->where('is_active', $status === 'active');
        }

        // Role filter
        if ($role) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        // Transform for view
        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'is_active' => $user->is_active,
                'roles' => $user->getRoleNames(),
                'permissions_count' => $user->getAllPermissions()->count(),
                'created_at' => $user->created_at,
                'last_login_at' => $user->last_login_at,
            ];
        });

        // Get all roles for filter
        $roles = Role::whereIn('name', $dashboardRoles)->get();

        // Statistics
        $stats = [
            'total' => User::whereHas('roles', fn($q) => $q->whereIn('name', $dashboardRoles))->count(),
            'active' => User::whereHas('roles', fn($q) => $q->whereIn('name', $dashboardRoles))
                ->where('is_active', true)->count(),
            'inactive' => User::whereHas('roles', fn($q) => $q->whereIn('name', $dashboardRoles))
                ->where('is_active', false)->count(),
        ];

        $pagination = [
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
            'from' => $users->firstItem(),
            'to' => $users->lastItem(),
        ];

        $filters = compact('search', 'status', 'role');

        return view('adminrole.users', [
            'users' => $users->items(),
            'roles' => $roles,
            'stats' => $stats,
            'pagination' => $pagination,
            'filters' => $filters
        ]);
    }

    /**
     * Display roles list
     */
    public function roles(Request $request)
    {
        $roles = Role::where('guard_name', 'web')
            ->withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        // Transform for view
        $rolesData = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => app()->getLocale() === 'ar' ? ($role->name_ar ?? ucwords(str_replace('_', ' ', $role->name))) : ($role->name_en ?? ucwords(str_replace('_', ' ', $role->name))),
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions->count(),
                'permissions' => $role->permissions->pluck('name'),
                'created_at' => $role->created_at,
            ];
        });

        $stats = [
            'total_roles' => $roles->count(),
            'total_permissions' => Permission::count(),
        ];

        return view('adminrole.list', [
            'roles' => $rolesData,
            'stats' => $stats
        ]);
    }

    /**
     * Show edit role page
     */
    public function editRole($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        $allPermissions = Permission::all()->groupBy(function ($permission) {
            // Group permissions by module (e.g., view_clients, create_clients -> clients)
            $parts = explode('_', $permission->name);
            return count($parts) > 1 ? implode('_', array_slice($parts, 1)) : 'general';
        });

        return view('adminrole.edit', [
            'role' => $role,
            'allPermissions' => $allPermissions,
            'rolePermissions' => $role->permissions->pluck('name')->toArray()
        ]);
    }

    /**
     * Update role permissions
     */
    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        // Sync permissions
        $role->syncPermissions($request->input('permissions', []));

        return redirect()->route('adminrole.roles')
            ->with('success', 'Role permissions updated successfully');
    }

    /**
     * Store new role
     */
    public function storeRole(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name',
                'description' => 'nullable|string',
                'status' => 'nullable|string|in:Active,Inactive',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string',
            ]);

            // Create role
            $role = Role::create([
                'name' => strtolower(str_replace(' ', '_', $validated['name'])),
                'guard_name' => 'web',
            ]);

            // Process and assign permissions
            if (!empty($validated['permissions'])) {
                $permissionNames = [];
                foreach ($validated['permissions'] as $permission) {
                    // Permission format: module.action (e.g., clients.create)
                    $permissionNames[] = $permission;
                    
                    // Create permission if it doesn't exist
                    Permission::firstOrCreate([
                        'name' => $permission,
                        'guard_name' => 'web',
                    ]);
                }
                
                $role->syncPermissions($permissionNames);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating role', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store new dashboard user
     */
    public function storeUser(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|unique:users,phone',
                'password' => 'required|string|min:8',
                'role' => 'required|in:super_admin,admin,manager,support_agent,content_manager,analyst',
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'user_type' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'created_by' => auth()->id(),
            ]);

            // Assign role
            $user->assignRole($validated['role']);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
            ]);
        } catch (QueryException $e) {
            // Handle database errors
            Log::error('Database error creating user', ['error' => $e->getMessage()]);
            
            $errorMessage = 'Failed to create user';
            
            if (strpos($e->getMessage(), 'users_email_unique') !== false) {
                $errorMessage = 'This email address is already registered';
            } elseif (strpos($e->getMessage(), 'users_phone_unique') !== false) {
                $errorMessage = 'This phone number is already registered';
            }
            
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
            ], 422);
        } catch (\Exception $e) {
            // Handle any other errors
            Log::error('Error creating user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user status
     */
    public function toggleUserStatus($id)
    {
        $user = User::findOrFail($id);

        // Prevent deactivating yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate your own account',
            ], 403);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
        ]);
    }

    /**
     * Update user role
     */
    public function updateUserRole(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'role' => 'required|in:super_admin,admin,manager,support_agent,content_manager,analyst',
        ]);

        // Remove all current roles and assign new one
        $user->syncRoles([$validated['role']]);

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
        ]);
    }

    /**
     * Delete dashboard user
     */
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully',
        ]);
    }
}
