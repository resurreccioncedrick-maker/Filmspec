<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\PageActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $canPost = in_array($role, config('filmspec.all_staff'), true);

        $msg = null;
        if ($request->isMethod('post') && $canPost) {
            $msg = $this->handleAction($request, $user->user_id);
        }

        $faqs = DB::table('faqs')
            ->orderBy('category')->orderBy('sort_order')->orderBy('faq_id')
            ->get()
            ->groupBy('category');

        return view('faqs', [
            'msg' => $msg,
            'canPost' => $canPost,
            'faqs' => $faqs,
            'pageActivity' => PageActivity::forModule('faqs'),
            'activityModule' => 'faqs',
            'accessLog' => PageActivity::recentAccess(),
        ]);
    }

    private function handleAction(Request $request, int $uid): ?array
    {
        $action = $request->input('action', '');

        if ($action === 'add_faq') {
            $question = trim((string) $request->input('question', ''));
            $answer = trim((string) $request->input('answer', ''));
            if ($question === '' || $answer === '') {
                return ['type' => 'danger', 'text' => 'Question and answer are required.'];
            }

            $id = DB::table('faqs')->insertGetId([
                'question' => $question,
                'answer' => $answer,
                'category' => trim((string) $request->input('category', '')) ?: 'General',
                'sort_order' => (int) $request->input('sort_order', 0),
                'is_active' => (bool) $request->input('is_active', true),
                'created_at' => now(),
                'updated_at' => now(),
            ], 'faq_id');

            ActivityLog::record($uid, 'create', 'faqs', "Added FAQ \"$question\".", $id);

            return ['type' => 'success', 'text' => 'FAQ added.'];
        }

        if ($action === 'edit_faq') {
            $id = (int) $request->input('faq_id');
            $faq = DB::table('faqs')->where('faq_id', $id)->first();
            if (! $faq) {
                return ['type' => 'danger', 'text' => 'FAQ not found.'];
            }

            $question = trim((string) $request->input('question', ''));
            $answer = trim((string) $request->input('answer', ''));
            if ($question === '' || $answer === '') {
                return ['type' => 'danger', 'text' => 'Question and answer are required.'];
            }

            DB::table('faqs')->where('faq_id', $id)->update([
                'question' => $question,
                'answer' => $answer,
                'category' => trim((string) $request->input('category', '')) ?: 'General',
                'sort_order' => (int) $request->input('sort_order', 0),
                'is_active' => (bool) $request->input('is_active', true),
                'updated_at' => now(),
            ]);

            ActivityLog::record($uid, 'update', 'faqs', "Edited FAQ \"$question\".", $id);

            return ['type' => 'success', 'text' => 'FAQ updated.'];
        }

        if ($action === 'delete_faq') {
            $id = (int) $request->input('faq_id');
            $faq = DB::table('faqs')->where('faq_id', $id)->first();
            if (! $faq) {
                return ['type' => 'danger', 'text' => 'FAQ not found.'];
            }

            DB::table('faqs')->where('faq_id', $id)->delete();
            ActivityLog::record($uid, 'delete', 'faqs', "Deleted FAQ \"{$faq->question}\".", $id);

            return ['type' => 'success', 'text' => 'FAQ deleted.'];
        }

        return null;
    }
}
