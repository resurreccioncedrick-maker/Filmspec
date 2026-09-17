<?php

namespace App\Support;

/**
 * Ported as-is from core/mailer.php (buildOtpEmail) and verify_signup.php's
 * inline signup template so the emails look identical to the legacy app.
 */
class OtpMailTemplates
{
    public static function login(string $name, string $otp): string
    {
        return '<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
  body{font-family:"DM Sans",Arial,sans-serif;background:#0a0e1a;margin:0;padding:30px}
  .wrap{max-width:480px;margin:0 auto;background:#0f1623;border-radius:12px;overflow:hidden;border:1px solid #1e2d4a}
  .hdr{background:linear-gradient(135deg,#0d1628,#0a0e1a);padding:28px 32px;border-bottom:1px solid #1e2d4a}
  .logo{font-family:Georgia,serif;font-size:28px;letter-spacing:3px;color:#0060C7;font-weight:bold}
  .body{padding:30px 32px}
  h2{color:#e8edf5;font-size:18px;margin:0 0 12px}
  p{color:#7695B0;font-size:14px;line-height:1.7;margin:0 0 20px}
  .otp-box{background:#151d2e;border:2px solid #0060C7;border-radius:10px;padding:20px;text-align:center;margin:20px 0}
  .otp{font-family:"JetBrains Mono",monospace,Courier;font-size:40px;letter-spacing:10px;color:#0060C7;font-weight:bold}
  .exp{font-size:12px;color:#4A6A8A;margin-top:8px}
  .footer{background:#070e1a;padding:16px 32px;border-top:1px solid #1e2d4a;font-size:11px;color:#4A6A8A;text-align:center}
</style></head>
<body>
<div class="wrap">
  <div class="hdr"><div class="logo">FILMSPEC</div></div>
  <div class="body">
    <h2>Your Verification Code</h2>
    <p>Hi ' . htmlspecialchars($name) . ', use the code below to complete your sign-in. Do not share this code with anyone.</p>
    <div class="otp-box">
      <div class="otp">' . $otp . '</div>
      <div class="exp">Expires in 10 minutes</div>
    </div>
    <p>If you did not attempt to sign in to FilmSpec, please ignore this email and contact your administrator.</p>
  </div>
  <div class="footer">&copy; ' . date('Y') . ' FilmSpec &nbsp;|&nbsp; Integrated Film Operations Platform</div>
</div>
</body></html>';
    }

    public static function resetPassword(string $name, string $otp): string
    {
        return '<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
  body{font-family:"DM Sans",Arial,sans-serif;background:#0a0e1a;margin:0;padding:30px}
  .wrap{max-width:480px;margin:0 auto;background:#0f1623;border-radius:12px;overflow:hidden;border:1px solid #1e2d4a}
  .hdr{background:linear-gradient(135deg,#0d1628,#0a0e1a);padding:28px 32px;border-bottom:1px solid #1e2d4a}
  .logo{font-family:Georgia,serif;font-size:28px;letter-spacing:3px;color:#0060C7;font-weight:bold}
  .body{padding:30px 32px}
  h2{color:#e8edf5;font-size:18px;margin:0 0 12px}
  p{color:#7695B0;font-size:14px;line-height:1.7;margin:0 0 20px}
  .otp-box{background:#151d2e;border:2px solid #0060C7;border-radius:10px;padding:20px;text-align:center;margin:20px 0}
  .otp{font-family:"JetBrains Mono",monospace,Courier;font-size:40px;letter-spacing:10px;color:#0060C7;font-weight:bold}
  .exp{font-size:12px;color:#4A6A8A;margin-top:8px}
  .footer{background:#070e1a;padding:16px 32px;border-top:1px solid #1e2d4a;font-size:11px;color:#4A6A8A;text-align:center}
</style></head>
<body>
<div class="wrap">
  <div class="hdr"><div class="logo">FILMSPEC</div></div>
  <div class="body">
    <h2>Reset Your Password</h2>
    <p>Hi ' . htmlspecialchars($name) . ', use the code below to reset your FilmSpec password. Do not share this code with anyone.</p>
    <div class="otp-box">
      <div class="otp">' . $otp . '</div>
      <div class="exp">Expires in 10 minutes</div>
    </div>
    <p>If you did not request a password reset, please ignore this email — your password will not be changed.</p>
  </div>
  <div class="footer">&copy; ' . date('Y') . ' FilmSpec &nbsp;|&nbsp; Integrated Film Operations Platform</div>
</div>
</body></html>';
    }

    public static function signup(string $name, string $otp): string
    {
        return '<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
  body{font-family:"DM Sans",Arial,sans-serif;background:#f8fafc;margin:0;padding:30px}
  .wrap{max-width:480px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 4px 24px rgba(0,0,0,.08)}
  .hdr{background:linear-gradient(135deg,#003d99,#0060C7);padding:28px 32px}
  .logo{font-family:Georgia,serif;font-size:28px;letter-spacing:3px;color:#fff;font-weight:bold}
  .body{padding:30px 32px}
  h2{color:#1e293b;font-size:18px;margin:0 0 12px}
  p{color:#64748b;font-size:14px;line-height:1.7;margin:0 0 20px}
  .otp-box{background:#f0f7ff;border:2px solid #0060C7;border-radius:10px;padding:20px;text-align:center;margin:20px 0}
  .otp{font-family:"JetBrains Mono",monospace,Courier;font-size:40px;letter-spacing:10px;color:#0060C7;font-weight:bold}
  .exp{font-size:12px;color:#94a3b8;margin-top:8px}
  .footer{background:#f8fafc;padding:16px 32px;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8;text-align:center}
</style></head>
<body><div class="wrap">
  <div class="hdr"><div class="logo">FILMSPEC</div></div>
  <div class="body">
    <h2>Verify Your Email Address</h2>
    <p>Hi ' . htmlspecialchars($name) . ', you\'re almost there! Enter the code below to complete your FilmSpec account registration.</p>
    <div class="otp-box">
      <div class="otp">' . $otp . '</div>
      <div class="exp">Expires in 10 minutes &nbsp;&middot;&nbsp; Do not share this code</div>
    </div>
    <p>If you did not request this, you can safely ignore this email.</p>
  </div>
  <div class="footer">&copy; ' . date('Y') . ' FilmSpec &nbsp;|&nbsp; Integrated Film Operations Platform</div>
</div></body></html>';
    }

    public static function clientApproved(string $name): string
    {
        return '<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
  body{font-family:"DM Sans",Arial,sans-serif;background:#f8fafc;margin:0;padding:30px}
  .wrap{max-width:480px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 4px 24px rgba(0,0,0,.08)}
  .hdr{background:linear-gradient(135deg,#003d99,#0060C7);padding:28px 32px}
  .logo{font-family:Georgia,serif;font-size:28px;letter-spacing:3px;color:#fff;font-weight:bold}
  .body{padding:30px 32px}
  h2{color:#1e293b;font-size:18px;margin:0 0 12px}
  p{color:#64748b;font-size:14px;line-height:1.7;margin:0 0 20px}
  .badge{background:#f0fdf4;border:2px solid #16a34a;border-radius:10px;padding:16px 20px;text-align:center;margin:20px 0;color:#15803d;font-weight:bold;font-size:15px}
  .footer{background:#f8fafc;padding:16px 32px;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8;text-align:center}
</style></head>
<body><div class="wrap">
  <div class="hdr"><div class="logo">FILMSPEC</div></div>
  <div class="body">
    <h2>Your Account is Approved!</h2>
    <p>Hi ' . htmlspecialchars($name) . ', good news — your FilmSpec account has been reviewed and approved.</p>
    <div class="badge">&#10003; You can now sign in and start booking equipment.</div>
    <p>If you have any questions, reach out to us through the app once you\'re signed in.</p>
  </div>
  <div class="footer">&copy; ' . date('Y') . ' FilmSpec &nbsp;|&nbsp; Integrated Film Operations Platform</div>
</div></body></html>';
    }

    public static function clientRejected(string $name, string $reason): string
    {
        return '<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
  body{font-family:"DM Sans",Arial,sans-serif;background:#f8fafc;margin:0;padding:30px}
  .wrap{max-width:480px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 4px 24px rgba(0,0,0,.08)}
  .hdr{background:linear-gradient(135deg,#003d99,#0060C7);padding:28px 32px}
  .logo{font-family:Georgia,serif;font-size:28px;letter-spacing:3px;color:#fff;font-weight:bold}
  .body{padding:30px 32px}
  h2{color:#1e293b;font-size:18px;margin:0 0 12px}
  p{color:#64748b;font-size:14px;line-height:1.7;margin:0 0 20px}
  .reason-box{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:10px;padding:16px 18px;margin:20px 0;color:#7f1d1d;font-size:13.5px;line-height:1.6}
  .reason-box strong{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#b91c1c;margin-bottom:6px}
  .footer{background:#f8fafc;padding:16px 32px;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8;text-align:center}
</style></head>
<body><div class="wrap">
  <div class="hdr"><div class="logo">FILMSPEC</div></div>
  <div class="body">
    <h2>Account Application Update</h2>
    <p>Hi ' . htmlspecialchars($name) . ', we\'ve reviewed your FilmSpec account application and are unable to approve it at this time.</p>
    <div class="reason-box"><strong>Reason</strong>' . nl2br(htmlspecialchars($reason)) . '</div>
    <p>If you believe this was a mistake or would like to provide more information, please contact us directly.</p>
  </div>
  <div class="footer">&copy; ' . date('Y') . ' FilmSpec &nbsp;|&nbsp; Integrated Film Operations Platform</div>
</div></body></html>';
    }
}
