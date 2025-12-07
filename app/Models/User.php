<?php

namespace App\Models;

use App\Models\BaseMongoModel;

class User extends BaseMongoModel
{
    protected $collection = 'users';

   
    protected $fillable = [
        'role',

        // ROOM USERS
        'room_number',
        'room_key',
        'guest_name',
        'guest_uuid',
        'is_occupied',

        // KITCHEN USERS
        'name_kitchenUser',
        'number_kitchenNumber',
        'kitchenUser_key',
        'kitchenUser_uuid',
        'is_active'
    ];

    protected $attributes = [
        // room defaults
        'guest_name'         => null,
        'guest_uuid'         => null,
        'is_occupied'        => false,

        // kitchen defaults
        'name_kitchenUser'   => null,
        'number_kitchenNumber' => null,
        'kitchenUser_key'     => null,
        'kitchenUser_uuid'    => null,
        'is_active'           => false,
    ];

    /**
     * Validation rules for creating/updating users
     */
    public static function rules()
    {
        return [
            'role'        => 'required|string|in:client,kitchen,admin',
            'room_number' => 'nullable|int',
            'room_key'    => 'nullable|int',
            'guest_name'  => 'nullable|string',
        ];
    }

    /**
     * Assign guest to room after login
     */
    public function assignGuest(string $guestName)
    {
        $this->guest_name = $guestName;
        $this->guest_uuid = (string) \Illuminate\Support\Str::uuid();
        $this->is_occupied = true;

        return $this->save();
    }

    /**
     * Completely reset a room after order delivered/cancelled
     */
    public function resetRoom()
    {
        $this->guest_name = null;
        $this->guest_uuid = null;
        $this->is_occupied = false;

        return $this->save();
    }

     /**
     * Assign login to kitchen staff
     */
    public function activateKitchenUser()
    {
        $this->kitchenUser_uuid = (string) \Illuminate\Support\Str::uuid();
        $this->is_active = true;

        return $this->save();
    }

    /**
     * Logout kitchen user
     */
    public function deactivateKitchenUser()
    {
        $this->is_active = false;
        $this->kitchenUser_uuid = null;

        return $this->save();
    }
}
