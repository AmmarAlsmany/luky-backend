<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Check both is_active flag and status field
            if (!$user->is_active || $user->status !== 'active') {
                // Allow rejected providers to access profile endpoints to see rejection reason
                $allowedPaths = [
                    'api/v1/user/profile',
                    'api/v1/provider/profile',
                ];

                $isAllowedPath = false;
                foreach ($allowedPaths as $path) {
                    if ($request->is($path)) {
                        $isAllowedPath = true;
                        break;
                    }
                }

                // If trying to access profile endpoint, allow it so they can see rejection reason
                if ($isAllowedPath) {
                    return $next($request);
                }

                // For all other endpoints, block access
                $statusMessage = !$user->is_active
                    ? 'Your account is inactive. Please contact support.'
                    : 'Your account has been ' . $user->status . '. Please contact support.';

                if ($request->expectsJson() || $request->is('api/*')) {
                    // For API requests, return JSON response
                    // Token revocation is handled by the client when it receives this response
                    return response()->json([
                        'success' => false,
                        'message' => $statusMessage,
                        'error_code' => 'account_inactive'
                    ], 403);
                }

                // For web requests, logout session and redirect to login with error message
                Auth::logout();
                return redirect()->route('login')
                    ->with('error', $statusMessage);
            }
        }

        return $next($request);
    }
}
