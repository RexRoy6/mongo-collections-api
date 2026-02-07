<?php

namespace App\Models;

use App\Models\BaseMongoModel;
use App\Models\Traits\BelongsToBusiness;

class Menu extends BaseMongoModel
{
    use BelongsToBusiness;

    protected $collection = 'menus';

    protected $fillable = [
        'business_uuid',
        'menu_key',
        'menu_info',
        'items',
        'is_active',
        'version'
    ];

    protected $attributes = [
        'items' => [],
        'is_active' => true,
        'version' => 1
    ];


    public static function rules()
    {
        return [
            'menu_key'  => 'required|string',
            'menu_info' => 'nullable|string',
            'items'     => 'required|array|min:1',

            'items.*.id'       => 'nullable|string',
            'items.*.name'     => 'required|string',
            'items.*.price'    => 'required|numeric|min:0',
            'items.*.image'    => 'nullable|string',
            'items.*.category' => 'nullable|string',

            'items.*.options' => 'nullable|array',
            'items.*.options.*.type'   => 'required_with:items.*.options|string|in:single,multiple',
            'items.*.options.*.values' => 'required_with:items.*.options|array|min:1',
            'items.*.options.*.values.*' => 'string',
        ];
    }
}
