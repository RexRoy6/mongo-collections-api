<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Menu;
use Illuminate\Support\Facades\Log;
use App\Helpers\BusinessHelper;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    /**
     * Return a specific menu by key
     * Example: /hotel/menus/menu_cafe
     */
    public function getMenuByKey(Request $request)
    {
        try {

            $menu = Menu::forCurrentBusiness()
                ->where('is_active', true)
                ->first();

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

    public function createMenu(Request $request)
{
    try {
        $validated = $request->validate([
            'menu_key'  => 'required|string',
            'menu_info' => 'nullable|string',
            'items'     => 'required|array|min:1',
            'items.*.name'     => 'required|string',
            'items.*.price'    => 'required|numeric|min:0',
            'items.*.category' => 'nullable|string',
            'items.*.options'  => 'nullable|array',
        ]);

        $businessUuid = BusinessHelper::uuid();

        return DB::transaction(function () use ($validated, $businessUuid) {

            // 🔍 Get latest menu version
            $latestMenu = Menu::where('business_uuid', $businessUuid)
                ->where('menu_key', $validated['menu_key'])
                ->orderByDesc('version')
                ->first();

            $nextVersion = $latestMenu ? $latestMenu->version + 1 : 1;

            // 🔒 Deactivate previous active menu
            Menu::where('business_uuid', $businessUuid)
                ->where('menu_key', $validated['menu_key'])
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // ✅ Create new active menu
            $menu = Menu::create([
                'business_uuid' => $businessUuid,
                'menu_key'      => $validated['menu_key'],
                'menu_info'     => $validated['menu_info'] ?? '',
                'items'         => $validated['items'],
                'version'       => $nextVersion,
                'is_active'     => true,
            ]);

            return response()->json($menu, 201);
        });

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Invalid menu data',
            'errors'  => $e->errors(),
        ], 422);
    }
}

public function updateMenu(Request $request, $menuKey)
{
    $oldMenu = Menu::forCurrentBusiness()
        ->where('menu_key', $menuKey)
        ->where('is_active', true)
        ->firstOrFail();

    if ($oldMenu->is_active) {
    return response()->json([
        'message' => 'Active menus cannot be edited. Create a new version instead.'
    ], 409);
}
    // deactivate old
    $oldMenu->update(['is_active' => false]);

    // create new version
    $newMenu = Menu::create([
        'menu_key'  => $oldMenu->menu_key,
        'menu_info' => $request->menu_info ?? $oldMenu->menu_info,
        'items'     => $request->items,
        'version'   => $oldMenu->version + 1,
        'is_active' => true,
        'business_uuid' => $oldMenu->business_uuid
    ]);

    return response()->json($newMenu);
}



}
