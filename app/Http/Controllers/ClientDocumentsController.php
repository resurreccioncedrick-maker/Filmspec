<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * A single client's documents (Part 18). Not a general client-detail page — this app has none
 * — just the one purpose, reached from a "Documents" button on the clients list.
 */
class ClientDocumentsController extends Controller
{
    public function show(Request $request, int $id): View
    {
        $client = DB::table('clients')->where('client_id', $id)->first();
        abort_unless($client, 404);

        $documents = DB::table('documents as d')
            ->leftJoin('users as u', 'd.uploaded_by', '=', 'u.user_id')
            ->leftJoin('users as su', 'd.signed_by', '=', 'su.user_id')
            ->where('d.client_id', $id)
            ->orderByDesc('d.created_at')
            ->select('d.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS uploaded_by_name"), DB::raw("CONCAT(su.first_name,' ',su.last_name) AS signed_by_name"))
            ->get();

        $role = $request->user()->role->role_name ?? '';

        return view('client-documents', [
            'client' => $client, 'documents' => $documents,
            'canManage' => in_array($role, config('filmspec.manage_roles'), true),
        ]);
    }
}
