<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FavoritesController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ($user->role->role_name ?? '') !== 'client') {
            return response()->json(['error' => 'Not logged in as client']);
        }

        $uid = $user->user_id;
        $action = $request->input('action', $request->query('action', ''));

        return match ($action) {
            'get' => $this->get($uid),
            'toggle' => $this->toggle($request, $uid),
            default => response()->json(['error' => 'Unknown action']),
        };
    }

    private function get(int $uid): JsonResponse
    {
        $equipmentIds = DB::table('equipment_favorites')
            ->where('user_id', $uid)
            ->pluck('equipment_id');

        return response()->json(['equipment_ids' => $equipmentIds]);
    }

    private function toggle(Request $request, int $uid): JsonResponse
    {
        $eid = (int) $request->input('equipment_id', 0);
        if (! $eid) {
            return response()->json(['error' => 'No equipment']);
        }
        if (! DB::table('equipment')->where('equipment_id', $eid)->exists()) {
            return response()->json(['error' => 'Equipment not found']);
        }

        $existing = DB::table('equipment_favorites')->where('user_id', $uid)->where('equipment_id', $eid)->value('favorite_id');
        if ($existing) {
            DB::table('equipment_favorites')->where('favorite_id', $existing)->delete();

            return response()->json(['ok' => true, 'favorited' => false]);
        }

        DB::table('equipment_favorites')->insert(['user_id' => $uid, 'equipment_id' => $eid, 'created_at' => now()]);

        return response()->json(['ok' => true, 'favorited' => true]);
    }
}
