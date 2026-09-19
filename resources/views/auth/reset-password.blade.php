<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FilmSpec — Reset Password</title>
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
.otp-row{display:flex;gap:8px;justify-content:center;margin-bottom:20px}
.otp-row input{width:48px;height:56px;border-radius:8px;border:1.5px solid #CFDFEE;background:#F0F6FC;color:#0B1A33;font-family:'JetBrains Mono',monospace;font-size:24px;text-align:center;outline:none;transition:border-color .15s,box-shadow .15s,background .15s}
.otp-row input:focus{border-color:#0060C7;box-shadow:0 0 0 3px rgba(0,96,199,.10);background:#fff}
.fg{margin-bottom:16px}
.fg label{display:block;font-size:12.5px;font-weight:600;color:#0B1A33;margin-bottom:6px}
.pw-wrap{position:relative}
.pw-wrap input{width:100%;padding:11px 60px 11px 13px;border-radius:8px;border:1.5px solid #CFDFEE;background:#F0F6FC;color:#0B1A33;font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:border-color .15s,box-shadow .15s,background .15s}
.pw-wrap input:focus{border-color:#0060C7;box-shadow:0 0 0 3px rgba(0,96,199,.10);background:#fff}
.pw-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;font-size:11px;font-weight:700;color:#0060C7;cursor:pointer;letter-spacing:.5px}
.pw-bar-wrap{margin-top:8px}
.pw-bar-bg{height:5px;border-radius:3px;background:#E2ECF7;overflow:hidden}
.pw-bar{height:100%;width:0;background:#ef4444;transition:width .2s,background .2s}
.pw-label{font-size:11px;margin-top:4px;font-weight:600}
.match-err{display:none;color:#dc2626;font-size:11.5px;margin-top:6px}
.sbtn{width:100%;padding:12px;border-radius:9px;border:none;cursor:pointer;font-size:13.5px;font-weight:700;font-family:'DM Sans',sans-serif;background:#0060C7;color:#fff;transition:all .18s;letter-spacing:.01em;box-shadow:0 2px 8px rgba(0,96,199,.18)}
.sbtn:hover{background:#004EA3;box-shadow:0 4px 14px rgba(0,96,199,.26);transform:translateY(-1px)}
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
@media(max-width:880px){
  /* Centering against 100vh traps a focused input under the keyboard once the
     card is taller than the visible (keyboard-shrunk) viewport — align to the top
     instead so the page scrolls normally. */
  body{align-items:flex-start}
}
</style>
</head>
<body>
<div class="card">
  <div class="card-head">
    <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec">
    <span class="card-brand">FilmSpec</span>
  </div>
  <div class="sub">Account recovery</div>

  <h2>Reset your password</h2>
  @php
    $at = strpos($pending['email'], '@');
    $masked = substr($pending['email'], 0, 3) . str_repeat('*', max(0, $at - 3)) . substr($pending['email'], $at);
  @endphp
  <div class="desc">
    We sent a 6-digit code to <strong>{{ $masked }}</strong>. Enter it below along with your new password.
  </div>

  @if ($error)
  <div class="err">{{ $error }}</div>
  @endif

  <form method="POST" action="{{ route('reset-password.store') }}" id="resetForm" autocomplete="off">
    @csrf
    <div class="otp-row">
      <input type="text" name="d1" id="d1" maxlength="1" inputmode="numeric" pattern="\d" required autofocus>
      <input type="text" name="d2" id="d2" maxlength="1" inputmode="numeric" pattern="\d" required>
      <input type="text" name="d3" id="d3" maxlength="1" inputmode="numeric" pattern="\d" required>
      <input type="text" name="d4" id="d4" maxlength="1" inputmode="numeric" pattern="\d" required>
      <input type="text" name="d5" id="d5" maxlength="1" inputmode="numeric" pattern="\d" required>
      <input type="text" name="d6" id="d6" maxlength="1" inputmode="numeric" pattern="\d" required>
    </div>

    <div class="fg">
      <label>New Password</label>
      <div class="pw-wrap">
        <input type="password" name="password" id="pwInput" placeholder="Create a strong password" required oninput="checkPwStrength(this.value)">
        <button type="button" class="pw-toggle" onclick="togglePw('pwInput',this)">SHOW</button>
      </div>
      <div class="pw-bar-wrap">
        <div class="pw-bar-bg"><div class="pw-bar" id="pwBar"></div></div>
        <div class="pw-label" id="pwLabel"></div>
      </div>
    </div>

    <div class="fg">
      <label>Confirm New Password</label>
      <div class="pw-wrap">
        <input type="password" name="password2" id="pw2Input" placeholder="Repeat your new password" required oninput="checkMatch()">
        <button type="button" class="pw-toggle" onclick="togglePw('pw2Input',this)">SHOW</button>
      </div>
      <div class="match-err" id="matchErr">Passwords do not match.</div>
    </div>

    <button type="submit" class="sbtn" onclick="return validateReset()">Reset Password</button>
  </form>

  <div class="resend-row">
    Didn't receive it? <a href="{{ route('forgot-password') }}">Request a new code</a>
  </div>
  <a href="{{ route('login') }}" class="back">&larr; Back to Sign In</a>
</div>

<script>
const inputs = Array.from(document.querySelectorAll('.otp-row input'));
inputs.forEach((el, i) => {
  el.addEventListener('input', () => {
    el.value = el.value.replace(/\D/g, '').slice(-1);
    if (el.value && i < inputs.length - 1) inputs[i + 1].focus();
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
  });
});

function togglePw(id, btn) {
  const inp = document.getElementById(id);
  if (!inp) return;
  const show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  btn.textContent = show ? 'HIDE' : 'SHOW';
}

let pwScore = 0;
function checkPwStrength(pw) {
  const bar = document.getElementById('pwBar');
  const lbl = document.getElementById('pwLabel');
  if (!pw) { bar.style.width = '0'; lbl.textContent = ''; pwScore = 0; return; }
  pwScore = 0;
  if (pw.length >= 8) pwScore++;
  if (pw.length >= 12) pwScore++;
  if (/[A-Z]/.test(pw)) pwScore++;
  if (/[0-9]/.test(pw)) pwScore++;
  if (/[^A-Za-z0-9]/.test(pw)) pwScore++;
  const levels = [
    {pct:'20%',color:'#ef4444',text:'Too weak'},
    {pct:'40%',color:'#f97316',text:'Weak'},
    {pct:'60%',color:'#eab308',text:'Fair'},
    {pct:'80%',color:'#22c55e',text:'Strong'},
    {pct:'100%',color:'#3b82f6',text:'Very strong'},
  ];
  const l = levels[Math.max(0, Math.min(pwScore - 1, 4))];
  bar.style.width = l.pct; bar.style.background = l.color;
  lbl.style.color = l.color; lbl.textContent = l.text;
}

function checkMatch() {
  const p1 = document.getElementById('pwInput').value;
  const p2 = document.getElementById('pw2Input').value;
  document.getElementById('matchErr').style.display = (p2 && p1 !== p2) ? 'block' : 'none';
}

function validateReset() {
  const p1 = document.getElementById('pwInput').value;
  const p2 = document.getElementById('pw2Input').value;
  if (p1 !== p2) { document.getElementById('matchErr').style.display = 'block'; return false; }
  if (pwScore < 2) { alert('Password is too weak. Please choose at least a Fair-strength password.'); return false; }
  return true;
}
</script>

<footer style="position:fixed;bottom:0;left:0;width:100%;padding:9px 20px;text-align:center;color:#7695B0;font-size:11px;font-family:'DM Sans',sans-serif;letter-spacing:.3px;z-index:1">
  &copy; {{ date('Y') }} FilmSpec &mdash; Integrated Film Operations Platform
</footer>
<script src="{{ asset('assets/js/keyboard-aware.js') }}"></script>
</body>
</html>
