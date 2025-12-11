<?php

namespace App\Models\Traits;

use App\Models\Business;
use App\Helpers\BusinessHelper;

trait BelongsToBusiness
{
    /**
     * Boot the trait
     */
    protected static function bootBelongsToBusiness()
    {
        static::creating(function ($model) {
            // Automatically set business_uuid if not provided
            if (empty($model->business_uuid) && BusinessHelper::hasContext()) {
                $model->business_uuid = BusinessHelper::uuid();
            }
        });
    }

    /**
     * Get the business this model belongs to
     */
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_uuid', 'uuid');
    }

    /**
     * Scope queries to current business
     */
    public function scopeForCurrentBusiness($query)
    {
        $businessUuid = BusinessHelper::uuid();
        
        if ($businessUuid) {
            return $query->where('business_uuid', $businessUuid);
        }
        
        return $query;
    }

    /**
     * Scope queries to specific business
     */
    public function scopeForBusiness($query, $businessUuid)
    {
        return $query->where('business_uuid', $businessUuid);
    }

    /**
     * Check if model belongs to current business
     */
    public function belongsToCurrentBusiness()
    {
        return $this->business_uuid === BusinessHelper::uuid();
    }
}