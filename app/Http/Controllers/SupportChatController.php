<?php

namespace App\Http\Controllers;

use App\Support\ChatAttachmentUpload;
use App\Support\MessageReadTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Staff-side inbox for the homepage support chat (client_support_messages) — the widget in
 * HomeController::index() has always let clients send messages, but nothing on the staff side
 * ever read them back. This closes that gap, following the same insert/attachment/is_internal
 * shape HomeController and BookingDetailController::postComment() already use.
 */
class SupportChatController extends Controller
{
    public function index()
    {
        return view('support-chat', array_merge(
            ['activeClientId' => null, 'client' => null, 'messages' => collect()],
            $this->threadList()
        ));
    }

    public function show(Request $request, int $clientId)
    {
        $client = DB::table('clients')->where('client_id', $clientId)->first();
        abort_unless($client, 404);

        $uid = Auth::id();
        $flash = null;

        if ($request->isMethod('post') && $request->input('action') === 'post_message') {
            $body = trim((string) $request->input('body', ''));
            $attach = ChatAttachmentUpload::handle($request->file('attachment'));

            if ($attach['error']) {
                $flash = ['type' => 'danger', 'text' => $attach['error']];
            } elseif ($body === '' && ! $attach['success']) {
                $flash = ['type' => 'danger', 'text' => 'Message cannot be empty.'];
            } else {
                DB::table('client_support_messages')->insert([
                    'client_id' => $clientId, 'user_id' => $uid, 'body' => $body,
                    'attachment_path' => $attach['success'] ? $attach['path'] : null,
                    'attachment_name' => $attach['success'] ? $attach['name'] : null,
                    'attachment_mime' => $attach['success'] ? $attach['mime'] : null,
                    'attachment_size' => $attach['success'] ? $attach['size'] : null,
                    'is_internal' => $request->boolean('is_internal'),
                    'created_at' => now(),
                ]);
            }

            if ($flash) {
                $request->session()->flash('sc_flash', $flash);
            }

            return redirect()->route('support-chat.show', $clientId);
        }

        $messages = DB::table('client_support_messages as m')
            ->join('users as u', 'm.user_id', '=', 'u.user_id')
            ->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->where('m.client_id', $clientId)
            ->orderBy('m.created_at')
            ->select('m.*', 'r.role_name as author_role', 'u.first_name', 'u.last_name')
            ->get();

        MessageReadTracker::markRead($uid, 'support', $clientId, $messages->last()->created_at ?? null);

        return view('support-chat', array_merge(
            ['activeClientId' => $clientId, 'client' => $client, 'messages' => $messages],
            $this->threadList()
        ));
    }

    /**
     * The conversation list shown in the left rail — needed on every request to this
     * controller now that index() and show() render the same split-inbox view, not just
     * on the old list-only page.
     */
    private function threadList(): array
    {
        $uid = Auth::id();

        $counts = DB::table('client_support_messages as m')
            ->join('clients as c', 'm.client_id', '=', 'c.client_id')
            ->groupBy('c.client_id', 'c.company_name', 'c.contact_person')
            ->select('c.client_id', 'c.company_name', 'c.contact_person', DB::raw('COUNT(*) as message_count'))
            ->get()
            ->keyBy('client_id');

        // Grouping the last-message preview in PHP rather than a correlated subquery —
        // simpler and plenty fast at this table's realistic size.
        $lastMessages = DB::table('client_support_messages')
            ->orderByDesc('created_at')
            ->get(['client_id', 'body', 'is_internal', 'created_at'])
            ->groupBy('client_id')
            ->map(fn ($rows) => $rows->first());

        $unreadFlags = MessageReadTracker::unreadFlags(
            $uid, 'support',
            $lastMessages->map(fn ($m, $clientId) => (object) ['thread_id' => $clientId, 'created_at' => $m->created_at])->values()
        );

        $threads = $lastMessages->keys()->map(function ($clientId) use ($counts, $lastMessages, $unreadFlags) {
            $c = $counts->get($clientId);
            $last = $lastMessages->get($clientId);

            return (object) [
                'client_id' => $clientId,
                'company_name' => $c->company_name ?? null,
                'contact_person' => $c->contact_person ?? null,
                'message_count' => $c->message_count ?? 0,
                'last_body' => $last->body ?? '',
                'last_is_internal' => (bool) ($last->is_internal ?? false),
                'last_message_at' => $last->created_at ?? null,
                'unread' => $unreadFlags[$clientId] ?? false,
            ];
        })->sortByDesc('last_message_at')->values();

        return ['threads' => $threads];
    }

    /** Lightweight polling for the staff thread view — mirrors HomeController::supportPoll(). */
    public function poll(Request $request, int $clientId)
    {
        $after = (int) $request->query('after', 0);

        $messages = DB::table('client_support_messages as m')
            ->join('users as u', 'm.user_id', '=', 'u.user_id')
            ->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->where('m.client_id', $clientId)
            ->where('m.message_id', '>', $after)
            ->orderBy('m.created_at')
            ->select('m.*', 'r.role_name as author_role', 'u.first_name', 'u.last_name')
            ->get();

        if ($messages->isNotEmpty()) {
            MessageReadTracker::markRead(Auth::id(), 'support', $clientId, $messages->last()->created_at);
        }

        return response()->json(['messages' => $messages]);
    }

    /** Staff-only attachment download — route already gated by can_access:support_chat. */
    public function attachment(int $clientId, int $messageId)
    {
        $message = DB::table('client_support_messages')
            ->where('message_id', $messageId)->where('client_id', $clientId)
            ->select('attachment_path', 'attachment_name')
            ->first();

        abort_unless($message && $message->attachment_path, 404);
        abort_unless(Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name);
    }
}
