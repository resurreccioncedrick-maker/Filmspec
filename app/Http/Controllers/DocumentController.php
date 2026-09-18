<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mutating actions for Documents (Part 18). Listing happens in the controllers that host the
 * card (BookingDetailController, ClientDocumentsController) since access there already follows
 * the parent record's own permission — this controller re-derives that same access check
 * itself rather than trusting the route middleware alone, because "can see bookings in
 * general" isn't the same claim as "can see THIS booking's files".
 *
 * Deliberately NOT the ImageUpload pattern (public webroot, no auth): these files can carry
 * PII, so they live on the private `local` disk and only ever leave it through download().
 */
class DocumentController extends Controller
{
    private const MAX_SIZE_KB = 10240; // 10MB, matches the raised php.ini ceiling

    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg', 'image/jpg', 'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function store(Request $request)
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $bookingId = (int) $request->input('booking_id', 0) ?: null;
        $clientId = (int) $request->input('client_id', 0) ?: null;

        if (($bookingId && $clientId) || (! $bookingId && ! $clientId)) {
            return back()->with('doc_msg', ['type' => 'danger', 'text' => 'A document must belong to exactly one booking or client.']);
        }

        if ($bookingId && ! $this->canAccessBooking($role, $bookingId)) {
            abort(403);
        }
        if ($clientId && ! $this->canAccessClients($role)) {
            abort(403);
        }

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return back()->with('doc_msg', ['type' => 'danger', 'text' => 'Choose a file to upload.']);
        }
        if ($file->getSize() > self::MAX_SIZE_KB * 1024) {
            return back()->with('doc_msg', ['type' => 'danger', 'text' => 'File too large. Max 10MB.']);
        }
        // Content-sniffed, not extension-trusted — an .exe renamed .pdf must still be
        // rejected, the same discipline ImageUpload already applies to photos.
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            return back()->with('doc_msg', ['type' => 'danger', 'text' => 'Only PDF, Word, or image files are allowed.']);
        }

        $category = $request->input('category', 'other');
        if (! in_array($category, ['id', 'permit', 'other'], true)) {
            $category = 'other';
        }

        $ext = strtolower($file->getClientOriginalExtension()) ?: 'bin';
        $storedName = Str::random(40) . '.' . $ext;

        Storage::disk('local')->putFileAs('documents', $file, $storedName);

        $id = DB::table('documents')->insertGetId([
            'booking_id' => $bookingId, 'client_id' => $clientId,
            'category' => $category,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'note' => trim((string) $request->input('note', '')) ?: null,
            'uploaded_by' => $user->user_id,
            'created_at' => now(), 'updated_at' => now(),
        ], 'document_id');

        $module = $bookingId ? 'booking' : 'client';
        $recordId = $bookingId ?: $clientId;
        ActivityLog::record($user->user_id, 'create', $module, 'Uploaded document "' . $file->getClientOriginalName() . '".', $recordId);

        return back()->with('doc_msg', ['type' => 'success', 'text' => 'Document uploaded.']);
    }

    public function download(Request $request, int $id): StreamedResponse
    {
        $doc = DB::table('documents')->where('document_id', $id)->first();
        // 404, not 403 — a user without access shouldn't learn the document even exists.
        abort_unless($doc, 404);

        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $allowed = $role === 'client'
            ? ((bool) $doc->booking_id && $this->canClientAccessBooking($user->user_id, (int) $doc->booking_id))
            : ($doc->booking_id ? $this->canAccessBooking($role, (int) $doc->booking_id) : $this->canAccessClients($role));
        abort_unless($allowed, 404);

        abort_unless(Storage::disk('local')->exists('documents/' . $doc->stored_name), 404);

        return Storage::disk('local')->download('documents/' . $doc->stored_name, $doc->original_name);
    }

    public function destroy(Request $request, int $id)
    {
        $role = $request->user()->role->role_name ?? '';
        abort_unless(in_array($role, config('filmspec.manage_roles'), true), 403);

        $doc = DB::table('documents')->where('document_id', $id)->first();
        if (! $doc) {
            return back()->with('doc_msg', ['type' => 'danger', 'text' => 'Document not found.']);
        }

        Storage::disk('local')->delete('documents/' . $doc->stored_name);
        DB::table('documents')->where('document_id', $id)->delete();

        $module = $doc->booking_id ? 'booking' : 'client';
        $recordId = $doc->booking_id ?: $doc->client_id;
        ActivityLog::record($request->user()->user_id, 'delete', $module, 'Deleted document "' . $doc->original_name . '".', $recordId);

        return back()->with('doc_msg', ['type' => 'success', 'text' => 'Document deleted.']);
    }

    private function canAccessBooking(string $role, int $bookingId): bool
    {
        if (! in_array('bookings', config("filmspec.role_permissions.$role", []), true)) {
            return false;
        }

        return DB::table('bookings')->where('booking_id', $bookingId)->exists();
    }

    private function canAccessClients(string $role): bool
    {
        return in_array('clients', config("filmspec.role_permissions.$role", []), true);
    }

    private function canClientAccessBooking(int $uid, int $bookingId): bool
    {
        return DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $bookingId)->where('c.user_id', $uid)
            ->exists();
    }
}
