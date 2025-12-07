<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Menu;

class MenuController extends Controller
{
    /**
     * Return a specific menu by key
     * Example: /hotel/menus/menu_cafe
     */
    public function getMenuByKey(Request $request)
    {
        $validated = $request->validate([
        'menu_key' => 'required|string'
    ]);
        $menu = Menu::where('menu_key',$validated['menu_key'])->first();

        if (!$menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }

        return response()->json($menu, 200);
    }

}
