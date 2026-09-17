<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        $uid = $request->user()->user_id;
        $msg = null;

        if ($request->isMethod('post')) {
            $msg = $this->handleAction($request, $uid);
        }

        $user = DB::table('users as u')
            ->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->where('u.user_id', $uid)
            ->select('u.*', 'r.role_name')
            ->first();

        return view('profile', ['msg' => $msg, 'user' => $user]);
    }

    private function handleAction(Request $request, int $uid): ?array
    {
        $action = $request->input('action', '');

        if ($action === 'update_profile') {
            $phone = trim($request->input('phone', ''));
            if ($phone !== '' && ! preg_match('/^09\d{9}$/', $phone)) {
                return ['type' => 'danger', 'text' => 'Phone must be 11 digits starting with 09 (e.g. 09171234567).'];
            }

            $fn = strtoupper(trim($request->input('first_name', '')));
            $ln = strtoupper(trim($request->input('last_name', '')));

            DB::table('users')->where('user_id', $uid)->update([
                'first_name' => $fn, 'last_name' => $ln, 'phone' => $phone, 'updated_at' => now(),
            ]);

            return ['type' => 'success', 'text' => 'Profile updated.'];
        }

        if ($action === 'change_password') {
            $curr = $request->input('current_password', '');
            $new = $request->input('new_password', '');
            $conf = $request->input('confirm_password', '');

            $hash = DB::table('users')->where('user_id', $uid)->value('password_hash');
            if (! Hash::check($curr, $hash)) {
                return ['type' => 'danger', 'text' => 'Current password is incorrect.'];
            }
            if (strlen($new) < 8) {
                return ['type' => 'danger', 'text' => 'New password must be at least 8 characters.'];
            }
            if ($new !== $conf) {
                return ['type' => 'danger', 'text' => 'Passwords do not match.'];
            }

            DB::table('users')->where('user_id', $uid)->update([
                'password_hash' => Hash::make($new), 'updated_at' => now(),
            ]);

            return ['type' => 'success', 'text' => 'Password changed successfully.'];
        }

        return null;
    }
}
