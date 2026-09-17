<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\SupportChatRetention;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataRetentionController extends Controller
{
    public function index(Request $request): View
    {
        $msg = null;

        if ($request->isMethod('post') && $request->input('action') === 'run_now') {
            $actorId = $request->user()->user_id;
            $deleted = SupportChatRetention::prune($actorId);

            ActivityLog::record($actorId, 'delete', 'data_retention', "Ran support chat cleanup manually — $deleted message(s) removed");

            $msg = $deleted > 0
                ? ['type' => 'success', 'text' => "Cleanup complete — <strong>$deleted</strong> old support chat message(s) removed."]
                : ['type' => 'success', 'text' => 'Cleanup ran — nothing was old enough to remove.'];
        }

        return view('data-retention', [
            'msg' => $msg,
            'stats' => SupportChatRetention::stats(),
        ]);
    }
}
