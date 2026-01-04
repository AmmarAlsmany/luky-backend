<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Get all settings
     */
    public function index()
    {
        // Get settings from cache or database
        $settings = Cache::remember('app_settings', 3600, function () {
            return DB::table('settings')->pluck('value', 'key')->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => ['settings' => $settings],
        ]);
    }

    /**
     * Get single setting by key
     */
    public function show($key)
    {
        $setting = DB::table('settings')->where('key', $key)->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => ['setting' => $setting],
        ]);
    }

    /**
     * Update or create setting
     */
    public function update(Request $request, $key)
    {
        $validator = Validator::make($request->all(), [
            'value' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $existing = DB::table('settings')->where('key', $key)->first();

        if ($existing) {
            DB::table('settings')->where('key', $key)->update([
                'value' => $request->value,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => $request->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Clear cache
        Cache::forget('app_settings');

        $setting = DB::table('settings')->where('key', $key)->first();

        return response()->json([
            'success' => true,
            'data' => ['setting' => $setting],
            'message' => 'Setting updated successfully',
        ]);
    }

    /**
     * Bulk update settings
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->settings as $key => $value) {
            $existing = DB::table('settings')->where('key', $key)->first();

            if ($existing) {
                DB::table('settings')->where('key', $key)->update([
                    'value' => $value,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Clear cache
        Cache::forget('app_settings');

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
        ]);
    }

    /**
     * Get general app settings
     */
    public function getAppSettings()
    {
        $keys = [
            'app_name',
            'app_name_ar',
            'app_description',
            'app_description_ar',
            'app_logo',
            'app_icon',
            'support_email',
            'support_phone',
            'default_language',
            'timezone',
            'date_format',
            'time_format',
        ];

        $settings = DB::table('settings')
            ->whereIn('key', $keys)
            ->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => ['settings' => $settings],
        ]);
    }

    /**
     * Update general app settings
     */
    public function updateAppSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'app_name' => 'nullable|string|max:255',
            'app_name_ar' => 'nullable|string|max:255',
            'app_description' => 'nullable|string',
            'app_description_ar' => 'nullable|string',
            'support_email' => 'nullable|email',
            'support_phone' => 'nullable|string',
            'default_language' => 'nullable|in:en,ar',
            'timezone' => 'nullable|string',
            'date_format' => 'nullable|string',
            'time_format' => 'nullable|string',
            'app_logo' => 'nullable|image|max:2048',
            'app_icon' => 'nullable|image|max:1024',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->except(['app_logo', 'app_icon']) as $key => $value) {
            if ($value !== null) {
                $this->updateSetting($key, $value);
            }
        }

        // Handle logo upload
        if ($request->hasFile('app_logo')) {
            $logoPath = $request->file('app_logo')->store('app', 'public');
            $this->updateSetting('app_logo', $logoPath);
        }

        // Handle icon upload
        if ($request->hasFile('app_icon')) {
            $iconPath = $request->file('app_icon')->store('app', 'public');
            $this->updateSetting('app_icon', $iconPath);
        }

        Cache::forget('app_settings');

        return response()->json([
            'success' => true,
            'message' => 'App settings updated successfully',
        ]);
    }

    /**
     * Get booking settings
     */
    public function getBookingSettings()
    {
        $keys = [
            'booking_window_days',
            'cancellation_window_hours',
            'auto_confirm_bookings',
            'allow_same_day_booking',
            'max_bookings_per_day',
            'booking_slot_duration',
        ];

        $settings = DB::table('settings')
            ->whereIn('key', $keys)
            ->pluck('value', 'key');

        // Add provider acceptance timeout from app_settings table
        $settings['provider_acceptance_timeout_minutes'] = \App\Models\AppSetting::get('provider_acceptance_timeout_minutes', 30);

        return response()->json([
            'success' => true,
            'data' => ['settings' => $settings],
        ]);
    }

    /**
     * Update booking settings
     */
    public function updateBookingSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_window_days' => 'nullable|integer|min:1|max:365',
            'cancellation_window_hours' => 'nullable|integer|min:0|max:168',
            'auto_confirm_bookings' => 'nullable|boolean',
            'allow_same_day_booking' => 'nullable|boolean',
            'max_bookings_per_day' => 'nullable|integer|min:1',
            'booking_slot_duration' => 'nullable|integer|min:15',
            'provider_acceptance_timeout_minutes' => 'nullable|integer|min:5|max:1440',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Handle provider_acceptance_timeout_minutes separately (stored in app_settings)
        if ($request->has('provider_acceptance_timeout_minutes') && $request->provider_acceptance_timeout_minutes !== null) {
            \App\Models\AppSetting::set('provider_acceptance_timeout_minutes', $request->provider_acceptance_timeout_minutes);
        }

        // Update other settings (stored in settings table)
        foreach ($request->except('provider_acceptance_timeout_minutes') as $key => $value) {
            if ($value !== null) {
                $this->updateSetting($key, $value);
            }
        }

        Cache::forget('app_settings');

        return response()->json([
            'success' => true,
            'message' => 'Booking settings updated successfully',
        ]);
    }

    /**
     * Get notification settings
     */
    public function getNotificationSettings()
    {
        $keys = [
            'enable_email_notifications',
            'enable_sms_notifications',
            'enable_push_notifications',
            'notification_sound',
            'notify_booking_created',
            'notify_booking_confirmed',
            'notify_booking_cancelled',
            'notify_booking_completed',
        ];

        $settings = DB::table('settings')
            ->whereIn('key', $keys)
            ->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => ['settings' => $settings],
        ]);
    }

    /**
     * Update notification settings
     */
    public function updateNotificationSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enable_email_notifications' => 'nullable|boolean',
            'enable_sms_notifications' => 'nullable|boolean',
            'enable_push_notifications' => 'nullable|boolean',
            'notification_sound' => 'nullable|boolean',
            'notify_booking_created' => 'nullable|boolean',
            'notify_booking_confirmed' => 'nullable|boolean',
            'notify_booking_cancelled' => 'nullable|boolean',
            'notify_booking_completed' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->all() as $key => $value) {
            if ($value !== null) {
                $this->updateSetting($key, $value);
            }
        }

        Cache::forget('app_settings');

        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated successfully',
        ]);
    }

    /**
     * Get maintenance mode settings
     */
    public function getMaintenanceMode()
    {
        $settings = [
            'maintenance_mode' => DB::table('settings')->where('key', 'maintenance_mode')->value('value') === 'true',
            'maintenance_message' => DB::table('settings')->where('key', 'maintenance_message')->value('value'),
            'maintenance_end_time' => DB::table('settings')->where('key', 'maintenance_end_time')->value('value'),
        ];

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Toggle maintenance mode
     */
    public function toggleMaintenanceMode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'message' => 'nullable|string',
            'end_time' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $this->updateSetting('maintenance_mode', $request->enabled ? 'true' : 'false');

        if ($request->message) {
            $this->updateSetting('maintenance_message', $request->message);
        }

        if ($request->end_time) {
            $this->updateSetting('maintenance_end_time', $request->end_time);
        }

        Cache::forget('app_settings');

        return response()->json([
            'success' => true,
            'message' => $request->enabled ? 'Maintenance mode enabled' : 'Maintenance mode disabled',
        ]);
    }

    /**
     * Clear application cache
     */
    public function clearCache()
    {
        Cache::flush();

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully',
        ]);
    }

    /**
     * Helper method to update setting
     */
    private function updateSetting($key, $value)
    {
        $existing = DB::table('settings')->where('key', $key)->first();

        if ($existing) {
            DB::table('settings')->where('key', $key)->update([
                'value' => $value,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
