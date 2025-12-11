<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class GuestAuthUser extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'business_uuid',
        'business_key', // Keep this for convenience
        'guest_uuid',
        'guest_name',
        'room_number',
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
