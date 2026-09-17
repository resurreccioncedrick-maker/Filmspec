<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Support\OtpMailTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectForRole(Auth::user());
        }

        return view('auth.login', [
            'activeTab' => old('form', 'login'),
            'expiredMsg' => $request->boolean('mfa_expired')
                ? 'Your verification code expired. Please sign in again.'
                : ($request->boolean('signup_expired') ? 'Your registration code expired. Please register again.' : ''),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::with('role')->where('email', $data['email'])->first();

        if (! $user) {
            return back()->withInput()->withErrors(['login' => 'No account found with that email.']);
        }
        if (! $user->is_active) {
            return back()->withInput()->withErrors(['login' => 'Account deactivated. Contact the administrator.']);
        }
        if (($user->role->role_name ?? '') === 'client') {
            $clientStatus = \DB::table('clients')->where('user_id', $user->user_id)->value('status');
            if ($clientStatus === 'pending') {
                return back()->withInput()->withErrors(['login' => 'Your account is awaiting admin approval. We will email you once it has been reviewed.']);
            }
            if ($clientStatus === 'rejected') {
                return back()->withInput()->withErrors(['login' => 'Your account application was not approved. Contact us for details.']);
            }
        }
        if (! Hash::check($data['password'], $user->password_hash)) {
            return back()->withInput()->withErrors(['login' => 'Incorrect password.']);
        }

        $otp = $this->generateOtp();

        $request->session()->regenerate();
        $request->session()->put('mfa_pending', [
            'user_id' => $user->user_id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->role->role_name,
            'issued_at' => time(),
            '_otp' => $otp,
        ]);
        $this->storeMfaToken($user->user_id, $otp);

        return redirect()->route('verify-mfa');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'regex:/^09\d{9}$/'],
            'password' => ['required', 'string', 'min:8'],
            'password2' => ['required', 'same:password'],
            'entity_type' => ['nullable', Rule::in(['individual', 'company', 'ngo'])],
            'dpa_consent' => ['accepted'],
        ], [
            'email.unique' => 'That email is already registered.',
            'phone.regex' => 'Phone must be 11 digits starting with 09 (e.g. 09171234567).',
            'password2.same' => 'Passwords do not match.',
            'dpa_consent.accepted' => 'You must agree to the Data Privacy Policy to create an account.',
        ]);

        if ($this->passwordScore($data['password']) < 2) {
            return back()->withInput()->withErrors(['register' => 'Password is too weak. Add uppercase letters, numbers, or special characters.']);
        }

        $otp = $this->generateOtp();

        $request->session()->regenerate();
        $request->session()->put('signup_pending', [
            'first_name' => strtoupper($data['first_name']),
            'last_name' => strtoupper($data['last_name']),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
            'password_hash' => Hash::make($data['password']),
            'entity_type' => $data['entity_type'] ?? 'individual',
            'otp_hash' => hash('sha256', $otp),
            'otp_expires' => time() + 600,
            'issued_at' => time(),
            '_otp' => $otp,
        ]);

        return redirect()->route('verify-signup');
    }

    public function showVerifyMfa(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectForRole(Auth::user());
        }

        $pending = $request->session()->get('mfa_pending');
        if (! $pending) {
            return redirect()->route('login');
        }
        if (time() - ($pending['issued_at'] ?? 0) > 900) {
            $request->session()->forget(['mfa_pending', 'mfa_attempts']);
            return redirect()->route('login', ['mfa_expired' => 1]);
        }

        if (! empty($pending['_otp'])) {
            $otpToSend = $pending['_otp'];
            unset($pending['_otp']);
            $request->session()->put('mfa_pending', $pending);
            $this->sendMail($pending['email'], 'Your FilmSpec Verification Code', OtpMailTemplates::login($pending['first_name'], $otpToSend));
        }

        return view('auth.verify-mfa', [
            'pending' => $pending,
            'error' => $request->session()->get('mfa_error', ''),
        ]);
    }

    public function verifyMfa(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('mfa_pending');
        if (! $pending) {
            return redirect()->route('login');
        }

        $otp = collect(['d1', 'd2', 'd3', 'd4', 'd5', 'd6'])
            ->map(fn ($k) => trim($request->input($k, '')))
            ->implode('');

        $attempts = $request->session()->get('mfa_attempts', 0);

        if (! preg_match('/^\d{6}$/', $otp)) {
            return redirect()->route('verify-mfa')->with('mfa_error', 'Please enter the 6-digit code.');
        }
        if ($attempts >= 5) {
            $request->session()->forget(['mfa_pending', 'mfa_attempts']);
            return redirect()->route('login');
        }

        $userId = (int) $pending['user_id'];
        $token = \DB::table('mfa_tokens')
            ->where('user_id', $userId)
            ->where('used', 0)
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (! $token || ! hash_equals($token->token_hash, hash('sha256', $otp))) {
            $attempts++;
            $request->session()->put('mfa_attempts', $attempts);
            $left = 5 - $attempts;
            if ($left <= 0) {
                $request->session()->forget(['mfa_pending', 'mfa_attempts']);
                return redirect()->route('login');
            }
            $s = $left === 1 ? '' : 's';
            return redirect()->route('verify-mfa')->with('mfa_error', "Incorrect or expired code. $left attempt$s remaining.");
        }

        \DB::table('mfa_tokens')->where('id', $token->id)->update(['used' => 1]);

        $request->session()->forget(['mfa_pending', 'mfa_attempts']);
        $request->session()->regenerate();

        $user = User::with('role')->findOrFail($userId);
        Auth::login($user);
        ActivityLog::record($userId, 'login', 'auth', 'Logged in (MFA verified)');

        return $this->redirectForRole($user);
    }

    public function showVerifySignup(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectForRole(Auth::user());
        }

        $pending = $request->session()->get('signup_pending');
        if (! $pending) {
            return redirect()->route('login');
        }
        if (time() > ($pending['otp_expires'] ?? 0)) {
            $request->session()->forget(['signup_pending', 'signup_otp_attempts']);
            return redirect()->route('login', ['signup_expired' => 1]);
        }

        if (! empty($pending['_otp'])) {
            $otpToSend = $pending['_otp'];
            unset($pending['_otp']);
            $request->session()->put('signup_pending', $pending);
            $this->sendMail($pending['email'], 'Verify your FilmSpec account', OtpMailTemplates::signup($pending['first_name'], $otpToSend));
        }

        $at = strpos($pending['email'], '@');
        $maskedEmail = substr($pending['email'], 0, 3) . str_repeat('*', max(0, $at - 3)) . substr($pending['email'], $at);

        return view('auth.verify-signup', [
            'pending' => $pending,
            'maskedEmail' => $maskedEmail,
            'error' => $request->session()->get('signup_error', ''),
        ]);
    }

    public function verifySignup(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('signup_pending');
        if (! $pending) {
            return redirect()->route('login');
        }

        $otp = collect(['d1', 'd2', 'd3', 'd4', 'd5', 'd6'])
            ->map(fn ($k) => trim($request->input($k, '')))
            ->implode('');

        $attempts = $request->session()->get('signup_otp_attempts', 0);

        if (! preg_match('/^\d{6}$/', $otp)) {
            return redirect()->route('verify-signup')->with('signup_error', 'Please enter the complete 6-digit code.');
        }
        if ($attempts >= 5) {
            $request->session()->forget(['signup_pending', 'signup_otp_attempts']);
            return redirect()->route('login');
        }
        if (time() > ($pending['otp_expires'] ?? 0)) {
            $request->session()->forget(['signup_pending', 'signup_otp_attempts']);
            return redirect()->route('login', ['signup_expired' => 1]);
        }
        if (! hash_equals($pending['otp_hash'], hash('sha256', $otp))) {
            $attempts++;
            $request->session()->put('signup_otp_attempts', $attempts);
            $left = 5 - $attempts;
            if ($left <= 0) {
                $request->session()->forget(['signup_pending', 'signup_otp_attempts']);
                return redirect()->route('login');
            }
            $s = $left === 1 ? '' : 's';
            return redirect()->route('verify-signup')->with('signup_error', "Incorrect code. $left attempt$s remaining.");
        }

        if (User::where('email', $pending['email'])->exists()) {
            $request->session()->forget(['signup_pending', 'signup_otp_attempts']);
            return redirect()->route('login')->withInput(['form' => 'signup'])->withErrors(['register' => 'That email was just registered. Please use a different email.']);
        }

        $roleId = Role::where('role_name', 'client')->value('role_id') ?? 6;

        $user = User::create([
            'role_id' => $roleId,
            'first_name' => $pending['first_name'],
            'last_name' => $pending['last_name'],
            'email' => $pending['email'],
            'password_hash' => $pending['password_hash'],
            'phone' => $pending['phone'],
            'is_active' => 1,
        ]);

        Client::create([
            'user_id' => $user->user_id,
            'company_name' => '',
            'contact_person' => $pending['first_name'] . ' ' . $pending['last_name'],
            'email' => $pending['email'],
            'phone' => $pending['phone'],
            'client_type' => 'first_time',
            'is_vat_registered' => $pending['entity_type'] === 'ngo' ? 0 : 1,
        ]);
        \DB::table('clients')->where('user_id', $user->user_id)->update([
            'entity_type' => $pending['entity_type'],
            'status' => 'pending',
        ]);

        ActivityLog::record($user->user_id, 'create', 'auth', 'Client account registered — pending approval');

        $request->session()->forget(['signup_pending', 'signup_otp_attempts']);
        $request->session()->regenerate();

        return redirect()->route('login')->withInput(['form' => 'login'])->with(
            'signup_success',
            'Thanks for signing up! Your account is awaiting admin approval — we\'ll email you once it\'s been reviewed.'
        );
    }

    public function showForgotPassword(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectForRole(Auth::user());
        }

        return view('auth.forgot-password', [
            'error' => $request->session()->get('forgot_error', ''),
        ]);
    }

    public function sendResetOtp(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return redirect()->route('forgot-password')->withInput()->with('forgot_error', 'No account found with that email.');
        }
        if (! $user->is_active) {
            return redirect()->route('forgot-password')->withInput()->with('forgot_error', 'Account deactivated. Contact the administrator.');
        }

        $otp = $this->generateOtp();

        $request->session()->regenerate();
        $request->session()->put('password_reset_pending', [
            'user_id' => $user->user_id,
            'first_name' => $user->first_name,
            'email' => $user->email,
            'issued_at' => time(),
        ]);

        \DB::table('password_reset_tokens')->where('user_id', $user->user_id)->delete();
        \DB::table('password_reset_tokens')->insert([
            'user_id' => $user->user_id,
            'token_hash' => hash('sha256', $otp),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->sendMail($user->email, 'Reset your FilmSpec password', OtpMailTemplates::resetPassword($user->first_name, $otp));

        return redirect()->route('reset-password');
    }

    public function showResetPassword(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectForRole(Auth::user());
        }

        $pending = $request->session()->get('password_reset_pending');
        if (! $pending) {
            return redirect()->route('forgot-password');
        }
        if (time() - ($pending['issued_at'] ?? 0) > 900) {
            $request->session()->forget(['password_reset_pending', 'password_reset_attempts']);

            return redirect()->route('forgot-password')->with('forgot_error', 'Your reset code expired. Please request a new one.');
        }

        return view('auth.reset-password', [
            'pending' => $pending,
            'error' => $request->session()->get('reset_error', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('password_reset_pending');
        if (! $pending) {
            return redirect()->route('forgot-password');
        }

        $otp = collect(['d1', 'd2', 'd3', 'd4', 'd5', 'd6'])
            ->map(fn ($k) => trim($request->input($k, '')))
            ->implode('');

        $attempts = $request->session()->get('password_reset_attempts', 0);

        if (! preg_match('/^\d{6}$/', $otp)) {
            return redirect()->route('reset-password')->with('reset_error', 'Please enter the 6-digit code.');
        }
        if ($attempts >= 5) {
            $request->session()->forget(['password_reset_pending', 'password_reset_attempts']);

            return redirect()->route('forgot-password')->with('forgot_error', 'Too many attempts. Please request a new code.');
        }

        $userId = (int) $pending['user_id'];
        $token = \DB::table('password_reset_tokens')
            ->where('user_id', $userId)
            ->where('used', 0)
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (! $token || ! hash_equals($token->token_hash, hash('sha256', $otp))) {
            $attempts++;
            $request->session()->put('password_reset_attempts', $attempts);
            $left = 5 - $attempts;
            if ($left <= 0) {
                $request->session()->forget(['password_reset_pending', 'password_reset_attempts']);

                return redirect()->route('forgot-password')->with('forgot_error', 'Too many attempts. Please request a new code.');
            }
            $s = $left === 1 ? '' : 's';

            return redirect()->route('reset-password')->with('reset_error', "Incorrect or expired code. $left attempt$s remaining.");
        }

        $password = (string) $request->input('password', '');
        $password2 = (string) $request->input('password2', '');
        if ($password === '' || strlen($password) < 8) {
            return redirect()->route('reset-password')->with('reset_error', 'Password must be at least 8 characters.');
        }
        if ($password !== $password2) {
            return redirect()->route('reset-password')->with('reset_error', 'Passwords do not match.');
        }
        if ($this->passwordScore($password) < 2) {
            return redirect()->route('reset-password')->with('reset_error', 'Password is too weak. Add uppercase letters, numbers, or special characters.');
        }

        \DB::table('password_reset_tokens')->where('id', $token->id)->update(['used' => 1]);
        User::where('user_id', $userId)->update(['password_hash' => Hash::make($password)]);
        ActivityLog::record($userId, 'update', 'auth', 'Password reset via email verification');

        $request->session()->forget(['password_reset_pending', 'password_reset_attempts']);

        return redirect()->route('login')->with('reset_success', 'Password reset successfully. Please sign in with your new password.');
    }

    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            ActivityLog::record(Auth::id(), 'logout', 'auth', 'User logged out');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectForRole(User $user): RedirectResponse
    {
        $role = $user->role->role_name ?? '';

        if ($role === 'client') {
            return redirect('/');
        }
        if ($role === 'crew') {
            return redirect()->route('crew-portal');
        }

        return redirect()->route('dashboard');
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function storeMfaToken(int $userId, string $otp): void
    {
        \DB::table('mfa_tokens')->where('user_id', $userId)->delete();
        \DB::table('mfa_tokens')->insert([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $otp),
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    private function passwordScore(string $pw): int
    {
        $score = 0;
        if (strlen($pw) >= 8) $score++;
        if (strlen($pw) >= 12) $score++;
        if (preg_match('/[A-Z]/', $pw)) $score++;
        if (preg_match('/[0-9]/', $pw)) $score++;
        if (preg_match('/[^A-Za-z0-9]/', $pw)) $score++;

        return $score;
    }

    private function sendMail(string $to, string $subject, string $html): void
    {
        try {
            Mail::html($html, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OTP mail failed: ' . $e->getMessage());
        }
    }
}
