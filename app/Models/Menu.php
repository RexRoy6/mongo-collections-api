<?php

namespace App\Models;

use App\Models\BaseMongoModel;

class Menu extends BaseMongoModel
{
    protected $collection = 'menus';

    protected $fillable = [
        'menu_key',     // Example: 'menu_cafe', 'menu_hotel', 'menu_breakfast'
        'menu_info',    // Description: "menu from cafe"
        'items',        // Array of menu items
    ];
}
