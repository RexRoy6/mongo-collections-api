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
        'name_kitchenUser'
    ];

    protected $hidden = [
        'remember_token',
    ];
}
