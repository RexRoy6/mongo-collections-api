<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Menu;
use Illuminate\Support\Facades\Log;
use App\Helpers\BusinessHelper;

class MenuController extends Controller
{
    /**
     * Return a specific menu by key
     * Example: /hotel/menus/menu_cafe
     */
    public function getMenuByKey(Request $request)
    {
    try{ 
        $validated = $request->validate([
        'menu_key' => 'required|string'
    ]);
        $menu = Menu::forCurrentBusiness()
        ->where('menu_key',$validated['menu_key'])->first();

        if (!$menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }

        return response()->json($menu, 200);
    

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error($e->getMessage());
        return response()->json([
            'error' => 'Bad Request',
            'message' => 'Missing or invalid parameters',
            'errors' => $e->errors()
        ], 400);
    }
}

}
