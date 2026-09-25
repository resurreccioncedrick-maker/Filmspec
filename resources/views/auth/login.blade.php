<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,interactive-widget=resizes-content">
<title>FilmSpec — Sign In</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}

body{
  font-family:'DM Sans',sans-serif;
  background:#ECF2FA;
  min-height:100vh;
  min-height:100dvh;
  display:flex;align-items:center;justify-content:center;
  padding:20px;
}

.shell{
  display:flex;width:100%;max-width:1120px;min-height:580px;
  border-radius:18px;overflow:hidden;
  border:1px solid #CFDFEE;
  box-shadow:0 8px 40px rgba(0,30,80,.11),0 2px 8px rgba(0,30,80,.06);
}

.panel{
  flex:0 0 70%;
  background:#EBF3FC;
  background-image:
    linear-gradient(rgba(0,96,199,.055) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,96,199,.055) 1px, transparent 1px);
  background-size:54px 54px;
  padding:60px 64px;
  display:flex;flex-direction:column;justify-content:space-between;
  position:relative;overflow:hidden;
}
.panel::after{
  content:'';position:absolute;
  right:-160px;top:50%;transform:translateY(-50%);
  width:520px;height:520px;border-radius:50%;
  background:radial-gradient(circle, rgba(0,96,199,.09) 0%, transparent 68%);
  pointer-events:none;
}
.panel-brand-row{display:flex;flex-direction:column;gap:12px;position:relative;z-index:1}
.panel-logo{height:86px;object-fit:contain;object-position:left;display:block}
.panel-platform{font-size:11px;font-weight:700;letter-spacing:3.5px;text-transform:uppercase;color:#7695B0}

.panel-hero{position:relative;z-index:1;display:flex;flex-direction:column;gap:18px}
.panel-badge{display:inline-flex;align-items:center;gap:8px;border:1px solid #B5CFEA;border-radius:20px;padding:5px 14px;font-size:11px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:#385270;width:fit-content}
.badge-dot{width:7px;height:7px;border-radius:50%;background:#0060C7;flex-shrink:0}
.panel-headline{font-family:'Bebas Neue',sans-serif;font-size:clamp(48px,4.6vw,76px);color:#0B1A33;line-height:.92;letter-spacing:1px;text-transform:uppercase}
.hl-blue{color:#0060C7}
.panel-desc{font-size:14px;color:#385270;line-height:1.75;max-width:420px}

.form-side{
  flex:1;background:#fff;
  padding:44px 36px 36px;
  display:flex;flex-direction:column;justify-content:center;
  overflow-y:auto;
  border-left:1px solid #CFDFEE;
}
.form-logo{display:flex;align-items:center;gap:8px;margin-bottom:26px}
.form-logo img{height:24px;object-fit:contain}
.form-logo-name{font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:2px;color:#0B1A33}
.form-title{font-size:22px;font-weight:700;color:#0B1A33;letter-spacing:-.3px;margin-bottom:4px}
.form-sub{font-size:13px;color:#385270;margin-bottom:22px}

.tabs{display:flex;background:#ECF2FA;border-radius:9px;padding:3px;gap:2px;margin-bottom:20px}
.tbtn{flex:1;padding:7px 8px;border-radius:7px;font-size:12.5px;font-weight:500;cursor:pointer;text-align:center;color:#385270;border:none;background:transparent;font-family:'DM Sans',sans-serif;transition:all .18s}
.tbtn.on{background:#fff;color:#0060C7;font-weight:700;box-shadow:0 1px 5px rgba(0,30,80,.09)}

.fg{display:flex;flex-direction:column;gap:4px;margin-bottom:12px}
.fg label{font-size:11px;color:#385270;font-weight:600;letter-spacing:.4px;text-transform:uppercase}
.fg input,.fg select{background:#F0F6FC;border:1.5px solid #CFDFEE;color:#0B1A33;padding:10px 12px;border-radius:8px;font-size:13.5px;font-family:'DM Sans',sans-serif;outline:none;width:100%;transition:border-color .18s,box-shadow .18s,background .18s}
.fg input:focus,.fg select:focus{border-color:#0060C7;box-shadow:0 0 0 3px rgba(0,96,199,.10);background:#fff}
.fg input::placeholder{color:#7695B0}
.fg select option{background:#fff;color:#0B1A33}
.pw-wrap{position:relative}
.pw-wrap input{padding-right:50px}
.pw-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#7695B0;font-size:9.5px;font-family:'DM Sans',sans-serif;font-weight:700;padding:3px 5px;border-radius:4px;transition:color .15s;letter-spacing:.6px}
.pw-toggle:hover{color:#0060C7}
.pw-bar-wrap{margin-top:5px}
.pw-bar-bg{height:3px;background:#CFDFEE;border-radius:2px;margin-bottom:4px}
.pw-bar{height:3px;border-radius:2px;width:0;transition:width .3s,background .3s}
.pw-label{font-size:10px;color:#7695B0;transition:color .3s}
.match-err{font-size:11px;color:#dc2626;margin-top:3px;display:none}
.vat-note{background:#E5F0FF;border:1px solid rgba(0,96,199,.2);border-radius:7px;padding:7px 10px;font-size:11.5px;color:#0060C7;margin-top:-6px;margin-bottom:12px;display:flex;align-items:flex-start;gap:6px;line-height:1.5}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.divider{height:1px;background:#CFDFEE;margin:12px 0}
.sbtn{width:100%;padding:11px;border-radius:8px;border:none;cursor:pointer;font-size:13.5px;font-weight:700;font-family:'DM Sans',sans-serif;transition:all .18s;margin-top:4px;background:#0060C7;color:#fff;letter-spacing:.01em;box-shadow:0 2px 8px rgba(0,96,199,.18)}
.sbtn:hover{background:#004EA3;box-shadow:0 4px 14px rgba(0,96,199,.26);transform:translateY(-1px)}
.sbtn:active{transform:translateY(0);box-shadow:none}
.sbtn:disabled{opacity:.45;cursor:not-allowed;box-shadow:none;transform:none}
.err{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;border-radius:7px;padding:9px 11px;font-size:12px;margin-bottom:12px;line-height:1.55}
.warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:7px;padding:9px 11px;font-size:12px;margin-bottom:12px;line-height:1.55}
.success{background:#f0fdf4;border:1px solid #86efac;color:#15803d;border-radius:7px;padding:9px 11px;font-size:12px;margin-bottom:12px;line-height:1.55}
.back{display:block;text-align:center;margin-top:12px;font-size:12px;color:#7695B0;text-decoration:none;transition:color .15s}
.back:hover{color:#0060C7}
.fs{display:none}.fs.on{display:block}
.note{font-size:11.5px;color:#7695B0;margin-top:8px;text-align:center;line-height:1.7}
.dpa-row{display:flex;align-items:flex-start;gap:9px;margin-bottom:10px;margin-top:4px}
.dpa-row input[type=checkbox]{width:14px;height:14px;margin-top:2px;flex-shrink:0;accent-color:#0060C7;cursor:pointer}
.dpa-row span{font-size:12px;color:#385270;line-height:1.6}
.dpa-row a,.dpa-row strong.link{color:#0060C7;cursor:pointer;font-weight:600;text-decoration:underline;text-underline-offset:2px}

@media(max-width:880px){
  /* Centering against 100vh/100dvh traps a focused input under the keyboard once the
     card is taller than the visible (keyboard-shrunk) viewport — align to the top instead
     so the page scrolls normally and the browser's own "scroll focused field into view"
     behavior can do its job. */
  body{align-items:flex-start}
  .shell{flex-direction:column;max-width:480px;min-height:unset;border-radius:14px}
  .panel{flex:none;padding:36px 32px 28px}
  .panel-logo{height:60px}
  .form-side{padding:32px 28px;border-left:none;border-top:1px solid #CFDFEE}
}
@media(max-width:520px){
  body{padding:12px}
  .shell{border-radius:10px}
  .form-side{padding:24px 20px 20px}
  .frow{grid-template-columns:1fr}
}
</style>
</head>
<body>

<div class="shell">

  <div class="panel">
    <div class="panel-brand-row">
      <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" class="panel-logo">
      <span class="panel-platform">Integrated Film Operations Platform</span>
    </div>

    <div class="panel-hero">
      <div class="panel-badge">
        <span class="badge-dot"></span>Professional Film Production
      </div>
      <div class="panel-headline">
        Everything<br>
        <span class="hl-blue">Your Production</span><br>
        Needs
      </div>
      <div class="panel-desc">Access professional cameras, lighting, grip, and sound equipment — plus qualified crew — all in one platform built for Philippine film productions.</div>
    </div>

    <div style="font-size:11px;color:#7695B0;letter-spacing:.3px;position:relative;z-index:1">&copy; {{ date('Y') }} FilmSpec</div>
  </div>

  <div class="form-side">

    @if ($expiredMsg)
    <div class="warn">{{ $expiredMsg }}</div>
    @endif
    @if (session('signup_success'))
    <div class="success">{{ session('signup_success') }}</div>
    @endif

    <div class="tabs">
      <button type="button" class="tbtn {{ $activeTab === 'login' ? 'on' : '' }}" onclick="switchTab('login')">Sign In</button>
      <button type="button" class="tbtn {{ $activeTab === 'signup' ? 'on' : '' }}" onclick="switchTab('signup')">Client Registration</button>
    </div>

    <!-- LOGIN -->
    <div class="fs {{ $activeTab === 'login' ? 'on' : '' }}" id="fsLogin">
      <div class="form-title">Welcome back</div>
      <div class="form-sub">Sign in to access your account</div>
      @if (session('reset_success'))
      <div class="err" style="background:#f0fdf4;border-color:#86efac;color:#15803d">{{ session('reset_success') }}</div>
      @endif
      @if ($errors->has('login'))
      <div class="err">{{ $errors->first('login') }}</div>
      @endif
      <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <input type="hidden" name="form" value="login">
        <div class="fg">
          <label>Email Address</label>
          <input type="email" name="email"
                 value="{{ $activeTab === 'login' ? old('email') : '' }}"
                 placeholder="your@email.com" required autofocus autocomplete="email">
        </div>
        <div class="fg">
          <label>Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="loginPw" placeholder="Enter your password"
                   required autocomplete="current-password">
            <button type="button" class="pw-toggle" onclick="togglePw('loginPw',this)">SHOW</button>
          </div>
          <a href="{{ route('forgot-password') }}" style="display:inline-block;margin-top:8px;font-size:12px;color:#0060C7;text-decoration:none;font-weight:600">Forgot password?</a>
        </div>
        <button type="submit" class="sbtn">Sign In &rarr;</button>
      </form>
      <div class="note">All accounts require a one-time email verification code on every sign-in.</div>
    </div>

    <!-- SIGNUP -->
    <div class="fs {{ $activeTab === 'signup' ? 'on' : '' }}" id="fsSignup">
      <div class="form-title">Client registration</div>
      <div class="form-sub">Fill in your details to get started — staff accounts are created internally</div>
      @if ($errors->has('register'))
      <div class="err">{{ $errors->first('register') }}</div>
      @endif
      @if ($errors->any() && !$errors->has('login') && !$errors->has('register'))
      <div class="err">{{ $errors->first() }}</div>
      @endif
      <form method="POST" action="{{ route('register.store') }}" id="signupForm" onsubmit="return validateSignup()">
        @csrf
        <input type="hidden" name="form" value="signup">
        <div class="frow">
          <div class="fg">
            <label>First Name *</label>
            <input type="text" name="first_name"
                   value="{{ old('first_name') }}"
                   placeholder="JUAN" required
                   oninput="this.value=this.value.toUpperCase()">
          </div>
          <div class="fg">
            <label>Last Name *</label>
            <input type="text" name="last_name"
                   value="{{ old('last_name') }}"
                   placeholder="DELA CRUZ" required
                   oninput="this.value=this.value.toUpperCase()">
          </div>
        </div>
        <div class="fg">
          <label>Email Address *</label>
          <input type="email" name="email"
                 value="{{ old('email') }}"
                 placeholder="you@email.com" required>
        </div>
        <div class="fg">
          <label>Phone Number <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
          <input type="text" name="phone"
                 value="{{ old('phone') }}"
                 placeholder="09XXXXXXXXX" maxlength="11"
                 oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)">
        </div>
        <div class="divider"></div>
        <div class="fg">
          <label>Password * <span style="font-weight:400;text-transform:none;letter-spacing:0">(minimum Fair)</span></label>
          <div class="pw-wrap">
            <input type="password" name="password" id="pwInput" placeholder="Create a strong password" required
                   oninput="checkPwStrength(this.value)">
            <button type="button" class="pw-toggle" onclick="togglePw('pwInput',this)">SHOW</button>
          </div>
          <div class="pw-bar-wrap">
            <div class="pw-bar-bg"><div class="pw-bar" id="pwBar"></div></div>
            <span class="pw-label" id="pwLabel"></span>
          </div>
        </div>
        <div class="fg">
          <label>Confirm Password *</label>
          <div class="pw-wrap">
            <input type="password" name="password2" id="pw2Input" placeholder="Repeat your password" required
                   oninput="checkMatch()">
            <button type="button" class="pw-toggle" onclick="togglePw('pw2Input',this)">SHOW</button>
          </div>
          <div class="match-err" id="matchErr">Passwords do not match.</div>
        </div>
        <div class="dpa-row">
          <input type="checkbox" name="dpa_consent" id="dpaConsent" value="1" required onchange="updateSignupBtn()">
          <span>
            I have read and understood FilmSpec's
            <strong class="link" onclick="openDpaModal(event)">Privacy Notice</strong>
            and consent to processing my personal information per <strong style="color:#94a3b8">R.A. 10173</strong>.
          </span>
        </div>
        <button type="submit" class="sbtn" id="createAcctBtn" disabled>Create Account &rarr; Verify Email</button>
      </form>
      <div class="note">A 6-digit code will be sent to your email to confirm your account.</div>
    </div>

    <a href="{{ url('/') }}" class="back">&larr; Back to Homepage</a>
  </div>

</div>

<script>
function switchTab(t){
  document.getElementById('fsLogin').classList.toggle('on', t==='login');
  document.getElementById('fsSignup').classList.toggle('on', t==='signup');
  document.querySelectorAll('.tbtn').forEach((b,i)=>b.classList.toggle('on',(i===0&&t==='login')||(i===1&&t==='signup')));
}

function togglePw(id, btn){
  const inp = document.getElementById(id);
  if (!inp) return;
  const show = inp.type==='password';
  inp.type = show ? 'text' : 'password';
  btn.textContent = show ? 'HIDE' : 'SHOW';
}

let pwScore = 0;
function checkPwStrength(pw){
  const bar = document.getElementById('pwBar');
  const lbl = document.getElementById('pwLabel');
  if (!pw){ bar.style.width='0'; lbl.textContent=''; pwScore=0; return; }
  pwScore=0;
  if (pw.length>=8)              pwScore++;
  if (pw.length>=12)             pwScore++;
  if (/[A-Z]/.test(pw))         pwScore++;
  if (/[0-9]/.test(pw))         pwScore++;
  if (/[^A-Za-z0-9]/.test(pw))  pwScore++;
  const levels=[
    {pct:'20%',color:'#ef4444',text:'Too weak'},
    {pct:'40%',color:'#f97316',text:'Weak'},
    {pct:'60%',color:'#eab308',text:'Fair'},
    {pct:'80%',color:'#22c55e',text:'Strong'},
    {pct:'100%',color:'#3b82f6',text:'Very strong'},
  ];
  const l=levels[Math.max(0,Math.min(pwScore-1,4))];
  bar.style.width=l.pct; bar.style.background=l.color;
  lbl.style.color=l.color; lbl.textContent=l.text;
}

function checkMatch(){
  const p1=document.getElementById('pwInput').value;
  const p2=document.getElementById('pw2Input').value;
  const err=document.getElementById('matchErr');
  err.style.display=(p2&&p1!==p2)?'block':'none';
}

function validateSignup(){
  const p1=document.getElementById('pwInput').value;
  const p2=document.getElementById('pw2Input').value;
  if(p1!==p2){ document.getElementById('matchErr').style.display='block'; return false; }
  if(pwScore<3){
    alert('Password is too weak. Please choose at least a Fair-strength password.');
    return false;
  }
  return true;
}

function openDpaModal(e){ e.preventDefault(); document.getElementById('dpaMo').style.display='flex'; }
function closeDpaModal(){ document.getElementById('dpaMo').style.display='none'; }
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeDpaModal(); });
function updateSignupBtn(){
  const btn = document.getElementById('createAcctBtn');
  if (btn) btn.disabled = !document.getElementById('dpaConsent').checked;
}
</script>
<script src="{{ asset('assets/js/keyboard-aware.js') }}"></script>

<div id="dpaMo" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)">
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;width:100%;max-width:520px;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 12px 48px rgba(15,23,42,.18)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px 16px;border-bottom:1px solid #e8eef6">
      <div style="font-size:15px;font-weight:700;color:#1e293b">Data Privacy Policy</div>
      <button onclick="closeDpaModal()" style="background:none;border:none;color:#94a3b8;font-size:22px;cursor:pointer;line-height:1;transition:color .15s" onmouseover="this.style.color='#1e293b'" onmouseout="this.style.color='#94a3b8'">&times;</button>
    </div>
    <div style="overflow-y:auto;padding:20px 24px;font-size:13px;color:#64748b;line-height:1.85;flex:1">
      <p style="color:#1e293b;font-weight:600;margin-bottom:12px">FilmSpec — Data Privacy Act Consent (R.A. 10173)</p>
      <p style="margin-bottom:14px">FilmSpec collects and processes the personal information you provide during account creation and booking to deliver its film equipment rental and crew management services.</p>
      <p style="color:#1e293b;font-weight:600;margin-bottom:7px">Information We Collect</p>
      <ul style="margin:0 0 14px 18px">
        <li>Name, email address, and contact number</li>
        <li>Booking and project details</li>
        <li>Payment transaction records</li>
      </ul>
      <p style="color:#1e293b;font-weight:600;margin-bottom:7px">How We Use Your Data</p>
      <ul style="margin:0 0 14px 18px">
        <li>Processing your equipment rental and crew booking requests</li>
        <li>Communicating booking confirmations and updates</li>
        <li>Generating contracts, cost estimates, and billing documents</li>
        <li>Maintaining records as required by applicable law</li>
      </ul>
      <p style="color:#1e293b;font-weight:600;margin-bottom:7px">Your Rights</p>
      <p style="margin-bottom:14px">Under the Data Privacy Act of 2012, you have the right to access, correct, and request erasure of your personal data.</p>
      <p style="color:#1e293b;font-weight:600;margin-bottom:7px">Data Retention</p>
      <p>Your data is retained for the duration of your account and for the period required by applicable regulations after account closure.</p>
    </div>
    <div style="padding:16px 24px;border-top:1px solid #e8eef6;display:flex;justify-content:flex-end">
      <button onclick="closeDpaModal();document.getElementById('dpaConsent').checked=true;updateSignupBtn();"
              style="background:#2563eb;color:#fff;border:none;padding:10px 26px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s"
              onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
        I Understand &amp; Agree
      </button>
    </div>
  </div>
</div>

</body>
</html>
