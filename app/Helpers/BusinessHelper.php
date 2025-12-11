<?php

namespace App\Helpers;

use App\Models\Business;

class BusinessHelper
{
    /**
     * Get current business instance
     */
    public static function current()
    {
        return app('current_business');
    }

    /**
     * Get business configuration value
     */
    public static function config($key, $default = null)
    {
        $business = self::current();
        
        if (!$business) {
            return $default;
        }

        return $business->config[$key] ?? $default;
    }

    /**
     * Check if business context is set
     */
    public static function hasContext()
    {
        return !is_null(app('current_business'));
    }

    /**
     * Get business UUID
     */
    public static function uuid()
    {
        $business = self::current();
        
        return $business ? $business->uuid : null;
    }

    /**
     * Get business key
     */
    public static function key()
    {
        $business = self::current();
        
        return $business ? $business->business_key : null;
    }
}