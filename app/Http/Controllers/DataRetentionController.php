<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\ClientErasure;
use App\Support\SupportChatRetention;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DataRetentionController extends Controller
{
    public function index(Request $request): View
    {
        $msg = null;
        $actorId = $request->user()->user_id;

        if ($request->isMethod('post') && $request->input('action') === 'run_now') {
            $deleted = SupportChatRetention::prune($actorId);

            ActivityLog::record($actorId, 'delete', 'data_retention', "Ran support chat cleanup manually — $deleted message(s) removed");

            $msg = $deleted > 0
                ? ['type' => 'success', 'text' => "Cleanup complete — <strong>$deleted</strong> old support chat message(s) removed."]
                : ['type' => 'success', 'text' => 'Cleanup ran — nothing was old enough to remove.'];
        }

        if ($request->isMethod('post') && $request->input('action') === 'process_erasure') {
            $reqId = (int) $request->input('request_id');
            $decision = $request->input('decision');
            $notes = trim((string) $request->input('staff_notes', '')) ?: null;
            $erasureReq = DB::table('data_erasure_requests')->where('request_id', $reqId)->where('status', 'pending')->first();

            if (! $erasureReq) {
                $msg = ['type' => 'danger', 'text' => 'That request is no longer pending.'];
            } elseif ($decision === 'approve') {
                ClientErasure::anonymize($erasureReq->client_id, $actorId, $notes);
                ActivityLog::record($actorId, 'delete', 'data_retention', "Anonymized client #{$erasureReq->client_id}'s personal data (erasure request #$reqId)", $erasureReq->client_id);
                $msg = ['type' => 'success', 'text' => 'Client data anonymized and the account deactivated.'];
            } elseif ($decision === 'reject') {
                DB::table('data_erasure_requests')->where('request_id', $reqId)->update([
                    'status' => 'rejected', 'processed_by' => $actorId, 'processed_at' => now(), 'staff_notes' => $notes, 'updated_at' => now(),
                ]);
                ActivityLog::record($actorId, 'update', 'data_retention', "Rejected erasure request #$reqId for client #{$erasureReq->client_id}", $erasureReq->client_id);
                $msg = ['type' => 'success', 'text' => 'Erasure request rejected.'];
            }
        }

        return view('data-retention', [
            'msg' => $msg,
            'stats' => SupportChatRetention::stats(),
            'erasureRequests' => ClientErasure::pendingRequests(),
        ]);
    }
}
