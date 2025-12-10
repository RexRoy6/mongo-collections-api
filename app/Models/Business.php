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
        'business_code',             // random generated code
    ];

    protected $casts = [
        'business_code' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();

        // Generate unique 8-digit code before creating
        static::creating(function ($model) {
            // Only generate if code doesn't exist
            if (empty($model->code)) {
                do {
                    $code = mt_rand(10000000, 99999999); // 8-digit number
                } while (self::where('business_code', $code)->exists());
                
                $model->code = $code;
            }
        });
    }

    public static function rules()
    {
        return [
            'business_key'  => 'required|string|unique:Business,business_key',
            'business_info' => 'nullable|string',
        ];
    }
}