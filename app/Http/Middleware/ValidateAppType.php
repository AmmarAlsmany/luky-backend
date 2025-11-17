<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validate that the user is accessing from the correct app type
 * Provider users should use Provider app, Client users should use Client app
 */
class ValidateAppType
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $appType = $request->header('X-App-Type'); // 'provider' or 'client'

        // If user is authenticated and app type header is present
        if ($user && $appType) {
            // Validate that user type matches app type
            if ($user->user_type !== $appType) {
                $correctApp = $user->user_type === 'provider' ? 'Provider' : 'Client';
                $currentApp = $appType === 'provider' ? 'Provider' : 'Client';

                return response()->json([
                    'success' => false,
                    'message' => "This account is registered for {$correctApp}s. Please use the Luky {$correctApp} app instead of the {$currentApp} app.",
                    'error' => 'INVALID_APP_TYPE',
                    'data' => [
                        'user_type' => $user->user_type,
                        'app_type' => $appType,
                        'correct_app' => $correctApp
                    ]
                ], 403);
            }
        }

        return $next($request);
    }
}
