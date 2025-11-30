<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

abstract class BaseMongoModel extends Model
{
    protected $connection = 'mongodb';

    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $dates = ['created_at', 'updated_at'];

    protected static function boot()
    {
        parent::boot();

        // Generate UUID
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            $model->created_at = Carbon::now();
            $model->updated_at = Carbon::now();
        });

        static::updating(function ($model) {
            $model->updated_at = Carbon::now();
        });
    }

    /**  Dynamic collection setter  */
    public function setCollection($name)
    {
        $this->collection = $name;
        return $this;
    }

    public function getTable()
    {
        return $this->collection;
    }

    /**  Convert timestamps for JSON  */
    public function toArray()
    {
        $array = parent::toArray();

        foreach (['created_at', 'updated_at'] as $field) {
            if (isset($array[$field]) && $array[$field] instanceof Carbon) {
                $array[$field] = $array[$field]->format('Y-m-d H:i:s');
            }
        }

        return $array;
    }

    protected function serializeDate(\DateTimeInterface $date)
{
    return Carbon::parse($date)
        ->setTimezone(env('timeZone')) // change timezone in env? or in request
        ->format('Y-m-d H:i:s');
}


public static function deleteByUuid(string $uuid, string $collection)
{
    $model = new static();
    $model->setCollection($collection);

    $doc = $model->where('uuid', $uuid)->first();

    if (!$doc) {
        return false;
    }
    $doc->setCollection($collection);

    return $doc->delete();
}

}
