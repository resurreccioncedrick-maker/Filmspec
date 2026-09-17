<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FilmSpec — Verify Identity</title>
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
.desc strong{color:#0B1A33}
.step-badge{display:inline-flex;align-items:center;gap:6px;background:#E5F0FF;border:1px solid rgba(0,96,199,.18);color:#0060C7;border-radius:20px;padding:4px 12px;font-size:11px;font-weight:600;letter-spacing:.4px;margin-bottom:16px}
.step-badge svg{width:12px;height:12px}
.otp-row{display:flex;gap:8px;justify-content:center;margin-bottom:20px}
.otp-row input{width:48px;height:56px;border-radius:8px;border:1.5px solid #CFDFEE;background:#F0F6FC;color:#0B1A33;font-family:'JetBrains Mono',monospace;font-size:24px;text-align:center;outline:none;transition:border-color .15s,box-shadow .15s,background .15s}
.otp-row input:focus{border-color:#0060C7;box-shadow:0 0 0 3px rgba(0,96,199,.10);background:#fff}
.sbtn{width:100%;padding:12px;border-radius:9px;border:none;cursor:pointer;font-size:13.5px;font-weight:700;font-family:'DM Sans',sans-serif;background:#0060C7;color:#fff;transition:all .18s;letter-spacing:.01em;box-shadow:0 2px 8px rgba(0,96,199,.18)}
.sbtn:hover{background:#004EA3;box-shadow:0 4px 14px rgba(0,96,199,.26);transform:translateY(-1px)}
.sbtn:disabled{background:#CFDFEE;color:#7695B0;cursor:not-allowed;transform:none;box-shadow:none}
.err{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;border-radius:8px;padding:9px 12px;font-size:12.5px;margin-bottom:14px;line-height:1.5}
.resend-row{text-align:center;margin-top:14px;font-size:12.5px;color:#7695B0}
.resend-row a{color:#0060C7;text-decoration:none;cursor:pointer;font-weight:600}
.resend-row a:hover{text-decoration:underline}
.back{display:block;text-align:center;margin-top:12px;font-size:12px;color:#7695B0;text-decoration:none;transition:color .15s}
.back:hover{color:#0060C7}
@media(max-width:400px){
  .card{padding:28px 16px 24px}
  .otp-row{gap:5px}
  .otp-row input{width:36px;height:48px;font-size:19px}
}
</style>
</head>
<body>
<div class="card">
  <div class="card-head">
    <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec">
    <span class="card-brand">FilmSpec</span>
  </div>
  <div class="sub">Two-step verification</div>

  <div class="step-badge">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    Identity Verification
  </div>

  <h2>Check your email</h2>
  @php
    $at = strpos($pending['email'], '@');
    $masked = substr($pending['email'], 0, 3) . str_repeat('*', max(0, $at - 3)) . substr($pending['email'], $at);
  @endphp
  <div class="desc">
    We sent a 6-digit code to<br>
    <strong>{{ $masked }}</strong>.
    Enter it below to sign in.
  </div>

  @if ($error)
  <div class="err">{{ $error }}</div>
  @endif

  <form method="POST" action="{{ route('verify-mfa.store') }}" id="mfaForm" autocomplete="off">
    @csrf
    <div class="otp-row">
      <input type="text" name="d1" id="d1" maxlength="1" inputmode="numeric" pattern="\d" required autofocus>
      <input type="text" name="d2" id="d2" maxlength="1" inputmode="numeric" pattern="\d" required>
      <input type="text" name="d3" id="d3" maxlength="1" inputmode="numeric" pattern="\d" required>
      <input type="text" name="d4" id="d4" maxlength="1" inputmode="numeric" pattern="\d" required>
      <input type="text" name="d5" id="d5" maxlength="1" inputmode="numeric" pattern="\d" required>
      <input type="text" name="d6" id="d6" maxlength="1" inputmode="numeric" pattern="\d" required>
    </div>
    <button type="submit" class="sbtn" id="verifyBtn">Verify &amp; Sign In</button>
  </form>

  <div class="resend-row">
    Didn't receive it? <a href="{{ route('login') }}">Go back and try again</a>
  </div>
  <a href="{{ route('login') }}" class="back">&larr; Back to Sign In</a>
</div>

<script>
const inputs = Array.from(document.querySelectorAll('.otp-row input'));

inputs.forEach((el, i) => {
  el.addEventListener('input', () => {
    el.value = el.value.replace(/\D/g, '').slice(-1);
    if (el.value && i < inputs.length - 1) inputs[i + 1].focus();
    tryAutoSubmit();
  });
  el.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !el.value && i > 0) inputs[i - 1].focus();
    if (e.key === 'ArrowLeft'  && i > 0) inputs[i - 1].focus();
    if (e.key === 'ArrowRight' && i < inputs.length - 1) inputs[i + 1].focus();
  });
  el.addEventListener('paste', e => {
    e.preventDefault();
    const digits = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
    digits.split('').forEach((d, j) => { if (inputs[i + j]) inputs[i + j].value = d; });
    const next = Math.min(i + digits.length, inputs.length - 1);
    inputs[next].focus();
    tryAutoSubmit();
  });
});

function tryAutoSubmit() {
  if (inputs.every(el => el.value)) {
    document.getElementById('verifyBtn').disabled = true;
    document.getElementById('mfaForm').submit();
  }
}
</script>

<footer style="position:fixed;bottom:0;left:0;width:100%;padding:9px 20px;text-align:center;color:#7695B0;font-size:11px;font-family:'DM Sans',sans-serif;letter-spacing:.3px;z-index:1">
  &copy; {{ date('Y') }} FilmSpec &mdash; Integrated Film Operations Platform
</footer>
</body>
</html>
