<?php

namespace App\Models;

use App\Models\BaseMongoModel;

class Menu extends BaseMongoModel
{
    protected $collection = 'menus';

    protected $fillable = [
        'business_uuid',
        'menu_key',     // Example: 'menu_cafe', 'menu_hotel', 'menu_breakfast'
        'menu_info',    // Description: "menu from cafe"
        'items',        // Array of menu items
    ];

    protected $attributes = [
        'items' => []
    ];

    public static function rules()
    {
        return [
            'menu_key'  => 'required|string',
            'menu_info' => 'nullable|string',
            'items'     => 'required|array|min:1',
            'items.*.name'  => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.image' => 'nullable|string',
            'currency' => 'required|string|in:mxn,usd'
        ];
    }
}
