<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * Get a setting value by key (with caching)
     */
    public static function get(string $key, $default = null)
    {
        return \Cache::remember(
            'settings:' . $key,
            3600, // 1 hour
            function () use ($key, $default) {
                $setting = static::where('key', $key)->first();

                if (!$setting) {
                    return $default;
                }

                return static::castValue($setting->value, $setting->type);
            }
        );
    }

    /**
     * Set a setting value (clears cache)
     */
    public static function set(string $key, $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );

        // Clear cache for this setting
        \Cache::forget('settings:' . $key);
        \Cache::forget('settings:all');
    }

    /**
     * Cast value based on type
     */
    protected static function castValue($value, string $type)
    {
        return match($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'decimal', 'float' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => static::castValue($setting->value, $setting->type)];
            })
            ->toArray();
    }
}
