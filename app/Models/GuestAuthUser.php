<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class GuestAuthUser extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'guest_uuid',
        'guest_name',
        'room_number',
    ];

    protected $hidden = [
        'remember_token',
    ];
}
