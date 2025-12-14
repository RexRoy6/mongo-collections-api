<?php

namespace App\Models;

use App\Models\BaseMongoModel;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Traits\BelongsToBusiness;
use Illuminate\Support\Str;

class User extends BaseMongoModel
{
    use HasApiTokens, BelongsToBusiness;

    protected $collection = 'users';

    protected $fillable = [
        'business_uuid',
        'role',              // client | kitchen | barista | admin

        // Shared identity
        'name',
        'email',
        'password',

        // Client (hotel room)
        'room_number',
        'room_key',
        'guest_uuid',
        'is_occupied',

        // Staff (kitchen / barista)
        'staff_number',
        'staff_key',
        'is_active',
    ];

    protected $attributes = [
        'is_occupied' => false,
        'is_active'   => false,
    ];

    protected $hidden = [
        'password',
    ];

    /* ===========================
       Role helpers
    ============================ */

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function isKitchen(): bool
    {
        return $this->role === 'kitchen';
    }

    public function isBarista(): bool
    {
        return $this->role === 'barista';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /* ===========================
       Client (room) actions
    ============================ */

    public function assignGuest(string $guestName): void
    {
        $this->update([
            'name'        => $guestName,
            'guest_uuid'  => (string) Str::uuid(),
            'is_occupied' => true,
        ]);
    }

    public function resetRoom(): void
    {
        $this->update([
            'name'        => null,
            'guest_uuid'  => null,
            'is_occupied' => false,
        ]);
    }

    /* ===========================
       Staff actions
    ============================ */

    public function activateStaff(): void
    {
        $this->update([
            'is_active' => true,
        ]);
    }

    public function deactivateStaff(): void
    {
        $this->update([
            'is_active' => false,
        ]);
    }
}
