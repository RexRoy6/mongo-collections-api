<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class kitchenAuthUser extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'kitchenUser_uuid',
        'name_kitchenUser',
        'business_uuid',     // Add this
        'business_key', // Keep this for convenience
    ];

    protected $hidden = [
        'remember_token',
    ];

    // Optional: Add relationship to business
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_uuid', 'uuid');
    }

     // Add a scope for business filtering
    public function scopeForBusiness($query, $businessUuid)
    {
        return $query->where('business_uuid', $businessUuid);
    }
}