<?php

namespace App\Models;

use App\Models\BaseMongoModel;
use Illuminate\Support\Str;

class Business extends BaseMongoModel
{
    protected $collection = 'Business';

    protected $fillable = [
        'business_key',     // Example: 'lunas_cafe', 'hotel', 'abogados'
        'business_info',    // Description: "lunas cafe code"
        'business_code',    // Random generated 8-digit code
        'is_active',        // Active status true/false
        'config',           // Business-specific configuration
        'public_config',    // Public info for frontend (theme, logo, etc.)
    ];

    protected $casts = [
        'business_code' => 'integer',
        'is_active' => 'boolean',
        'config' => 'array',
        'public_config' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
        'config' => [],
        'public_config' => [],
    ];

    protected static function boot()
    {
        parent::boot();

        // Generate unique 8-digit code before creating
        static::creating(function ($model) {
            // Only generate if code doesn't exist
            if (empty($model->business_code)) {
                do {
                    $code = mt_rand(10000000, 99999999); // 8-digit number
                } while (self::where('business_code', $code)->exists());
                
                $model->business_code = $code;
            }
        });
    }

    public static function rules()
    {
        return [
            'business_key'  => 'required|string|unique:Business,business_key',
            'business_info' => 'nullable|string',
            'is_active'     => 'boolean',
            'config'        => 'nullable|array',
            'public_config' => 'nullable|array',
        ];
    }

    /**
     * Scope to get only active businesses
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get public info for frontend
     */
    public function getPublicInfo()
    {
        $defaultPublicConfig = [
            'theme' => 'default',
            'primary_color' => '#4a90e2',
            'secondary_color' => '#f5a623',
            'logo_url' => null,
            'welcome_message' => "Welcome to {$this->business_info}",
            'login_options' => ['client', 'kitchen'], // Which logins are available
        ];

        $publicConfig = array_merge($defaultPublicConfig, $this->public_config ?? []);

        return [
            'uuid' => $this->uuid,
            'name' => $this->business_info,
            'key' => $this->business_key,
            'code' => $this->business_code,
            'is_active' => $this->is_active,
            'public_config' => $publicConfig,
            'created_at' => $this->created_at,
        ];
    }

      /**
     * Get default config values
     */
    public function getConfigAttribute($value)
    {
        $defaults = [
            'timezone' => 'UTC',
            'currency' => 'mxn',
            'language' => 'en'
        ];

        $config = $value ? json_decode($value, true) : [];
        
        return array_merge($defaults, $config);
    }
}