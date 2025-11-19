<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class SolicitudesMedicamento extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'test';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'channel',
        'created_by',
        'solicitud',
        'current_status'
    ];

    protected $casts = [];

    protected $attributes = [
        'current_status' => 'created',
        'status_history' => [],
        'solicitud' => []
    ];

    public function setCollection($collectionName)
    {
        // Overwrite the protected property with the new collection name
        $this->collection = $collectionName;

        // Return the instance itself to allow for method chaining
        return $this;
    }

    public function getCollection()
    {
        return $this->collection;
    }

    public function getTable()
    {
        return $this->collection;
    }

    //cosas para el timezone de mongo raro
    public function setDateTo(Carbon $date, string $fromTimezone, string $toTimezone = 'America/Ciudad_Juarez'): Carbon
    {
        return $date->shiftTimezone($fromTimezone)
            ->setTimezone($toTimezone);
    }

    /**
     * Sets 'date' attribute to convert a
     * Carbon date with format Y-m-d\TH:i:s.v\Z into \MongoDB\BSON\UTCDateTime timestamp in UTC.
     */
    public function setDateAttribute(Carbon $date): void
    {
        $convertedDate = $this->setDateTo($date, ($this->timezone ?? $this->default_timezone), $this->default_timezone);
        $this->attributes['date'] = new \MongoDB\BSON\UTCDateTime($convertedDate);
    }


    public static function createSolicitud(array $attributes)
    {
        try {
            $model = new static($attributes);
            if (!isset($attributes['collection'])) {
                throw new \InvalidArgumentException('Collection name is required');
            }
            if (!isset($attributes['collection'])) {
                throw new \InvalidArgumentException('Collection name is required');
            }

            // Generate UUID
            $model->uuid = (string) Str::uuid();

            // Set timestamps
            $now = Carbon::now()->format('Y-m-d H:i:s');

            $model->created_at = $now;
            $model->updated_at = $now;

            // Initialize status history
            $model->status_history = [
                [
                    'status' => 'created',
                    'updated_at' => $now,
                    'updated_by' => $attributes['created_by'] ?? null,
                    'notes' => ''
                ]
            ];

            // Handle solicitud field
            $model->solicitud = $attributes['solicitud'] ?? [];
            // Use regular save() instead of saveRaw()
            // $model->save();

            $model->setCollection($attributes['collection'])->save();


            return $model;

        } catch (\Exception $e) {
            Log::error('model error @ creatingDocument', [
                'error' => $e->getMessage(),
                'collection' => $attributes['collection']
            ]);
            return throw new \Exception($e->getMessage());
        }
    }

    // Override toArray to ensure proper output format
    public function toArray()
    {
        $array = parent::toArray();

        // Ensure solicitud is always an object in output
        $array['solicitud'] = (object) ($array['solicitud'] ?? []);

        // Format timestamps
        if (isset($array['created_at']) && $array['created_at'] instanceof Carbon) {
            $array['created_at'] = $array['created_at']->format('Y-m-d H:i:s');
        }
        if (isset($array['updated_at']) && $array['updated_at'] instanceof Carbon) {
            $array['updated_at'] = $array['updated_at']->format('Y-m-d H:i:s');
        }

        return $array;
    }

    public static function rules(): array
    {
        return [
            'channel' => 'required|string|in:pwa,web,app,postman,DocExtTest,DocExt',
            'created_by' => 'required|string|max:255',
            'solicitud' => 'nullable|array',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            // \Log::debug('Attempting to save to collection: ' . $model->getTable());
            // \Log::debug('Connection: ' . $model->getConnectionName());
        });

        static::creating(function ($model) {
            // Generate UUID if not set
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            // Set created_at to current time
            // $now = Carbon::now()->setTimezone('America/Ciudad_Juarez')
            // ->format('Y-m-d H:i:s');

            //$now = Carbon::now()->format('Y-m-d H:i:s');
            $now = Carbon::now();


            $model->created_at = $now;

            // Initialize status history
            $model->status_history = [
                [
                    'status' => 'created',
                    'updated_at' => $now,
                    'updated_by' => $model->created_by,
                    'notes' => ''
                ]
            ];
        });

        static::updating(function ($model) {
            $now = Carbon::now()->setTimezone('America/Ciudad_Juarez')
                ->format('Y-m-d H:i:s');

            // $now = Carbon::now()->setTimezone('America/Ciudad_Juarez')
            // ->format('Y-m-d H:i:s');

            $now = Carbon::now();

            $model->updated_at = $now;
        });
    }

    public static function validate(array $data): array
    {
        $validator = Validator::make($data, self::rules());

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function addStatusHistory(array $statusData): void
    {
        $history = $this->status_history ?? [];


        // $now = Carbon::now()->setTimezone('America/Ciudad_Juarez')
        //     ->format('Y-m-d H:i:s');

        $now = Carbon::now();


        $history[] = [
            'status' => $statusData['status'] ?? $this->current_status,
            'updated_at' => $now,
            'updated_by' => $statusData['updated_by'] ?? $this->created_by,
            'notes' => $statusData['notes'] ?? ''
        ];

        $this->status_history = $history;
        $this->current_status = $statusData['status'] ?? $this->current_status;
    }

    public function getCreatedAtAttribute($value): string
    {
        return $this->convertMongoDate($value);
    }

    public function getUpdatedAtAttribute($value): ?string
    {
        return $value ? $this->convertMongoDate($value) : null;
    }

    protected function convertMongoDate($value): string
    {
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            return Carbon::createFromTimestampMs($value->toDateTime()->getTimestamp() * 1000)
                ->format('Y-m-d H:i:s');
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    public function deleteDocument($uuid, $collection)
    {
        try {
            if (empty($collection)) {
                throw new \InvalidArgumentException('Collection name is required');
            }
            $this->setCollection($collection);

            if (empty($uuid)) {
                throw new \InvalidArgumentException('UUID is required');
            }

            $document = self::where('uuid', $uuid)->first();

            if (!$document) {
                return false;
            }

            $deleted = $document->delete();

            return $deleted;

        } catch (\Exception $e) {
            Log::error('Error in deleteDocument method', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
                'collection' => $collection
            ]);
            return throw new \Exception($e->getMessage());
        }
    }

    public function cancelledStatus($uuid, array $statusData, $collection)
    {

        try {

            if (!isset($collection)) {
                throw new \InvalidArgumentException('Collection name is required');
            }
            // SET THE COLLECTION FIRST before querying!!!
            $this->setCollection($collection);
            $document = self::where('uuid', $uuid)->first();

            if (!$document) {
                throw new \Exception("Document with UUID {$uuid} not found");
            }

            if ($document->current_status == 'cancelled') {
                throw new \Exception("this document {$uuid} its already with status: {$document->current_status}");
            }

            // Validate the status data
            $validator = Validator::make($statusData, [
                //'status' => 'required|string|in:cancelled,approved,rejected,processing',
                'updated_by' => 'required|string',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            // Create the new status history entry
            // $now = Carbon::now()->setTimezone('America/Ciudad_Juarez')
            //     ->format('Y-m-d H:i:s');
            $now = Carbon::now();


            $newStatusEntry = [
                'status' => 'cancelled',//$statusData['status'],
                'updated_at' => $now,
                'updated_by' => $statusData['updated_by'],
                'notes' => $statusData['notes'] ?? ''
            ];

            // Get current status history and add the new entry
            $currentHistory = $document->status_history ?? [];
            $currentHistory[] = $newStatusEntry;

            // Update the document
            $document->status_history = $currentHistory;
            //$document->current_status = $statusData['status'];
            $document->current_status = $newStatusEntry['status'];
            $document->updated_at = $newStatusEntry['updated_at']; // Use the status update time

            // Save the changes
            $document->save();

            return $document;

        } catch (\Exception $e) {
            Log::error('Error in model cancelledStatus', ['error' => $e->getMessage()]);
            return throw new \Exception($e->getMessage());
        }
    }


    public function updateStatus($uuid, array $statusData, $collection)
    {

        try {

            if (empty($collection)) {
                throw new \InvalidArgumentException('Collection name is required');
            }
            $this->setCollection($collection);


            if (empty($collection)) {
                throw new \InvalidArgumentException('Collection name is required');
            }
            $this->setCollection($collection);

            $document = self::where('uuid', $uuid)->first();

            if (!$document) {
                throw new \Exception("Document with UUID {$uuid} not found");
            }

            if ($document->current_status == 'cancelled') {
                throw new \Exception("this document {$uuid} its already with status: {$document->current_status}");
            }

            // Validate the status data
            $validator = Validator::make($statusData, [
                'updated_by' => 'required|string',
                'notes' => 'nullable|string',
                'status' => 'required|string|in:approved,rejected,processing'
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            // Create the new status history entry
            $now = Carbon::now();

            $newStatusEntry = [
                'status' => $statusData['status'],
                'updated_at' => $now,
                'updated_by' => $statusData['updated_by'],
                'notes' => $statusData['notes'] ?? ''
            ];

            // Get current status history and add the new entry
            $currentHistory = $document->status_history ?? [];
            $currentHistory[] = $newStatusEntry;


            // Update the document
            $document->status_history = $currentHistory;
            $document->current_status = $statusData['status'];
            $document->updated_at = $newStatusEntry['updated_at']; // Use the status update time
            //dd($document);
            // Save the changes
            $document->save();

            return $document;

        } catch (\Exception $e) {
            Log::error('Error in model cancelledStatus', ['error' => $e->getMessage()]);
            return throw new \Exception($e->getMessage());

        }
    }


    //si no jala 'America/Ciudad_Juarez'
    //usar:
    //America/Denver
    public function findByUuid($uuids, $collection)
    {

        try {

            if (!isset($collection)) {
                throw new \InvalidArgumentException('Collection name is required');
            }
            $pipeline = [
                [
                    '$match' => [
                        'uuid' => [
                            '$in' => (array) $uuids
                        ]
                    ]
                ],
                [
                    '$addFields' => [
                        // Convert main timestamps to Ciudad Juarez timezone
                        'timezone' => 'America/Ciudad_Juarez',
                        'created_at' => [
                            '$dateToString' => [
                                'format' => '%Y-%m-%d %H:%M:%S',
                                'date' => '$created_at',
                                'timezone' => 'America/Ciudad_Juarez'
                            ]
                        ],
                        'updated_at' => [
                            '$dateToString' => [
                                'format' => '%Y-%m-%d %H:%M:%S',
                                'date' => '$updated_at',
                                'timezone' => 'America/Ciudad_Juarez'
                            ]
                        ],
                        // Convert and replace updated_at in status_history
                        'status_history' => [
                            '$map' => [
                                'input' => '$status_history',
                                'as' => 'status',
                                'in' => [
                                    'status' => '$$status.status',
                                    'updated_at' => [ // Replace with converted timezone
                                        '$dateToString' => [
                                            'format' => '%Y-%m-%d %H:%M:%S',
                                            'date' => '$$status.updated_at',
                                            'timezone' => 'America/Ciudad_Juarez'
                                        ]
                                    ],
                                    'updated_by' => '$$status.updated_by',
                                    'notes' => '$$status.notes'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    '$project' => [
                        'current_status' => 1,
                        'status_history' => 1,
                        'solicitud' => 1,
                        'channel' => 1,
                        'created_by' => 1,
                        'uuid' => 1,
                        'id' => 1,
                        'created_at' => 1,
                        'updated_at' => 1,
                        'timezone' => 1
                    ]
                ]
            ];

            $results = SolicitudesMedicamento::raw(function ($collection) use ($pipeline) {
                return $collection->aggregate($pipeline);
            })->toArray();

            return $results;

        } catch (\Exception $e) {
            Log::error('model error @ findByUuid', [
                'error' => $e->getMessage(),
                'collection' => $collection
            ]);
            return throw new \Exception($e->getMessage());
        }


    }
}
