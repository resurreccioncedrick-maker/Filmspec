<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FilmSpec — Forgot Password</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#ECF2FA;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px 16px 64px;color:#0B1A33}
.card{width:100%;max-width:420px;background:#fff;border:1px solid #CFDFEE;border-radius:14px;padding:36px 34px 32px;box-shadow:0 4px 16px rgba(0,30,80,.08),0 1px 4px rgba(0,30,80,.05)}
.card-head{display:flex;align-items:center;gap:10px;margin-bottom:4px}
.card-head img{height:26px;object-fit:contain}
.card-brand{font-family:'Bebas Neue',sans-serif;font-size:21px;letter-spacing:2px;color:#0060C7}
.sub{font-size:12px;color:#385270;margin-bottom:20px;letter-spacing:.3px}
h2{font-size:16px;font-weight:700;color:#0B1A33;margin-bottom:6px}
.desc{font-size:13px;color:#385270;margin-bottom:20px;line-height:1.7}
.fg{margin-bottom:16px}
.fg label{display:block;font-size:12.5px;font-weight:600;color:#0B1A33;margin-bottom:6px}
.fg input{width:100%;padding:11px 13px;border-radius:8px;border:1.5px solid #CFDFEE;background:#F0F6FC;color:#0B1A33;font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:border-color .15s,box-shadow .15s,background .15s}
.fg input:focus{border-color:#0060C7;box-shadow:0 0 0 3px rgba(0,96,199,.10);background:#fff}
.sbtn{width:100%;padding:12px;border-radius:9px;border:none;cursor:pointer;font-size:13.5px;font-weight:700;font-family:'DM Sans',sans-serif;background:#0060C7;color:#fff;transition:all .18s;letter-spacing:.01em;box-shadow:0 2px 8px rgba(0,96,199,.18)}
.sbtn:hover{background:#004EA3;box-shadow:0 4px 14px rgba(0,96,199,.26);transform:translateY(-1px)}
.err{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;border-radius:8px;padding:9px 12px;font-size:12.5px;margin-bottom:14px;line-height:1.5}
.back{display:block;text-align:center;margin-top:14px;font-size:12px;color:#7695B0;text-decoration:none;transition:color .15s}
.back:hover{color:#0060C7}
</style>
</head>
<body>
<div class="card">
  <div class="card-head">
    <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec">
    <span class="card-brand">FilmSpec</span>
  </div>
  <div class="sub">Account recovery</div>

  <h2>Forgot your password?</h2>
  <div class="desc">Enter the email address on your account and we'll send you a 6-digit code to reset your password.</div>

  @if ($error)
  <div class="err">{{ $error }}</div>
  @endif

  <form method="POST" action="{{ route('forgot-password.store') }}">
    @csrf
    <div class="fg">
      <label>Email Address</label>
      <input type="email" name="email" value="{{ old('email') }}" placeholder="your@email.com" required autofocus autocomplete="email">
    </div>
    <button type="submit" class="sbtn">Send Reset Code &rarr;</button>
  </form>

  <a href="{{ route('login') }}" class="back">&larr; Back to Sign In</a>
</div>

<footer style="position:fixed;bottom:0;left:0;width:100%;padding:9px 20px;text-align:center;color:#7695B0;font-size:11px;font-family:'DM Sans',sans-serif;letter-spacing:.3px;z-index:1">
  &copy; {{ date('Y') }} FilmSpec &mdash; Integrated Film Operations Platform
</footer>
</body>
</html>
