<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>FilmSpec — Equipment &amp; Crew Rentals</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ─── RESET + TOKENS ─── */
:root{
  --bg:#f0f5fb;--surface:#fff;--s2:#f4f8fd;--s3:#e8f0fb;
  --border:#dde8f5;--border2:#c3d6ee;
  --blue:#0060C7;--blue2:#004EA3;--bluelt:#E5F0FF;--bluemid:#A8D0FF;
  --text:#0d1f3c;--sub:#445e7a;--muted:#8aa4be;
  --green:#15803d;--greenlt:#dcfce7;--red:#dc2626;--redlt:#fee2e2;
  --orange:#c2410c;--orangelt:#ffedd5;--yellow:#a16207;--yellowlt:#fef9c3;
  --font-d:'Bebas Neue',sans-serif;--font-b:'DM Sans',sans-serif;--font-m:'JetBrains Mono',monospace;
  --radius-sm:6px;--radius-md:10px;--radius-lg:14px;
  --shadow-sm:0 1px 4px rgba(0,30,80,.06);
  --shadow-md:0 4px 18px rgba(37,99,235,.11);
  --shadow-lg:0 12px 40px rgba(0,30,80,.14);
  --trans:.18s cubic-bezier(.4,0,.2,1);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-b);background:var(--bg);color:var(--text);min-height:100vh;-webkit-font-smoothing:antialiased;display:flex;flex-direction:column}
a{text-decoration:none;color:inherit}
button{font-family:var(--font-b);cursor:pointer}

/* ─── NAV ─── */
.nav{
  background:var(--surface);
  border-bottom:1px solid var(--border);
  padding:0 32px;
  height:62px;
  display:flex;
  align-items:center;
  gap:20px;
  position:sticky;
  top:0;
  z-index:100;
  box-shadow:0 2px 12px rgba(0,30,80,.07);
}
.nav-logo{
  font-family:var(--font-d);
  font-size:22px;
  letter-spacing:2px;
  color:var(--blue);
  white-space:nowrap;
  flex-shrink:0;
}
.nav-links{display:flex;gap:2px;flex:1;justify-content:center}
.nl{
  padding:7px 15px;
  border-radius:var(--radius-sm);
  font-size:13px;
  cursor:pointer;
  color:var(--sub);
  font-weight:500;
  border:none;
  background:none;
  font-family:var(--font-b);
  transition:all var(--trans);
  position:relative;
}
.nl:hover{color:var(--blue);background:var(--bluelt)}
.nl.on{color:var(--blue);background:var(--bluelt);font-weight:700}
.nl.on::after{
  content:'';
  position:absolute;
  bottom:-1px;
  left:50%;
  transform:translateX(-50%);
  width:20px;
  height:2px;
  background:var(--blue);
  border-radius:2px 2px 0 0;
}
.nav-right{display:flex;align-items:center;gap:10px;flex-shrink:0}

.nav-hamburger{
  display:none;
  background:none;
  border:none;
  cursor:pointer;
  padding:6px;
  color:var(--blue);
  flex-shrink:0;
}
.nav-hamburger svg{width:24px;height:24px;display:block}
.nav-mobile-menu{
  display:none;
  position:absolute;
  top:62px;
  left:0;
  right:0;
  background:var(--surface);
  border-bottom:1px solid var(--border);
  box-shadow:0 8px 20px rgba(0,30,80,.1);
  flex-direction:column;
  padding:8px;
  gap:2px;
  z-index:99;
}
.nav-mobile-menu.open{display:flex}
.nav-mobile-menu .nl{width:100%;text-align:left;padding:12px 15px}
.nav-mobile-menu .nl.on::after{display:none}

.rl-btn{
  display:flex;
  align-items:center;
  gap:7px;
  padding:8px 18px;
  background:var(--blue);
  color:#fff;
  border-radius:var(--radius-sm);
  font-size:13px;
  font-weight:700;
  border:none;
  cursor:pointer;
  position:relative;
  transition:all var(--trans);
  letter-spacing:.01em;
}
.rl-btn:hover{background:var(--blue2);transform:translateY(-1px);box-shadow:0 4px 14px rgba(0,96,199,.28)}
.rl-count{
  position:absolute;
  top:-7px;
  right:-7px;
  background:var(--red);
  color:#fff;
  border-radius:50%;
  width:19px;
  height:19px;
  font-size:10px;
  font-weight:700;
  display:flex;
  align-items:center;
  justify-content:center;
  border:2px solid #fff;
}

.user-chip{
  display:flex;
  align-items:center;
  gap:8px;
  padding:6px 13px;
  background:var(--bluelt);
  border:1px solid var(--bluemid);
  border-radius:var(--radius-sm);
  font-size:12.5px;
  color:var(--blue);
  font-weight:600;
  transition:all var(--trans);
}
.uav{
  width:26px;
  height:26px;
  border-radius:50%;
  background:linear-gradient(135deg,var(--blue),#7c3aed);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:10px;
  font-weight:700;
  color:#fff;
  flex-shrink:0;
}

.nav-btn{
  padding:7px 16px;
  border-radius:var(--radius-sm);
  font-size:13px;
  font-weight:500;
  cursor:pointer;
  border:1.5px solid var(--border2);
  background:var(--surface);
  color:var(--sub);
  transition:all var(--trans);
  font-family:var(--font-b);
}
.nav-btn:hover{border-color:var(--blue);color:var(--blue);background:var(--bluelt)}
.nav-btn.primary{background:var(--blue);color:#fff;border-color:var(--blue)}
.nav-btn.primary:hover{background:var(--blue2)}

/* ─── PAGES ─── */
.pg{display:none}.pg.on{display:block;flex:1}

/* ─── HERO ─── */
.hero{
  background:linear-gradient(145deg,#ffffff 0%,#eef4ff 55%,#f0f5fb 100%);
  border-bottom:1px solid var(--border);
  min-height:86vh;
  display:flex;
  align-items:center;
  position:relative;
  overflow:hidden;
}
.hero-grid{
  position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(0,60,199,.04) 1px,transparent 1px),
    linear-gradient(90deg,rgba(0,60,199,.04) 1px,transparent 1px);
  background-size:56px 56px;
  pointer-events:none;
}
.hero-orb{position:absolute;border-radius:50%;pointer-events:none;}
.hero-orb.a{
  width:700px;height:700px;
  top:-250px;left:-160px;
  background:radial-gradient(circle,rgba(0,96,199,.09) 0%,transparent 65%);
  animation:orbdrift 12s ease-in-out infinite alternate;
}
.hero-orb.b{
  width:600px;height:600px;
  bottom:-220px;right:-160px;
  background:radial-gradient(circle,rgba(124,58,237,.06) 0%,transparent 65%);
  animation:orbdrift 9s ease-in-out infinite alternate-reverse;
}
@keyframes orbdrift{to{transform:translate(48px,38px) scale(1.07)}}
.hero-diag{
  position:absolute;top:0;right:0;
  width:55%;height:100%;
  background:linear-gradient(140deg,transparent 52%,rgba(0,60,199,.04) 52%);
  pointer-events:none;
}
.hero-strip{
  position:absolute;top:0;bottom:0;left:0;
  width:4px;
  background:repeating-linear-gradient(to bottom,
    var(--blue) 0,var(--blue) 16px,
    transparent 16px,transparent 26px);
  opacity:.18;
}
.hero-inner{
  position:relative;z-index:2;
  max-width:1180px;margin:0 auto;
  padding:96px 48px;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:0;
  align-items:center;
  width:100%;
}
.hero-left{}
.hero-right{
  padding-left:52px;
  border-left:1px solid var(--border);
}
.hero-logo-wrap{
  display:inline-block;
}
.hero-logo-img{
  height:80px;
  width:auto;
  display:block;
}
.hero-eq-line{
  font-family:var(--font-d);
  font-size:clamp(13px,1.8vw,17px);
  letter-spacing:4px;
  color:var(--muted);
  text-transform:uppercase;
}
.hero-tag{
  display:inline-flex;align-items:center;gap:7px;
  background:rgba(0,96,199,.08);
  border:1px solid rgba(0,96,199,.22);
  color:var(--blue);
  font-size:10.5px;font-weight:700;
  text-transform:uppercase;letter-spacing:2px;
  padding:5px 14px;border-radius:20px;
  margin-bottom:24px;
}
.hero-tag::before{
  content:'';
  width:6px;height:6px;border-radius:50%;
  background:var(--blue);
  animation:hpulse 2s ease-in-out infinite;
}
@keyframes hpulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.35;transform:scale(.65)}}
.hero h1{
  font-family:var(--font-d);
  font-size:clamp(56px,6vw,90px);
  letter-spacing:1px;
  line-height:.90;
  margin-bottom:24px;
  color:#fff;
}
.hero h1 .accent{color:var(--blue);display:block}
.hero h1 .dim{color:var(--muted)}
.hero-sub{
  font-size:15px;
  color:var(--sub);
  max-width:450px;
  margin:0 0 36px;
  line-height:1.85;
}
.hero-acts{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.hbtn{
  padding:13px 28px;
  border-radius:var(--radius-md);
  font-size:14px;font-weight:700;
  cursor:pointer;border:none;
  font-family:var(--font-b);
  transition:all var(--trans);
  letter-spacing:.02em;
}
.hbtn.blue{
  background:var(--blue);
  color:#fff;
  box-shadow:0 4px 20px rgba(0,96,199,.45);
}
.hbtn.blue:hover{
  background:#0072ef;
  transform:translateY(-2px);
  box-shadow:0 8px 28px rgba(0,96,199,.6);
}
.hbtn.ghost{
  background:transparent;
  color:var(--blue);
  border:1.5px solid var(--blue2);
}
.hbtn.ghost:hover{
  background:var(--bluelt);
  color:var(--blue2);
  border-color:var(--blue);
}
.hero-stats{display:flex;flex-direction:column;gap:11px;}
.hstat{
  background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.08);
  backdrop-filter:blur(10px);
  border-radius:13px;
  padding:17px 20px;
  display:flex;align-items:center;gap:15px;
  transition:all var(--trans);cursor:default;
}
.hstat:hover{
  background:rgba(255,255,255,.08);
  border-color:rgba(0,96,199,.3);
  transform:translateX(5px);
}
.hstat-icon{
  width:44px;height:44px;
  border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.hstat-n{
  font-family:var(--font-d);
  font-size:36px;color:#fff;
  line-height:1;letter-spacing:1px;
}
.hstat-l{
  font-size:10.5px;
  color:rgba(255,255,255,.40);
  margin-top:2px;font-weight:600;
  text-transform:uppercase;letter-spacing:.8px;
}
.hero-logo-display{
  position:relative;
  display:flex;
  align-items:center;
  justify-content:center;
}
.hero-logo-glow{
  position:absolute;
  inset:-40px;
  background:radial-gradient(ellipse at center,rgba(0,96,199,.5) 0%,transparent 66%);
  border-radius:50%;
  animation:logoglw 5s ease-in-out infinite alternate;
  pointer-events:none;
}
@keyframes logoglw{to{transform:scale(1.08);opacity:.7}}
.hero-logo-frame{
  position:relative;
  background:#fff;
  border-radius:20px;
  padding:40px 52px;
  box-shadow:0 0 0 1px rgba(255,255,255,.18),0 24px 64px rgba(0,0,0,.45),0 0 60px rgba(0,96,199,.25);
  z-index:1;
}
.vf-corner{
  position:absolute;
  width:18px;height:18px;
  border-color:rgba(0,96,199,.7);
  border-style:solid;
}
.vf-tl{top:10px;left:10px;border-width:2px 0 0 2px}
.vf-tr{top:10px;right:10px;border-width:2px 2px 0 0}
.vf-bl{bottom:10px;left:10px;border-width:0 0 2px 2px}
.vf-br{bottom:10px;right:10px;border-width:0 2px 2px 0}
.hero-logo-img{
  height:100px;
  width:auto;
  object-fit:contain;
  display:block;
  position:relative;z-index:1;
}
.hero-logo-tagline{
  text-align:center;
  margin-top:14px;
  font-size:10px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:2px;
  color:rgba(255,255,255,.3);
}
.hero-stats-strip{
  display:flex;
  gap:0;
  margin-top:36px;
  padding-top:26px;
  border-top:1px solid var(--border);
  justify-content:center;
}
.hstat-mini{
  display:flex;
  flex-direction:column;
  gap:3px;
  min-width:120px;
}
.hstat-mini+.hstat-mini{
  border-left:1px solid var(--border);
  padding-left:28px;
  margin-left:28px;
}
.hstat-mini-n{
  font-family:var(--font-d);
  font-size:36px;
  color:var(--text);
  line-height:1;
  letter-spacing:.5px;
}
.hstat-mini-l{
  font-size:10px;
  color:var(--muted);
  font-weight:600;
  text-transform:uppercase;
  letter-spacing:.8px;
  margin-top:1px;
}

/* ─── CONTENT WRAP ─── */
.ccon{max-width:1220px;margin:0 auto;padding:32px 24px}
.sec-label{
  font-size:10px;
  text-transform:uppercase;
  letter-spacing:1.5px;
  color:var(--blue);
  font-weight:700;
  margin-bottom:4px;
}
.sec-title{
  font-family:var(--font-d);
  font-size:30px;
  letter-spacing:.5px;
  margin-bottom:4px;
  color:var(--text);
}
.sec-sub{font-size:13px;color:var(--muted);margin-bottom:22px;line-height:1.6}

/* ─── HOW IT WORKS ─── */
.hiw-section{
  background:var(--surface);
  border-top:1px solid var(--border);
  border-bottom:1px solid var(--border);
}
.steps-new{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:4px;
  position:relative;
}
.steps-new::before{
  content:'';
  position:absolute;
  top:44px;left:12%;right:12%;
  height:1px;
  background:linear-gradient(90deg,transparent,var(--border2) 20%,var(--border2) 80%,transparent);
  z-index:0;
}
.step-new{
  display:flex;flex-direction:column;
  align-items:center;text-align:center;
  padding:28px 18px;
  position:relative;z-index:1;
  border-radius:var(--radius-md);
  transition:all var(--trans);
}
.step-new:hover{background:var(--s2)}
.step-new-icon{
  width:66px;height:66px;
  border-radius:18px;
  display:flex;align-items:center;justify-content:center;
  margin-bottom:14px;
  position:relative;
  transition:transform var(--trans);
}
.step-new:hover .step-new-icon{transform:translateY(-3px)}
.step-new-badge{
  position:absolute;
  top:-7px;right:-7px;
  width:20px;height:20px;
  border-radius:50%;
  background:var(--blue);
  color:#fff;
  font-size:10px;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  border:2px solid var(--surface);
  font-family:var(--font-b);
}
.step-new-title{
  font-size:14px;font-weight:700;
  color:var(--text);margin-bottom:8px;
  line-height:1.3;
}
.step-new-desc{
  font-size:12px;color:var(--muted);
  line-height:1.75;max-width:190px;
}

/* ─── EQUIPMENT CATALOG ─── */
.eq-page-header{
  background:linear-gradient(135deg,#0a1628 0%,#0d2554 55%,#0a2447 100%);
  padding:52px 32px 46px;position:relative;overflow:hidden;
}
.eq-page-header::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),
    linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);
  background-size:44px 44px;
}
.eq-page-header::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.08) 30%,rgba(0,96,199,.5) 50%,rgba(255,255,255,.08) 70%,transparent);
}
.eq-ph-inner{
  max-width:1220px;margin:0 auto;position:relative;z-index:1;
  display:flex;align-items:flex-end;justify-content:space-between;
  gap:24px;flex-wrap:wrap;
}
.eq-page-eyebrow{
  font-size:10px;text-transform:uppercase;letter-spacing:3px;
  color:rgba(96,165,250,.75);font-weight:700;margin-bottom:8px;
}
.eq-page-title{
  font-family:var(--font-d);font-size:clamp(38px,5vw,60px);
  letter-spacing:2px;color:#fff;line-height:.96;margin-bottom:10px;
}
.eq-page-sub{font-size:13px;color:rgba(255,255,255,.4);font-weight:400;line-height:1.6}
.eq-page-count{
  background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);
  color:#fff;border-radius:24px;padding:9px 22px;
  font-size:13px;font-weight:700;white-space:nowrap;
  backdrop-filter:blur(8px);flex-shrink:0;align-self:flex-end;
}

.eq-toolbar{
  background:var(--surface);border-bottom:1px solid var(--border);
  padding:0 32px;position:sticky;top:62px;z-index:90;
  display:flex;align-items:center;gap:0;
  box-shadow:0 2px 8px rgba(0,30,80,.05);
}
.cat-pills{
  display:flex;gap:2px;flex:1;overflow-x:auto;
  scrollbar-width:none;padding:10px 0;
}
.cat-pills::-webkit-scrollbar{display:none}
.pill{
  padding:6px 15px;border-radius:7px;font-size:12.5px;font-weight:600;
  cursor:pointer;border:none;background:none;color:var(--sub);
  transition:all var(--trans);white-space:nowrap;font-family:var(--font-b);
}
.pill:hover{background:var(--s2);color:var(--text)}
.pill.on{background:var(--blue);color:#fff}

.eq-toolbar-sep{width:1px;background:var(--border);margin:10px 16px;align-self:stretch;flex-shrink:0}
.search-bar{
  display:flex;align-items:center;gap:8px;
  background:var(--s2);border:1.5px solid var(--border);
  border-radius:8px;padding:0 13px;
  width:220px;flex-shrink:0;
  transition:all var(--trans);margin:10px 0;
}
.search-bar:focus-within{
  border-color:var(--blue);background:var(--surface);
  box-shadow:0 0 0 3px rgba(0,96,199,.1);width:260px;
}
.search-bar input{
  background:none;border:none;font-size:13px;padding:8px 0;
  outline:none;color:var(--text);font-family:var(--font-b);flex:1;min-width:0;
}
.search-bar input::placeholder{color:var(--muted)}

.eq-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(256px,1fr));
  gap:20px;
}
.eq-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:14px;overflow:hidden;
  transition:box-shadow var(--trans),transform var(--trans),border-color var(--trans);
  display:flex;flex-direction:column;
}
.eq-card:hover{
  border-color:var(--border2);
  box-shadow:0 16px 48px rgba(0,30,80,.13);
  transform:translateY(-4px);
}
.eq-img{
  width:100%;aspect-ratio:4/3;background:#0f172a;
  display:flex;align-items:center;justify-content:center;
  overflow:hidden;flex-shrink:0;position:relative;
}
.eq-img img{width:100%;height:100%;object-fit:cover;transition:transform .5s ease}
.eq-card:hover .eq-img img{transform:scale(1.06)}
.eq-img[onclick]::after{
  content:'View Details';position:absolute;inset:0;
  background:rgba(10,22,40,.52);color:#fff;
  font-size:.65rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  display:flex;align-items:center;justify-content:center;
  opacity:0;transition:opacity .22s;
}
.eq-img[onclick]:hover::after{opacity:1}
.eq-cat-icon{font-family:var(--font-d);font-size:42px;letter-spacing:2px;color:rgba(255,255,255,.1);user-select:none}
.eq-cat-pill{
  position:absolute;bottom:10px;left:10px;
  background:rgba(0,0,0,.6);color:#fff;
  font-size:9.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
  padding:3px 10px;border-radius:20px;
  backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.1);
}
.avail-pill{
  position:absolute;top:10px;right:10px;
  font-size:10px;font-weight:700;padding:3px 9px 3px 7px;border-radius:20px;
  display:flex;align-items:center;gap:5px;backdrop-filter:blur(4px);
}
.avail-pill::before{content:'';width:6px;height:6px;border-radius:50%;flex-shrink:0}
.avail-pill.av{background:rgba(21,128,61,.18);color:#15803d;border:1px solid rgba(21,128,61,.28)}
.avail-pill.av::before{background:#15803d}
.avail-pill.busy{background:rgba(185,28,28,.16);color:#dc2626;border:1px solid rgba(185,28,28,.22)}
.avail-pill.busy::before{background:#dc2626}
.avail-pill.inuse{background:rgba(194,65,12,.16);color:#c2410c;border:1px solid rgba(194,65,12,.22)}
.avail-pill.inuse::before{background:#c2410c}
.form-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:600;display:block;margin-bottom:6px}
.fav-star{
  position:absolute;top:10px;left:10px;z-index:2;
  width:28px;height:28px;border-radius:50%;border:1px solid rgba(255,255,255,.16);
  background:rgba(10,22,40,.52);backdrop-filter:blur(4px);
  display:flex;align-items:center;justify-content:center;cursor:pointer;
  transition:background .15s,transform .15s;padding:0;
}
.fav-star:hover{transform:scale(1.08);background:rgba(10,22,40,.72)}
.fav-star svg{width:15px;height:15px;stroke:#fff;fill:none;stroke-width:2}
.fav-star.on svg{fill:#facc15;stroke:#facc15}
.pill.fav-pill{display:inline-flex;align-items:center;gap:5px}

.eq-body{padding:17px 18px 18px;flex:1;display:flex;flex-direction:column}
.eq-name{font-size:14.5px;font-weight:800;color:var(--text);line-height:1.25;margin-bottom:3px}
.eq-brand{font-size:10.5px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:14px}
.eq-divider{height:1px;background:var(--border);margin:0 0 13px}
.eq-op{
  display:inline-flex;align-items:center;gap:5px;
  font-size:10.5px;font-weight:600;color:var(--sub);
  background:var(--s2);border:1px solid var(--border);
  border-radius:6px;padding:3px 9px;
  margin-bottom:13px;align-self:flex-start;
}
.eq-op::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--muted);flex-shrink:0}
.eq-foot{margin-top:auto}
.eq-rate{
  font-family:var(--font-d);font-size:26px;
  color:var(--text);letter-spacing:.5px;line-height:1;margin-bottom:2px;
}
.eq-rate-sub{font-size:10.5px;color:var(--muted);margin-bottom:14px}

.req-btn{
  width:100%;padding:10px 14px;border-radius:8px;
  font-size:13px;font-weight:700;background:var(--blue);color:#fff;
  border:none;cursor:pointer;font-family:var(--font-b);transition:all var(--trans);
  display:flex;align-items:center;justify-content:center;gap:6px;letter-spacing:.02em;
}
.req-btn:hover{background:var(--blue2);box-shadow:0 4px 14px rgba(0,96,199,.3);transform:translateY(-1px)}
.req-btn.selected{background:var(--greenlt);color:var(--green);border:1.5px solid #86efac;transform:none}
.req-btn.selected:hover{background:var(--redlt);color:var(--red);border-color:#fca5a5}
.req-btn:disabled{background:var(--s2);color:var(--muted);cursor:not-allowed;box-shadow:none;transform:none}

/* ─── CREW ─── */
.crew-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
.crew-card{
  background:var(--surface);
  border:1.5px solid var(--border);
  border-radius:var(--radius-md);
  padding:16px;
  text-align:center;
  transition:all var(--trans);
}
.crew-card:hover{border-color:var(--blue);box-shadow:var(--shadow-md)}
.crew-av{
  width:52px;
  height:52px;
  border-radius:50%;
  margin:0 auto 10px;
  background:linear-gradient(135deg,var(--blue),#7c3aed);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:18px;
  font-weight:700;
  color:#fff;
  overflow:hidden;
}
.crew-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.crew-name{font-size:13px;font-weight:700;color:var(--text);margin-bottom:2px}
.crew-pos{font-size:11.5px;color:var(--sub);margin-bottom:6px}
.crew-rate{font-family:var(--font-m);font-size:12.5px;color:var(--blue)}
.crew-shoots{font-size:10.5px;color:var(--muted);margin-top:2px}

/* ─── MY BOOKINGS ─── */
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.bk-tbl{width:100%;border-collapse:collapse}
.bk-tbl th{
  font-size:10px;
  letter-spacing:1px;
  text-transform:uppercase;
  color:var(--muted);
  padding:10px 14px;
  text-align:left;
  border-bottom:1.5px solid var(--border);
  background:var(--s2);
  font-weight:700;
}
.bk-tbl td{
  padding:12px 14px;
  font-size:13px;
  border-bottom:1px solid var(--border);
  vertical-align:middle;
}
.bk-tbl tr:last-child td{border-bottom:none}
.bk-tbl tbody tr{transition:background var(--trans)}
.bk-tbl tbody tr:hover td{background:var(--bluelt)}

/* Status-colored row edge (Option B redesign) */
.bk-tbl tbody tr.row-action td:first-child{box-shadow:inset 3px 0 0 var(--orange)}
.bk-tbl tbody tr.row-pending td:first-child{box-shadow:inset 3px 0 0 var(--yellow)}
.bk-tbl tbody tr.row-confirmed td:first-child{box-shadow:inset 3px 0 0 var(--blue)}
.bk-tbl tbody tr.row-completed td:first-child{box-shadow:inset 3px 0 0 var(--green)}

.bk-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:11.5px;font-weight:700}
.bk-pill svg{width:11px;height:11px;flex-shrink:0}
.bk-pill.pending{background:var(--yellowlt);color:var(--yellow)}
.bk-pill.confirmed{background:var(--bluelt);color:var(--blue)}
.bk-pill.completed{background:var(--greenlt);color:var(--green)}
.bk-pill.action{background:var(--orangelt);color:var(--orange)}

.bk-cta{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;border-radius:7px;font-weight:700;font-size:12px;text-decoration:none;background:var(--bluelt);color:var(--blue)}
.bk-cta.warn{background:var(--orangelt);color:var(--orange)}

/* Stat tiles (Option B redesign) */
.bk-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}
.bk-stat{background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius-md);padding:14px 16px;display:flex;align-items:center;gap:12px;transition:border-color var(--trans)}
.bk-stat:hover{border-color:var(--blue)}
.bk-stat-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bk-stat-icon svg{width:18px;height:18px}
.bk-stat-n{font-family:var(--font-d);font-size:26px;line-height:1;color:var(--text)}
.bk-stat-l{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;font-weight:700;margin-top:2px}
.bk-stat.c-pending .bk-stat-n{color:var(--yellow)}
.bk-stat.c-confirmed .bk-stat-n{color:var(--blue)}
.bk-stat.c-completed .bk-stat-n{color:var(--green)}

.empty-state{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--radius-lg);
  padding:64px 40px;
  text-align:center;
}
.empty-icon{
  width:72px;
  height:72px;
  background:var(--s3);
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  margin:0 auto 18px;
  font-size:30px;
}
.empty-state h3{font-size:17px;font-weight:700;color:var(--sub);margin-bottom:8px}
.empty-state p{font-size:13px;color:var(--muted);margin-bottom:22px;line-height:1.7;max-width:360px;margin-left:auto;margin-right:auto}

.badge{
  display:inline-flex;
  align-items:center;
  padding:3px 10px;
  border-radius:20px;
  font-size:11px;
  font-weight:700;
  letter-spacing:.01em;
}

/* ─── REQUEST LIST (right panel) ─── */
.rl-overlay{
  position:fixed;
  inset:0;
  background:rgba(0,20,60,.4);
  backdrop-filter:blur(3px);
  z-index:299;
  opacity:0;
  pointer-events:none;
  transition:opacity .25s;
}
.rl-overlay.on{opacity:1;pointer-events:all}
.rl-panel{
  position:fixed;
  top:0;
  right:-460px;
  width:440px;
  height:100vh;
  background:var(--surface);
  border-left:1px solid var(--border);
  z-index:300;
  display:flex;
  flex-direction:column;
  transition:right .3s cubic-bezier(.4,0,.2,1);
  box-shadow:-12px 0 50px rgba(0,30,80,.15);
}
.rl-panel.open{right:0}

.rl-head{
  padding:0 20px;
  height:64px;
  border-bottom:1px solid rgba(255,255,255,.1);
  display:flex;
  align-items:center;
  justify-content:space-between;
  flex-shrink:0;
  background:linear-gradient(135deg,#0a2252,#0060C7);
}
.rl-title{
  font-family:var(--font-d);
  font-size:22px;
  letter-spacing:.5px;
  color:#fff;
}
.rl-close{
  background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.18);
  color:rgba(255,255,255,.8);
  font-size:18px;
  cursor:pointer;
  padding:4px 10px;
  border-radius:var(--radius-sm);
  transition:all var(--trans);
  line-height:1;
}
.rl-close:hover{background:rgba(220,38,38,.7);border-color:rgba(220,38,38,.5);color:#fff}

.rl-body{
  flex:1;
  overflow-y:auto;
  padding:14px 16px;
  scrollbar-width:thin;
  scrollbar-color:var(--border2) transparent;
}
.rl-empty{
  text-align:center;
  padding:48px 20px;
  color:var(--muted);
}
.rl-empty .ico{font-size:44px;margin-bottom:14px;opacity:.6}
.rl-empty h3{font-size:15px;font-weight:700;color:var(--sub);margin-bottom:6px}
.rl-empty p{font-size:12px;line-height:1.7}

/* ─── UPLOAD GEAR LIST (bulk add) ─── */
.rl-upload{
  background:var(--bluelt);
  border-bottom:1px solid var(--bluemid);
  padding:16px 20px;
  flex-shrink:0;
}
.rl-upload-title{
  display:flex;align-items:center;gap:7px;
  font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;
  color:var(--blue);margin-bottom:3px;
}
.rl-upload-sub{font-size:11.5px;color:var(--blue2);line-height:1.6;margin-bottom:12px}
.rl-upload-row{display:flex;gap:8px}
.rl-upload-pick{
  flex:1;display:flex;align-items:center;gap:8px;
  background:var(--surface);border:1.5px dashed var(--bluemid);border-radius:8px;
  padding:9px 12px;font-size:12px;color:var(--muted);cursor:pointer;
  transition:all var(--trans);overflow:hidden;white-space:nowrap;text-overflow:ellipsis;
}
.rl-upload-pick:hover{border-color:var(--blue)}
.rl-upload-pick.has-file{color:var(--text);font-weight:600;border-style:solid}
.rl-upload-days{
  display:flex;align-items:center;gap:6px;
  background:var(--surface);border:1.5px solid var(--bluemid);border-radius:8px;
  padding:0 6px 0 10px;flex-shrink:0;
}
.rl-upload-days span{font-size:10.5px;color:var(--sub);font-weight:600;white-space:nowrap}
.rl-upload-days input{
  width:38px;border:none;background:none;outline:none;
  font-family:var(--font-m);font-size:12px;color:var(--text);font-weight:600;
  padding:9px 4px;text-align:center;
}
.rl-upload-go{
  width:100%;margin-top:8px;padding:10px;border-radius:8px;border:none;cursor:pointer;
  background:var(--blue);color:#fff;font-size:12.5px;font-weight:700;font-family:var(--font-b);
  transition:all var(--trans);
}
.rl-upload-go:hover{background:var(--blue2)}
.rl-upload-go:disabled{background:var(--bluemid);cursor:not-allowed}
.rl-upload-processed{
  display:flex;align-items:center;justify-content:space-between;gap:10px;
}
.rl-upload-processed-name{
  display:flex;align-items:center;gap:7px;font-size:11.5px;color:var(--blue);font-weight:700;
  overflow:hidden;white-space:nowrap;text-overflow:ellipsis;
}
.rl-upload-again{font-size:11px;color:var(--blue);font-weight:700;text-decoration:underline;cursor:pointer;flex-shrink:0;background:none;border:none;font-family:var(--font-b)}

.rl-flagged{background:var(--surface);border-top:1px solid var(--border);flex-shrink:0;max-height:260px;overflow-y:auto;padding:12px 16px}
.rl-flagged-title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--orange);margin-bottom:8px}
.rl-flag-group{background:var(--s2);border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:8px}
.rl-flag-group:last-child{margin-bottom:0}
.rl-flag-group-h{padding:7px 11px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.rl-flag-group-h.not_found{background:var(--s2);color:var(--sub)}
.rl-flag-group-h.ambiguous{background:var(--yellowlt);color:var(--yellow)}
.rl-flag-group-h.unavailable{background:var(--orangelt);color:var(--orange)}
.rl-flag-group-h.external_source{background:var(--bluelt);color:var(--blue)}
.rl-flag-row{padding:7px 11px;border-top:1px solid var(--border);font-size:11.5px;color:var(--text);line-height:1.5}

.rli{
  background:var(--s2);
  border:1.5px solid var(--border);
  border-radius:var(--radius-md);
  padding:12px 13px;
  margin-bottom:10px;
  display:flex;
  gap:11px;
  align-items:flex-start;
  transition:all var(--trans);
}
.rli:hover{border-color:var(--bluemid);background:var(--bluelt)}
.rli-img{
  width:44px;
  height:44px;
  border-radius:var(--radius-sm);
  background:var(--s3);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:16px;
  overflow:hidden;
  flex-shrink:0;
  border:1px solid var(--border);
}
.rli-img img{width:100%;height:100%;object-fit:cover}
.rli-info{flex:1;min-width:0}
.rli-name{font-size:13px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rli-sub{font-size:11px;color:var(--sub);margin-top:1px;font-weight:500}
.rli-calc{display:flex;align-items:center;gap:7px;margin-top:8px}
.rli-calc input{
  width:50px;
  padding:4px 7px;
  border:1.5px solid var(--border2);
  border-radius:5px;
  font-size:12px;
  font-family:var(--font-m);
  text-align:center;
  outline:none;
  background:var(--surface);
  color:var(--text);
  transition:border-color var(--trans);
}
.rli-calc input:focus{border-color:var(--blue);box-shadow:0 0 0 2px rgba(0,96,199,.12)}
.rli-calc label{font-size:11px;color:var(--muted);font-weight:500}
.rli-amount{
  font-family:var(--font-m);
  font-size:12.5px;
  color:var(--blue);
  font-weight:700;
  margin-left:auto;
  white-space:nowrap;
  background:var(--bluelt);
  padding:2px 8px;
  border-radius:4px;
}
.rli-remove{
  background:none;
  border:none;
  color:var(--muted);
  cursor:pointer;
  font-size:16px;
  padding:3px 6px;
  border-radius:var(--radius-sm);
  flex-shrink:0;
  transition:all var(--trans);
}
.rli-remove:hover{color:var(--red);background:var(--redlt)}

.op-warn{
  background:#fff7ed;
  border:1.5px solid #fed7aa;
  border-radius:var(--radius-sm);
  padding:11px 13px;
  margin-bottom:10px;
  font-size:12px;
  color:var(--orange);
  line-height:1.5;
}
.op-warn strong{display:block;margin-bottom:3px;font-weight:700}

.rli-crew-est{
  display:flex;align-items:center;gap:6px;
  margin-top:6px;
  font-size:11.5px;color:var(--sub);
  background:rgba(0,96,199,.04);
  border-radius:5px;
  padding:5px 8px;
  border:1px dashed rgba(0,96,199,.18);
}
.rli-crew-est .crew-est-icon{font-size:12px;opacity:.65}
.rli-crew-est span:nth-child(2){flex:1}
.rli-crew-est .crew-est-amt{font-weight:600;color:var(--accent);font-size:11.5px}

.ce-sum{
  background:var(--s2);
  border:1.5px solid var(--border);
  border-radius:var(--radius-md);
  padding:14px;
  margin-bottom:12px;
}
.ce-row{display:flex;justify-content:space-between;font-size:12.5px;padding:4px 0;color:var(--sub)}
.ce-row strong{color:var(--text);font-weight:600}
.ce-row.total{
  border-top:1.5px solid var(--border2);
  margin-top:8px;
  padding-top:10px;
  font-weight:700;
  font-size:15px;
  color:var(--blue);
}
.ce-row.total span{font-family:var(--font-m)}

.rl-foot{padding:14px 16px;border-top:1px solid var(--border);flex-shrink:0}
.proc-btn{
  width:100%;
  padding:14px;
  border-radius:var(--radius-md);
  background:var(--blue);
  color:#fff;
  border:none;
  font-size:14.5px;
  font-weight:700;
  cursor:pointer;
  transition:all var(--trans);
  font-family:var(--font-b);
  letter-spacing:.02em;
}
.proc-btn:hover{
  background:var(--blue2);
  transform:translateY(-1px);
  box-shadow:0 6px 20px rgba(0,96,199,.3);
}
.proc-btn:disabled{
  background:var(--s3);
  color:var(--muted);
  cursor:not-allowed;
  transform:none;
  box-shadow:none;
}

/* ─── MODALS ─── */
.mo{
  position:fixed;
  inset:0;
  background:rgba(0,20,60,.45);
  backdrop-filter:blur(4px);
  z-index:400;
  display:flex;
  align-items:center;
  justify-content:center;
  opacity:0;
  pointer-events:none;
  transition:opacity .2s;
}
.mo.on{opacity:1;pointer-events:all}
.mo.on .mdl{transform:scale(1)}
.mdl{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--radius-lg);
  width:500px;
  max-width:94vw;
  /* var(--vvh) tracks the real visible height (keyboard-aware.js) so the modal
     shrinks with the on-screen keyboard instead of staying sized to the full
     layout viewport. */
  max-height:calc(var(--vvh, 88vh) - 24px);
  overflow-y:auto;
  transform:scale(.96);
  transition:transform .2s;
  box-shadow:var(--shadow-lg);
}
.mh{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:20px 24px 16px;
  border-bottom:1px solid var(--border);
}
.mt{font-family:var(--font-d);font-size:22px;letter-spacing:.5px;color:var(--text)}
.mx{
  background:none;
  border:none;
  color:var(--muted);
  cursor:pointer;
  font-size:16px;
  padding:4px 8px;
  border-radius:var(--radius-sm);
  transition:all var(--trans);
}
.mx:hover{color:var(--red);background:var(--redlt)}
.mb{padding:20px 24px}
.mf{padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.fg label{font-size:10px;color:var(--sub);letter-spacing:.5px;text-transform:uppercase;font-weight:700}
.fi{
  background:var(--s2);
  border:1.5px solid var(--border2);
  color:var(--text);
  padding:10px 13px;
  border-radius:var(--radius-sm);
  font-size:13px;
  font-family:var(--font-b);
  outline:none;
  width:100%;
  transition:all var(--trans);
}
.fi:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,96,199,.1);background:var(--surface)}
.fi.sel option{background:var(--surface)}
textarea.fi{resize:vertical;min-height:60px}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.acct-cards{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.btn-p{
  padding:9px 22px;
  background:var(--blue);
  color:#fff;
  border:none;
  border-radius:var(--radius-sm);
  font-size:13px;
  font-weight:700;
  cursor:pointer;
  transition:all var(--trans);
  font-family:var(--font-b);
}
.btn-p:hover{background:var(--blue2);box-shadow:0 3px 12px rgba(0,96,199,.25)}
.btn-g{
  padding:9px 22px;
  background:var(--surface);
  color:var(--sub);
  border:1.5px solid var(--border2);
  border-radius:var(--radius-sm);
  font-size:13px;
  cursor:pointer;
  transition:all var(--trans);
  font-family:var(--font-b);
  font-weight:500;
}
.btn-g:hover{border-color:var(--blue);color:var(--blue);background:var(--bluelt)}

/* ─── TOAST ─── */
.toast-w{position:fixed;bottom:22px;right:22px;z-index:999;display:flex;flex-direction:column;gap:8px}
.toast{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--radius-md);
  padding:12px 16px;
  display:flex;
  align-items:center;
  gap:10px;
  font-size:13px;
  color:var(--text);
  transform:translateY(60px);
  opacity:0;
  transition:all .3s;
  box-shadow:var(--shadow-lg);
  max-width:310px;
  font-weight:500;
}
.toast.show{transform:translateY(0);opacity:1}
.tdot{width:8px;height:8px;border-radius:50%;flex-shrink:0}

/* ─── FOOTER ─── */
.site-footer{
  background:linear-gradient(180deg,#070e1a 0%,#050c17 100%);
  border-top:1px solid #1a2a45;
  padding:28px 40px;
}
.footer-inner{
  max-width:1220px;
  margin:0 auto;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:20px;
  flex-wrap:wrap;
}
.footer-brand{
  display:flex;
  align-items:center;
  gap:12px;
}
.footer-logo{
  font-family:var(--font-d);
  font-size:20px;
  letter-spacing:3px;
  color:#60b0ff;
}
.footer-tagline{
  font-size:11.5px;
  color:#3d5a80;
  font-weight:500;
  letter-spacing:.3px;
}
.footer-copy{
  font-size:11.5px;
  color:#2e4a6a;
  letter-spacing:.3px;
}

@media(max-width:900px){
  .hero-inner{grid-template-columns:1fr;padding:72px 36px 60px}
  .hero-right{padding-left:0;border-left:none;padding-top:40px;border-top:1px solid var(--border)}
  .hero-logo-img{height:58px}
  .steps-new{grid-template-columns:1fr 1fr;gap:8px}
  .steps-new::before{display:none}
  .acct-cards{grid-template-columns:1fr}
}
@media(max-width:768px){
  .bk-summary{grid-template-columns:1fr 1fr}
}
@media(max-width:640px){
  .nav-links{display:none}
  .nav-hamburger{display:flex;align-items:center;justify-content:center;width:44px;height:44px;padding:0}
  .nav{padding:0 16px;gap:10px}
  .nav-logo{cursor:pointer}
  .nav-mobile-menu .nl{min-height:44px;display:flex;align-items:center}
  .rl-btn span.rl-label{display:none}
  .rl-btn{padding:8px 10px;min-height:44px}
  .user-chip span.uname{display:none}
  .user-chip{padding:6px 8px;min-height:44px;display:flex;align-items:center}
  .pill{padding:9px 16px;min-height:40px;display:inline-flex;align-items:center}
  .eq-grid{grid-template-columns:1fr 1fr;gap:12px}
  .rl-panel{width:100%;right:-100%}
  .bk-summary{grid-template-columns:1fr 1fr}
  .eq-page-header{flex-direction:row;align-items:center;justify-content:space-between;padding:16px 20px;gap:12px}
  .eq-page-title{font-size:26px;letter-spacing:1px;margin-bottom:0;line-height:1}
  .eq-page-eyebrow{display:none}
  .eq-page-sub{display:none}
  .eq-page-count{padding:6px 14px;font-size:11px;align-self:auto}
  .hero-inner{padding:52px 20px 48px}
  .hero-logo-img{height:48px}
  .hero-logo-wrap{padding:16px 22px;border-radius:10px}
  .steps-new{grid-template-columns:1fr 1fr}
  .hero-stats-strip{flex-wrap:wrap;gap:16px}
  .hstat-mini+.hstat-mini{border-left:none;padding-left:0;margin-left:0}
  .frow{grid-template-columns:1fr}
  .eq-toolbar{flex-wrap:wrap;padding:8px 16px;position:static}
  .cat-pills{width:100%;order:1}
  .search-bar{width:100%;order:2;margin:6px 0 8px;min-height:44px}
  .search-bar input{font-size:16px}
  .eq-toolbar-sep{display:none}
  #eqDetailMo .mdl{flex-direction:column!important;width:92vw!important;max-width:92vw!important;max-height:90vh!important}
  #edImgArea{width:100%!important;height:170px!important;flex-shrink:0!important;border-radius:var(--radius-lg) var(--radius-lg) 0 0!important}

  /* Hamburger menu — slide-in drawer with a tap-outside-to-close backdrop,
     matching the approved mobile design, instead of a dropdown below the nav
     bar. toggleMobileMenu() still just toggles .open on #navMobileMenu —
     the only markup change is one wrapping div (.nav-mobile-drawer) around
     the existing, unchanged nl buttons. */
  .nav-mobile-menu{
    display:flex;position:fixed;inset:0;top:62px;background:rgba(11,26,51,.4);
    align-items:stretch;justify-content:flex-start;
    opacity:0;pointer-events:none;transition:opacity .2s ease;
    padding:0;gap:0;box-shadow:none;border-bottom:none;
  }
  .nav-mobile-menu.open{opacity:1;pointer-events:auto}
  .nav-mobile-drawer{
    width:78%;max-width:300px;height:100%;background:var(--surface);
    padding:12px 0;overflow-y:auto;transform:translateX(-100%);
    transition:transform .25s ease;box-shadow:8px 0 30px rgba(0,30,80,.15);
  }
  .nav-mobile-menu.open .nav-mobile-drawer{transform:translateX(0)}
  .nav-mobile-menu .nl{
    width:100%;text-align:left;padding:13px 20px;min-height:52px;
    font-size:15px;font-weight:700;display:flex;align-items:center;
  }
  .nav-mobile-menu .nl.on{background:var(--bluelt);color:var(--blue)}

  /* Hero — rebuilt as a compact gradient card matching the approved mobile
     design, instead of the desktop full-bleed logo+stats split (logo is
     already shown in the top nav, so it's not repeated here). Same markup,
     CSS only: hero-left's logo/eyebrow hidden, stats become their own row,
     hero-right becomes the gradient card. */
  .hero-inner{display:flex;flex-direction:column;padding:16px}
  .hero-logo-wrap,.hero-left>div:nth-child(2){display:none}
  .hero-left{margin-bottom:0;margin-top:14px;order:2}
  .hero-right{order:1}
  .hero-stats-strip{
    justify-content:space-around!important;margin-top:0!important;
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--radius-md);padding:14px 10px;
  }
  .hstat-mini{align-items:center;text-align:center}
  .hstat-mini-n{font-size:26px}
  .hero-right{
    padding:26px 22px!important;border:none;border-radius:16px;position:relative;overflow:hidden;
    background:linear-gradient(135deg,var(--blue),var(--blue2));
  }
  .hero-right::after{content:"";position:absolute;right:-30px;top:-30px;width:150px;height:150px;border-radius:50%;background:rgba(255,255,255,.08)}
  .hero-tag{display:none}
  .hero-right h1{position:relative;font-size:28px!important;line-height:1.05!important;color:#fff!important;margin-bottom:12px!important}
  .hero-right h1 span{color:#bfe0ff!important}
  .hero-sub{position:relative;font-size:13.5px;color:rgba(255,255,255,.92);opacity:1;max-width:none}
  .hero-acts{position:relative;margin-top:4px}
  .hbtn{min-height:48px}
  .hbtn.blue{background:#fff;color:var(--blue);box-shadow:none}
  .hbtn.ghost{background:rgba(255,255,255,.14);color:#fff;border-color:rgba(255,255,255,.4)}
  .hero-eq-line{display:none}

  /* Equipment tiles — 2-column grid, image-top card kept at every mobile width
     (replaces the old flip-to-horizontal-list-row treatment). Same markup as
     desktop, renderGrid()'s JS is untouched — only these values change.
     Values here match the approved mobile design artifact exactly, not just
     scaled-down desktop values. */
  .eq-card{border-radius:10px}
  .eq-img{background:linear-gradient(135deg,#0B1A33,#1c2f52)}
  .eq-cat-icon{color:rgba(255,255,255,.35)}
  .fav-star{
    top:8px;left:8px;width:28px;height:28px;border-radius:50%;
    background:rgba(255,255,255,.92);border:none;
  }
  .fav-star svg{width:15px;height:15px;stroke:var(--border2);fill:none}
  .fav-star.on svg{stroke:#f5a623;fill:#f5a623}
  .eq-cat-pill{
    bottom:8px;left:8px;background:rgba(11,26,51,.85);
    font-size:9px;padding:3px 8px;border-radius:5px;backdrop-filter:none;border:none;
  }
  .avail-pill{top:8px;right:8px;padding:3px 8px 3px 7px;backdrop-filter:none}
  .avail-pill.av{background:rgba(22,163,74,.92);color:#fff;border:none}
  .avail-pill.av::before{background:#fff}
  .eq-body{padding:10px 11px 11px}
  .eq-name{font-size:13px;font-weight:700;line-height:1.3;min-height:2.4em;margin-bottom:2px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
  .eq-brand{font-size:10.5px;letter-spacing:.04em;margin-bottom:0}
  /* The artifact groups name+brand+op together, then a divider right before
     price — the existing .eq-divider sits right after brand instead (fixed
     markup order), so hide it and put the same rule on .eq-foot instead. */
  .eq-divider{display:none}
  .eq-op{font-size:10px;padding:3px 8px;border-radius:999px;margin:4px 0 0}
  .eq-foot{border-top:1px solid var(--border);margin-top:8px;padding-top:8px}
  .eq-rate{font-family:var(--font-b);font-size:16px;font-weight:700;letter-spacing:normal;margin-bottom:1px}
  .eq-rate-sub{font-size:10px;font-weight:600;margin-bottom:0}
  .req-btn{margin-top:8px;padding:9px;font-size:11.5px;min-height:38px}

  /* My Bookings — card layout instead of a horizontally-scrolled 7-column
     table, matching the artifact's card exactly (ref+title left / status
     badge right, one meta line, total+action footer) via CSS Grid placement
     of the existing <td>s. filterMyBookings() still targets the same
     tr[data-search]/data-status rows — no markup restructuring, just how
     each cell is positioned and styled. */
  .table-wrap{overflow-x:visible;background:transparent!important;border:none!important}
  .bk-tbl,.bk-tbl thead,.bk-tbl tbody{display:block;width:100%}
  .bk-tbl thead{display:none}
  .bk-tbl tbody tr{
    display:grid;grid-template-columns:1fr auto;column-gap:10px;
    background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);
    padding:14px 15px;margin-bottom:10px;
  }
  .bk-tbl tbody tr.row-action td:first-child,.bk-tbl tbody tr.row-pending td:first-child,
  .bk-tbl tbody tr.row-confirmed td:first-child,.bk-tbl tbody tr.row-completed td:first-child{box-shadow:none}
  .bk-tbl td{padding:0;border-bottom:none;max-width:none!important}
  .bk-tbl td[data-label]::before{content:none}
  .bk-tbl td:nth-child(1){grid-column:1;grid-row:1;font-size:12px!important}
  .bk-tbl td:nth-child(2){grid-column:1;grid-row:2;margin-top:2px}
  .bk-tbl td:nth-child(2) div:first-child{font-size:16px!important;white-space:normal!important}
  .bk-tbl td:nth-child(2) div:last-child{display:none}
  .bk-tbl td:nth-child(3){grid-column:1/-1;grid-row:3;font-size:13px!important;margin-top:8px}
  .bk-tbl td:nth-child(3)::after{content:" · ";color:var(--sub)}
  .bk-tbl td:nth-child(4){grid-column:1/-1;grid-row:4;font-size:13px!important;margin-top:0;white-space:normal!important}
  .bk-tbl td:nth-child(5){grid-column:2;grid-row:1/3;align-self:start;justify-self:end}
  .bk-tbl td:nth-child(6){grid-column:1;grid-row:5;font-size:14px!important;margin-top:10px}
  .bk-tbl td:nth-child(7){grid-column:2;grid-row:5;margin-top:10px}
  .bk-cta{min-height:40px;padding:8px 14px}
  #myBkSearch,#myBkStatus{font-size:16px!important;min-height:46px}

  /* Gear-list upload — bigger tap targets */
  .rl-upload-pick,.rl-upload-go{min-height:44px}
  .rl-upload-days input{min-height:44px;font-size:16px}

  /* Account forms — 16px avoids Safari's auto-zoom-on-focus */
  .acct-input{font-size:16px!important;min-height:44px}
}
@media(max-width:420px){
  .eq-grid{gap:10px}
  .eq-body{padding:9px 10px 10px}
}

/* Support chat (client-only, sticky bottom-right) */
.sup-fab{position:fixed;bottom:22px;right:22px;width:44px;height:44px;border-radius:12px;background:var(--surface);border:1.5px solid var(--border2);box-shadow:0 2px 8px rgba(0,30,80,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:900}
.sup-fab svg{width:19px;height:19px;color:var(--blue)}
.sup-fab-badge{position:absolute;top:-3px;right:-3px;width:17px;height:17px;border-radius:50%;background:var(--red);color:#fff;font-size:9.5px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg)}
.sup-overlay{display:none;position:fixed;inset:0;backdrop-filter:blur(6px) saturate(1.05);-webkit-backdrop-filter:blur(6px) saturate(1.05);background:rgba(11,26,51,.14);z-index:950}
.sup-overlay.open{display:block}
.sup-panel{position:fixed;bottom:22px;right:22px;width:320px;max-height:min(70vh,520px);background:var(--surface);border-radius:8px;box-shadow:0 4px 20px rgba(0,30,80,.10);border:1px solid var(--border);display:none;flex-direction:column;overflow:hidden;z-index:960}
.sup-panel.open{display:flex}
.sup-h{padding:11px 16px;display:flex;align-items:center;gap:10px;flex-shrink:0;border-bottom:1px solid var(--border)}
.sup-h .t{font-weight:700;font-size:12.5px;color:var(--text)}
.sup-h .s{font-size:10.5px;color:var(--muted)}
.sup-close{margin-left:auto;width:22px;height:22px;border:none;background:none;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--muted)}
.sup-close svg{width:13px;height:13px}
.sup-body{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;background:var(--surface)}
.sup-cluster{display:flex;flex-direction:column;gap:3px;margin-bottom:3px}
.sup-cluster.me{align-items:flex-end}
.sup-cluster.them{align-items:flex-start}
.sup-bubble{max-width:74%;padding:8px 12px;border-radius:14px;font-size:12.5px;line-height:1.45;white-space:pre-wrap}
.sup-cluster.me .sup-bubble{background:var(--blue);color:#fff;border-bottom-right-radius:4px}
.sup-cluster.them .sup-bubble{background:var(--surface);color:var(--text);border:1px solid var(--border);border-bottom-left-radius:4px}
.sup-meta{font-family:var(--font-m);font-size:9.5px;color:var(--muted);margin:2px 3px 10px}
.sup-empty{text-align:center;color:var(--muted);font-size:12.5px;padding:28px 10px}
.sup-input{display:flex;flex-direction:column;gap:6px;padding:10px 12px;background:var(--surface);border-top:1px solid var(--border);flex-shrink:0}
.sup-input-row{display:flex;gap:8px;align-items:flex-end}
.sup-input textarea{flex:1;border:none;border-bottom:1.5px solid var(--border2);border-radius:0;padding:5px 2px;font-size:12.5px;font-family:var(--font-b);outline:none;background:none;resize:none;max-height:80px}
.sup-input textarea:focus{border-color:var(--blue)}
.sup-send{width:28px;height:28px;border:none;background:none;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;color:var(--blue)}
.sup-send svg{width:16px;height:16px}
.sup-attach-btn{width:28px;height:28px;background:none;border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;color:var(--muted)}
.sup-attach-btn svg{width:16px;height:16px}
.sup-attach-btn:hover{color:var(--blue)}
.sup-file-chip{display:none;align-items:center;gap:6px;background:var(--bluelt);color:var(--blue);border-radius:8px;padding:5px 9px;font-size:11.5px;font-weight:600;max-width:100%}
.sup-file-chip.show{display:flex}
.sup-file-chip svg{width:12px;height:12px;flex-shrink:0}
.sup-file-chip .name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sup-file-chip .rm{margin-left:auto;cursor:pointer;width:16px;height:16px;display:flex;align-items:center;justify-content:center;flex-shrink:0;border-radius:50%;color:var(--blue)}
.sup-file-chip .rm:hover{background:rgba(0,0,0,.08)}
.sup-attach-img{max-width:100%;border-radius:10px;display:block;margin-top:6px;cursor:pointer}
.sup-attach-file{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;margin-top:6px;text-decoration:none}
.sup-cluster.me .sup-attach-file{background:rgba(255,255,255,.15);color:#fff}
.sup-cluster.them .sup-attach-file{background:var(--s2);color:var(--text);border:1px solid var(--border)}
.sup-attach-file svg{width:16px;height:16px;flex-shrink:0}
.sup-attach-file .meta{overflow:hidden}
.sup-attach-file .fname{font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sup-attach-file .fsize{font-size:10px;opacity:.75}
@media(max-width:420px){
  .sup-panel{right:10px;left:10px;width:auto;bottom:86px}
  .sup-fab{right:16px;bottom:16px}
}
</style>
</head>
<body>

<!-- ─── NAV ─── -->
<nav class="nav">
  <button class="nav-hamburger" onclick="toggleMobileMenu()" title="Menu" aria-label="Menu">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
  <div class="nav-logo" onclick="showPage('home',null)"><img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:34px;object-fit:contain;display:block"></div>
  <div class="nav-links">
    <button class="nl on"  onclick="showPage('home',this)">Home</button>
    <button class="nl"     onclick="showPage('equipment',this)">Equipment</button>
    @if($isLoggedIn)
    <button class="nl"     onclick="showPage('mybookings',this)">My Bookings</button>
    @endif
    <button class="nl"     onclick="showPage('help',this)">Help</button>
    @if($isLoggedIn)
    <button class="nl"     onclick="showPage('account',this)">Account</button>
    @endif
  </div>
  <div class="nav-right">
    @if($isLoggedIn)
      <button class="rl-btn" onclick="togglePanel()" title="View your booking request list">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px;flex-shrink:0"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        <span class="rl-label">Request List</span>
        <span class="rl-count" id="rlCount" style="display:none">0</span>
      </button>
      <div class="user-chip" onclick="showPage('account',null)" style="cursor:pointer" title="Account settings">
        <div class="uav">{{ strtoupper(substr($user->first_name ?? 'U', 0, 1)) }}</div>
        <span class="uname">{{ $user->first_name ?? '' }}</span>
      </div>
      <form method="POST" action="{{ route('logout') }}" style="display:contents">@csrf<button type="submit" class="nav-btn">Logout</button></form>
    @else
      <a href="{{ route('login') }}"><button class="nav-btn">Sign In</button></a>
    @endif
  </div>
</nav>
<div class="nav-mobile-menu" id="navMobileMenu" onclick="if(event.target===this)toggleMobileMenu()">
  <div class="nav-mobile-drawer">
    <button class="nl on" onclick="showPage('home',this);toggleMobileMenu()">Home</button>
    <button class="nl" onclick="showPage('equipment',this);toggleMobileMenu()">Equipment</button>
    @if($isLoggedIn)
    <button class="nl" onclick="showPage('mybookings',this);toggleMobileMenu()">My Bookings</button>
    @endif
    <button class="nl" onclick="showPage('help',this);toggleMobileMenu()">Help</button>
    @if($isLoggedIn)
    <button class="nl" onclick="showPage('account',this);toggleMobileMenu()">Account</button>
    @endif
  </div>
</div>

<!-- ════════ HOME ════════ -->
<div id="pg-home" class="pg on">

  <!-- HERO -->
  <div class="hero">
    <div class="hero-grid"></div>
    <div class="hero-orb a"></div>
    <div class="hero-orb b"></div>
    <div class="hero-diag"></div>
    <div class="hero-strip"></div>
    <div class="hero-inner">
      <div class="hero-left">
        <div class="hero-logo-wrap">
          <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" class="hero-logo-img">
        </div>
        <div style="margin-top:14px;font-size:10.5px;color:var(--muted);letter-spacing:2.5px;text-transform:uppercase;font-weight:600">Integrated Film Operations Platform</div>
        <div class="hero-stats-strip" style="justify-content:flex-start;margin-top:40px">
          <div class="hstat-mini">
            <div class="hstat-mini-n">{{ $stats['equip'] }}</div>
            <div class="hstat-mini-l">Available Items</div>
          </div>
          <div class="hstat-mini">
            <div class="hstat-mini-n">{{ $stats['crew'] }}</div>
            <div class="hstat-mini-l">Active Crew</div>
          </div>
          <div class="hstat-mini">
            <div class="hstat-mini-n">{{ $stats['done'] }}</div>
            <div class="hstat-mini-l">Shoots Completed</div>
          </div>
        </div>
      </div>
      <div class="hero-right">
        <div class="hero-tag">Professional Film Production</div>
        <h1 style="font-family:var(--font-d);font-size:clamp(46px,5.5vw,78px);letter-spacing:1px;line-height:.88;color:var(--text);margin-bottom:22px">Everything<span style="color:var(--blue);display:block">Your Production</span>Needs</h1>
        <p class="hero-sub">Access professional cameras, lighting, grip, and sound equipment — plus qualified crew — all in one platform built for Philippine film productions.</p>
        <div class="hero-acts">
          <button class="hbtn blue" onclick="showPage('equipment',null)">Browse Equipment</button>
          @if($isLoggedIn)
          <button class="hbtn ghost" onclick="showPage('mybookings',null)">My Bookings</button>
          @else
          <a href="{{ route('login') }}"><button class="hbtn ghost">Sign In to Book</button></a>
          @endif
        </div>
        <div class="hero-eq-line" style="margin-top:24px">Film Gear &nbsp;&middot;&nbsp; Rental &nbsp;&middot;&nbsp; Made Easy</div>
      </div>
    </div>
  </div>

  <!-- HOW IT WORKS -->
  <div class="hiw-section">
    <div class="ccon" style="max-width:1100px">
      <div style="text-align:center;margin-bottom:36px">
        <div class="sec-label">Simple Process</div>
        <div class="sec-title">How It Works</div>
        <div class="sec-sub">Four steps from browsing to a confirmed shoot</div>
      </div>
      <div class="steps-new">
        @php
          $steps = [
            ['#E5F0FF','#0060C7','<rect x="2" y="7" width="20" height="15" rx="2"/><path d="M16 3l-4 4-4-4"/><circle cx="12" cy="14" r="3"/>','Browse Equipment','Browse our catalog by category. Each item shows daily rate, availability, and operator requirements.'],
            ['#f5f3ff','#7c3aed','<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>','Build Request List','Add items to your Request List. Adjust shoot days and watch the cost estimate update in real time.'],
            ['#ecfeff','#0891b2','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>','Review Estimate','Get a full cost breakdown — equipment, crew, and transport — before you commit to anything.'],
            ['#dcfce7','#15803d','<polyline points="20 6 9 17 4 12"/>','Submit &amp; Confirm','Submit your request. FilmSpec confirms within 24 hours with crew assignments and shoot logistics.'],
          ];
        @endphp
        @foreach ($steps as $i => $s)
        <div class="step-new">
          <div class="step-new-icon" style="background:{{ $s[0] }};color:{{ $s[1] }}">
            <div class="step-new-badge">{{ $i + 1 }}</div>
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $s[2] !!}</svg>
          </div>
          <div class="step-new-title">{{ $s[3] }}</div>
          <div class="step-new-desc">{!! $s[4] !!}</div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  <!-- FEATURED EQUIPMENT -->
  <div class="ccon" style="max-width:1220px;padding-top:48px;padding-bottom:52px">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:24px;gap:16px;flex-wrap:wrap">
      <div>
        <div class="sec-label">Top Picks</div>
        <div class="sec-title">Featured Equipment</div>
        <div class="sec-sub" style="margin-bottom:0">Hand-picked gear for your next production</div>
      </div>
      <button class="hbtn blue" onclick="showPage('equipment',null)" style="font-size:13px;padding:11px 22px;flex-shrink:0">Browse All &rarr;</button>
    </div>
    <div class="eq-grid">
      @foreach ($equipment->take(6) as $eq)
      @php
        $av = $eq->availability_status === 'available';
        $isBooked = $eq->availability_status === 'booked';
        $availClass = $av ? 'av' : ($isBooked ? 'busy' : 'inuse');
        $availText  = $av ? 'Available' : ($isBooked ? 'Booked' : 'In Use');
      @endphp
      <div class="eq-card">
        <div class="eq-img" onclick="openEqDetail({{ $eq->equipment_id }})" style="cursor:pointer" title="View details">
          @if ($eq->image_path)
            <img src="{{ asset('storage/' . $eq->image_path) }}" alt="">
          @else
            <span class="eq-cat-icon">{{ strtoupper(substr($eq->category_name ?? '', 0, 2)) }}</span>
          @endif
          <span class="eq-cat-pill">{{ $eq->category_name }}</span>
          <span class="avail-pill {{ $availClass }}">{{ $availText }}</span>
        </div>
        <div class="eq-body">
          <div class="eq-name">{{ $eq->equipment_name }}</div>
          <div class="eq-brand">{{ $eq->brand ?? '' }}</div>
          <div class="eq-divider"></div>
          @if ($eq->requires_operator ?? 0)<div class="eq-op">Operator req'd</div>@endif
          <div class="eq-foot">
            <div class="eq-rate">₱{{ number_format($eq->daily_rate, 0) }}</div>
            <div class="eq-rate-sub">/day &middot; VAT included</div>
          </div>
          @if ($isLoggedIn)
          <button class="req-btn" id="rb_{{ $eq->equipment_id }}" data-eid="{{ $eq->equipment_id }}" onclick="toggleEquipment({{ $eq->equipment_id }})">+ Add to Request List</button>
          @else
          <button class="req-btn" onclick="requireAuth()">+ Add to Request List</button>
          @endif
        </div>
      </div>
      @endforeach
    </div>
  </div>

  @if ($publicReviews->isNotEmpty())
  <!-- WHAT OUR CLIENTS SAY -->
  <div class="ccon" style="max-width:1220px;padding-top:8px;padding-bottom:56px">
    <div class="sec-label">Client Feedback</div>
    <div class="sec-title">What Our Clients Say</div>
    <div class="sec-sub" style="margin-bottom:24px">Real reviews from completed productions</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">
      @foreach ($publicReviews as $r)
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;display:flex;flex-direction:column">
        <div style="color:#f59e0b;font-size:15px;letter-spacing:2px;margin-bottom:10px">{{ str_repeat('★', $r->rating) }}<span style="color:var(--border2)">{{ str_repeat('★', 5 - $r->rating) }}</span></div>
        <div style="font-size:13.5px;color:var(--text);line-height:1.65;margin-bottom:16px;flex:1">&ldquo;{{ $r->comment }}&rdquo;</div>
        <div style="font-size:12px;font-weight:700;color:var(--sub)">
          {{ $r->first_name }} {{ $r->last_name ? strtoupper(substr($r->last_name, 0, 1)) . '.' : '' }}
          <span style="font-weight:500;color:var(--muted)">&middot; {{ \Illuminate\Support\Carbon::parse($r->submitted_at)->format('M Y') }}</span>
        </div>
      </div>
      @endforeach
    </div>
  </div>
  @endif

</div>

<!-- ════════ EQUIPMENT PAGE ════════ -->
<div id="pg-equipment" class="pg">
  <div class="eq-page-header">
    <div class="eq-ph-inner">
      <div class="eq-ph-left">
        <div class="eq-page-eyebrow">FilmSpec Productions</div>
        <div class="eq-page-title">Equipment<br>Catalog</div>
        <div class="eq-page-sub">Premium production gear · Daily rates · VAT inclusive</div>
      </div>
      <span class="eq-page-count">{{ count($equipment) }} items available</span>
    </div>
  </div>
  <div class="eq-toolbar">
    <div class="cat-pills">
      <button class="pill on" onclick="filterCat(0,this)">All Items</button>
      @foreach ($categories as $cat)
      <button class="pill" onclick="filterCat({{ $cat->category_id }},this)">{{ $cat->category_name }}</button>
      @endforeach
      @if($isLoggedIn)
      <button class="pill fav-pill" id="favPill" onclick="toggleFavFilter(this)">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Favorites
      </button>
      @endif
    </div>
    <div class="eq-toolbar-sep"></div>
    <div class="search-bar">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8aa4be" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="eqSearch" placeholder="Search by name or brand…" oninput="filterSearch(this.value)">
    </div>
  </div>
  <div style="max-width:1220px;margin:0 auto;padding:28px 24px 52px">
    <div class="eq-grid" id="eqGrid">
      <!-- populated by JS -->
    </div>
  </div>
</div>

<!-- ════════ CREW PAGE ════════ -->
<div id="pg-crew" class="pg">
  <div class="ccon">
    <div class="sec-title">Crew Information</div>
    <div class="sec-sub">Professional crew members are automatically assigned when you book equipment that requires operators. Admin will assign the best available crew based on your project needs.</div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:40px;text-align:center">
      <div style="font-family:var(--font-d);font-size:22px;letter-spacing:.5px;margin-bottom:10px;color:var(--blue)">Crew Assignment</div>
      <div style="font-size:13px;color:var(--sub);margin-bottom:24px;line-height:1.7;max-width:480px;margin-left:auto;margin-right:auto">
        When you select equipment that requires operators, our admin team will assign qualified crew members for your project. You'll see the crew assignments in your booking confirmation.
      </div>
      <button class="hbtn blue" onclick="showPage('equipment',null)">Browse Equipment</button>
    </div>
  </div>
</div>

<!-- ════════ MY BOOKINGS ════════ -->
@if($isLoggedIn)
<div id="pg-mybookings" class="pg">
  <div class="ccon">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:12px">
      <div>
        <div class="sec-title">My Bookings</div>
        <div class="sec-sub" style="margin-bottom:0">Track your submitted requests and booking status</div>
      </div>
      <button class="hbtn blue" onclick="togglePanel()" style="font-size:13px;padding:11px 20px">+ New Booking Request</button>
    </div>

    @if(count($clientBookings))
    @php
      $pending   = collect($clientBookings)->where('booking_status', 'pending')->count();
      $confirmed = collect($clientBookings)->where('booking_status', 'confirmed')->count();
      $completed = collect($clientBookings)->where('booking_status', 'completed')->count();
    @endphp
    <div class="bk-summary" style="margin-top:20px">
      <div class="bk-stat">
        <div class="bk-stat-icon" style="background:var(--bluelt)"><svg viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
        <div><div class="bk-stat-n">{{ count($clientBookings) }}</div><div class="bk-stat-l">Total Bookings</div></div>
      </div>
      <div class="bk-stat c-pending">
        <div class="bk-stat-icon" style="background:var(--yellowlt)"><svg viewBox="0 0 24 24" fill="none" stroke="var(--yellow)" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></div>
        <div><div class="bk-stat-n">{{ $pending }}</div><div class="bk-stat-l">Pending Review</div></div>
      </div>
      <div class="bk-stat c-confirmed">
        <div class="bk-stat-icon" style="background:var(--bluelt)"><svg viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg></div>
        <div><div class="bk-stat-n">{{ $confirmed }}</div><div class="bk-stat-l">Confirmed</div></div>
      </div>
      <div class="bk-stat c-completed">
        <div class="bk-stat-icon" style="background:var(--greenlt)"><svg viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg></div>
        <div><div class="bk-stat-n">{{ $completed }}</div><div class="bk-stat-l">Completed</div></div>
      </div>
    </div>
    @endif

    @if(count($clientBookings) === 0)
    <div class="empty-state" style="margin-top:20px">
      <div class="empty-icon">&#128247;</div>
      <h3>No bookings yet</h3>
      <p>Browse equipment and add items to your request list, then submit your booking request.</p>
      <button class="hbtn blue" onclick="showPage('equipment',null)">Browse Equipment</button>
    </div>
    @else
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px">
      <div style="position:relative;flex:1;min-width:200px">
        <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted)" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" id="myBkSearch" placeholder="Search reference or project…" oninput="filterMyBookings()"
               style="width:100%;background:var(--surface);border:1.5px solid var(--border);color:var(--text);padding:9px 12px 9px 34px;border-radius:var(--radius-md);font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
      </div>
      <select id="myBkStatus" onchange="filterMyBookings()"
              style="background:var(--surface);border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:var(--radius-md);font-size:13px;font-family:var(--font-b);outline:none">
        <option value="">All Status</option>
        <option value="pending">Pending Review</option>
        <option value="confirmed">Confirmed</option>
        <option value="ongoing">Ongoing</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>
    <div class="table-wrap" style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);margin-top:12px">
      <table class="bk-tbl">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Project</th>
            <th>Items</th>
            <th>Dates</th>
            <th>Status</th>
            <th>Total</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="myBkTbody">
          @foreach($clientBookings as $bk)
          @php
            $needsApproval = ($bk->cost_approval_status ?? '') === 'pending_client';
            $rowClass = $needsApproval ? 'row-action' : ('row-' . $bk->booking_status);
          @endphp
          <tr class="{{ $rowClass }}" data-search="{{ strtolower($bk->booking_reference . ' ' . ($bk->project_title ?: '')) }}" data-status="{{ $bk->booking_status }}">
            <td data-label="Reference"><strong style="font-family:var(--font-m);font-size:.8rem;color:var(--blue)">{{ $bk->booking_reference }}</strong></td>
            <td data-label="Project" style="max-width:160px">
              <div style="font-weight:700;font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $bk->project_title ?: 'Booking Request' }}</div>
              <div style="font-size:.72rem;color:var(--muted);margin-top:1px">{{ ucfirst(str_replace('_', ' ', $bk->booking_type)) }}</div>
            </td>
            <td data-label="Items" style="font-size:.8rem;color:var(--sub)">{{ $bk->equip_count }} equip &middot; {{ $bk->crew_count }} crew</td>
            <td data-label="Dates" style="font-size:.78rem;color:var(--sub);white-space:nowrap">{{ \Carbon\Carbon::parse($bk->shoot_date_start)->format('M j') }} – {{ \Carbon\Carbon::parse($bk->shoot_date_end)->format('M j') }}</td>
            <td data-label="Status">
              @if ($needsApproval)
              <span class="bk-pill action"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg> Action Required</span>
              @elseif ($bk->booking_status === 'pending')
              <span class="bk-pill pending"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg> Pending Review</span>
              @elseif ($bk->booking_status === 'completed')
              <span class="bk-pill completed"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg> Completed</span>
              @elseif (in_array($bk->booking_status, ['confirmed', 'ongoing'], true))
              <span class="bk-pill confirmed"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg> {{ ucfirst($bk->booking_status) }}</span>
              @else
              <span class="bk-pill" style="background:var(--s3);color:var(--sub)">{{ ucfirst($bk->booking_status) }}</span>
              @endif
            </td>
            <td data-label="Total" style="font-family:var(--font-m);font-weight:700;color:var(--blue)">
              @if ($needsApproval)
              <span style="color:var(--orange);font-size:.78rem;font-family:var(--font-b)">Review cost</span>
              @elseif ($bk->final_amount > 0)
              &#8369;{{ number_format($bk->final_amount, 2) }}
              @else
              <span style="color:var(--muted);font-size:.78rem;font-family:inherit">TBD</span>
              @endif
            </td>
            <td style="white-space:nowrap;display:flex;gap:6px;align-items:center">
              <a href="{{ route('client-booking-detail', $bk->booking_id) }}" class="bk-cta {{ $needsApproval ? 'warn' : '' }}">
                {{ $needsApproval ? 'Review' : 'View' }}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
              </a>
              @if ($bk->booking_status === 'completed')
              <button type="button" class="bk-cta" style="background:var(--s3);color:var(--sub);border:none;cursor:pointer;font-family:inherit" onclick="bookAgain({{ $bk->booking_id }},this)" title="Add this booking's equipment back to your cart">
                Book Again
              </button>
              @endif
            </td>
          </tr>
          @endforeach
          <tr id="myBkNoMatch" style="display:none"><td colspan="7" style="text-align:center;color:var(--muted);padding:20px">No bookings match your search.</td></tr>
        </tbody>
      </table>
    </div>
    @endif
  </div>
</div>
@endif

<!-- ─── REQUEST LIST PANEL ─── -->
<div class="rl-overlay" id="rlOverlay" onclick="togglePanel()"></div>
<div class="rl-panel" id="rlPanel">
  <div class="rl-head">
    <div class="rl-title">Request List</div>
    <button class="rl-close" onclick="togglePanel()">&times;</button>
  </div>
  @if($isLoggedIn)
  <div class="rl-upload" id="rlUpload">
    <div id="rlUploadForm">
      <div class="rl-upload-title">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V4M12 4l-4 4M12 4l4 4"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
        Upload Gear List
      </div>
      <div class="rl-upload-sub">Upload your gear list (Word, Excel, or CSV) and matching items are added here automatically.</div>
      <div class="rl-upload-row">
        <label class="rl-upload-pick" id="rlUploadPick" for="rlGearFile">Choose file…</label>
        <input type="file" id="rlGearFile" accept=".doc,.docx,.xlsx,.xls,.csv,.txt" style="display:none" onchange="onGearFilePicked(this)">
        <div class="rl-upload-days">
          <span>Days</span>
          <input type="number" id="rlGearDays" value="1" min="1">
        </div>
      </div>
      <button class="rl-upload-go" id="rlUploadGo" disabled onclick="uploadGearList()">Upload &amp; Match</button>
    </div>
    <div id="rlUploadProcessed" class="rl-upload-processed" style="display:none">
      <div class="rl-upload-processed-name" id="rlUploadProcessedName"></div>
      <button class="rl-upload-again" onclick="resetGearUpload()">Upload another</button>
    </div>
  </div>
  @endif
  <div class="rl-body" id="rlBody">
    <div class="rl-empty">
      <div class="ico">&#128247;</div>
      <h3>Your request list is empty</h3>
      <p>Browse equipment and click "Add to Request List" on items you need for your production.</p>
    </div>
  </div>
  <div id="rlFlaggedBox" class="rl-flagged" style="display:none"></div>
  <div class="rl-foot">
    <div id="ceSumBox" style="display:none"></div>
    @if($isLoggedIn)
    <button class="proc-btn" id="procBtn" disabled onclick="goToEstimate()">View Cost Estimate &amp; Submit &rarr;</button>
    @else
    <button class="proc-btn" onclick="requireAuth()">Sign In to Book</button>
    @endif
  </div>
</div>

<!-- ════════ HELP / FAQ ════════ -->
<div id="pg-help" class="pg">
  <div class="ccon">
    <div class="sec-title">Help Center</div>
    <div class="sec-sub">Answers to common questions about pricing, booking, payments, and equipment.</div>

    <div class="eq-toolbar" style="margin-top:18px;margin-bottom:22px">
      <div class="cat-pills" id="faqCatPills">
        <button class="pill on" onclick="filterFaqCat('',this)">All Topics</button>
      </div>
      <div class="eq-toolbar-sep"></div>
      <div class="search-bar">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8aa4be" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="faqSearch" placeholder="Search a question…" oninput="filterFaqSearch(this.value)">
      </div>
    </div>

    <div id="faqList" style="display:flex;flex-direction:column;gap:10px"></div>

    <div id="faqEmpty" class="empty-state" style="display:none">
      <div class="empty-icon">&#10067;</div>
      <h3>No matching questions</h3>
      <p>Try a different search term or topic, or reach out through the support chat.</p>
    </div>
  </div>
</div>

<!-- ════════ ACCOUNT ════════ -->
@if($isLoggedIn)
<div id="pg-account" class="pg">
  <div class="ccon">
    <div class="sec-title">Account Settings</div>
    <div class="sec-sub">Manage your profile and password</div>

    @if($accountMsg)
    <div style="padding:12px 16px;border-radius:8px;margin-bottom:18px;font-size:13px;font-weight:600;
                background:{{ $accountMsg['type'] === 'success' ? '#dcfce7' : '#fee2e2' }};
                color:{{ $accountMsg['type'] === 'success' ? '#15803d' : '#b91c1c' }}">
      {{ $accountMsg['text'] }}
    </div>
    @endif

    @php
      $acctInitials = strtoupper(mb_substr($user->first_name, 0, 1) . mb_substr($user->last_name, 0, 1));
    @endphp
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:22px 26px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;margin-bottom:18px">
      <div style="width:56px;height:56px;border-radius:50%;background:var(--bluelt);color:var(--blue);display:flex;align-items:center;justify-content:center;font-family:var(--font-d);font-size:22px;letter-spacing:.5px;flex-shrink:0">{{ $acctInitials }}</div>
      <div style="flex:1;min-width:220px">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <div style="font-size:18px;font-weight:700;color:var(--text)">{{ $user->first_name }} {{ $user->last_name }}</div>
          <div style="font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--blue);background:var(--bluelt);border-radius:20px;padding:3px 10px">Client</div>
        </div>
        <div style="display:flex;align-items:center;gap:16px;margin-top:6px;flex-wrap:wrap">
          <div style="display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--sub)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v12H4z"/><path d="M4 7l8 6 8-6"/></svg>
            {{ $user->email }}
          </div>
          @if($user->phone)
          <div style="display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--sub)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            {{ $user->phone }}
          </div>
          @endif
        </div>
      </div>
    </div>

    <div class="acct-cards">
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px">
        <div style="font-family:var(--font-d);font-size:18px;letter-spacing:.3px;margin-bottom:4px">Profile Info</div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:18px">Your name and phone number</div>
        <form method="POST" action="{{ route('home') }}">
          @csrf
          <input type="hidden" name="action" value="update_profile">
          <div class="frow" style="margin-bottom:14px">
            <div>
              <label class="form-label">First Name</label>
              <input type="text" name="first_name" value="{{ $user->first_name }}" required class="acct-input"
                     style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
            </div>
            <div>
              <label class="form-label">Last Name</label>
              <input type="text" name="last_name" value="{{ $user->last_name }}" required class="acct-input"
                     style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
            </div>
          </div>
          <div style="margin-bottom:14px">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" value="{{ $user->phone }}" placeholder="09171234567" class="acct-input"
                   style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
          </div>
          <div style="margin-bottom:18px">
            <label class="form-label">Email</label>
            <input type="text" value="{{ $user->email }}" disabled class="acct-input"
                   style="width:100%;background:var(--s2);border:1.5px solid var(--border);color:var(--muted);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
            <div style="font-size:11px;color:var(--muted);margin-top:4px">Contact support to change your email.</div>
          </div>
          <button type="submit"
                  style="background:var(--blue);color:#fff;border:none;padding:10px 22px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font-b)">
            Save Changes
          </button>
        </form>
      </div>

      <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px">
        <div style="font-family:var(--font-d);font-size:18px;letter-spacing:.3px;margin-bottom:4px">Change Password</div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:18px">Use at least 8 characters</div>
        <form method="POST" action="{{ route('home') }}">
          @csrf
          <input type="hidden" name="action" value="change_password">
          <div style="margin-bottom:14px">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" required class="acct-input"
                   style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
          </div>
          <div class="frow" style="margin-bottom:18px">
            <div>
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" required minlength="8" class="acct-input"
                     style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
            </div>
            <div>
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_password" required minlength="8" class="acct-input"
                     style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
            </div>
          </div>
          <button type="submit"
                  style="background:var(--blue);color:#fff;border:none;padding:10px 22px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font-b)">
            Change Password
          </button>
        </form>
      </div>

      <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px">
        <div style="font-family:var(--font-d);font-size:18px;letter-spacing:.3px;margin-bottom:4px">Privacy &amp; Data</div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:18px;line-height:1.6">
          Under the Data Privacy Act (R.A. 10173), you may request that we erase your personal
          account information. Your booking and payment history is kept for legal/accounting
          reasons, but your name, email, and contact details are anonymized and your account is
          deactivated. This cannot be undone.
        </div>
        @if ($erasurePending)
        <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:7px;padding:10px 13px;font-size:12.5px;line-height:1.5">
          Your data erasure request is pending review by our team.
        </div>
        @else
        <form method="POST" action="{{ route('home') }}"
              onsubmit="return confirm('Request erasure of your personal account data? Your name, email, and phone will be anonymized and your account deactivated. This cannot be undone. Continue?')">
          @csrf
          <input type="hidden" name="action" value="request_data_erasure">
          <textarea name="erasure_reason" rows="2" placeholder="Reason (optional)"
                    style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box;margin-bottom:12px;resize:vertical"></textarea>
          <button type="submit"
                  style="background:#fff;color:#dc2626;border:1.5px solid #dc2626;padding:10px 22px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font-b)">
            Request Account Data Erasure
          </button>
        </form>
        @endif
      </div>
    </div>
  </div>
</div>
@endif

<!-- AUTH MODAL -->
<div class="mo" id="authMo">
  <div class="mdl" style="max-width:360px;text-align:center">
    <div class="mb" style="padding:36px 28px">
      <div style="font-family:var(--font-d);font-size:26px;letter-spacing:.5px;margin-bottom:10px">Sign In Required</div>
      <div style="font-size:13px;color:var(--sub);margin-bottom:22px;line-height:1.7">Create a free client account or sign in to add items to your request list and submit bookings.</div>
      <div style="display:flex;gap:10px;justify-content:center">
        <button class="btn-g" onclick="closeMo('authMo')">Cancel</button>
        <a href="{{ route('login') }}"><button class="btn-p">Sign In / Register</button></a>
      </div>
    </div>
  </div>
</div>

<!-- SUCCESS MODAL -->
<div class="mo" id="successMo">
  <div class="mdl" style="max-width:400px;text-align:center">
    <div class="mb" style="padding:40px 32px">
      <div style="font-family:var(--font-d);font-size:30px;letter-spacing:.5px;margin-bottom:10px;color:var(--blue)">Booking Submitted!</div>
      <div style="font-size:13px;color:var(--sub);margin-bottom:18px;line-height:1.7">Your request has been submitted. FilmSpec will confirm within 24 hours.</div>
      <div id="successRef" style="font-family:var(--font-m);font-size:17px;color:var(--blue);background:var(--bluelt);border-radius:var(--radius-sm);padding:12px;margin-bottom:22px;border:1px solid var(--bluemid)"></div>
      <button class="btn-p" style="width:100%;padding:12px" onclick="closeMo('successMo');showPage('mybookings',null);location.reload()">View My Bookings</button>
    </div>
  </div>
</div>

<!-- ══ EQUIPMENT DETAIL MODAL ══ -->
<div class="mo" id="eqDetailMo">
  <div class="mdl" style="max-width:880px;width:95vw;max-height:88vh;overflow:hidden;display:flex;flex-direction:row;padding:0;align-items:stretch">

    <div id="edImgArea" style="width:320px;flex-shrink:0;position:relative;background:#0f172a;overflow:hidden;display:flex;align-items:center;justify-content:center;border-radius:var(--radius-lg) 0 0 var(--radius-lg)">
      <img id="edImg" src="" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none">
      <span id="edImgFallback" style="font-size:64px;font-weight:900;letter-spacing:-3px;color:rgba(255,255,255,.14);font-family:var(--font-d)"></span>
      <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.82) 0%,rgba(0,0,0,.08) 55%,transparent 100%)"></div>
      <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,.35) 0%,transparent 28%)"></div>
      <div style="position:absolute;top:14px;left:14px;right:14px;display:flex;gap:6px;flex-wrap:wrap">
        <span id="edCatPill" style="font-size:.6rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;background:rgba(255,255,255,.16);color:#fff;border-radius:20px;padding:3px 10px;backdrop-filter:blur(6px)"></span>
        <span id="edAvailPill" style="font-size:.6rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;border-radius:20px;padding:3px 10px;backdrop-filter:blur(6px)"></span>
      </div>
      <div style="position:absolute;bottom:0;left:0;right:0;padding:18px 18px 22px">
        <div id="edName" style="font-family:var(--font-d);font-size:1.3rem;font-weight:800;letter-spacing:.3px;color:#fff;text-shadow:0 1px 6px rgba(0,0,0,.6);line-height:1.2"></div>
        <div id="edBrand" style="font-size:.75rem;color:rgba(255,255,255,.6);margin-top:4px;text-transform:uppercase;letter-spacing:.06em"></div>
      </div>
    </div>

    <div style="flex:1;min-width:0;display:flex;flex-direction:column;overflow:hidden">

      <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border);flex-shrink:0;gap:12px">
        <div style="display:flex;align-items:baseline;gap:6px;flex-wrap:wrap">
          <span id="edRate" style="font-size:1.55rem;font-weight:900;color:var(--blue);font-family:var(--font-d)"></span>
          <span style="font-size:.72rem;color:var(--sub)">/day <span style="font-size:.62rem;opacity:.7">VAT incl.</span></span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
          <div id="edOpNote" style="display:none;font-size:.7rem;font-weight:600;color:var(--sub);background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:3px 10px;white-space:nowrap"></div>
          <button onclick="closeMo('eqDetailMo')" style="background:var(--surface);border:1px solid var(--border);color:var(--sub);width:28px;height:28px;border-radius:50%;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;flex-shrink:0">&times;</button>
        </div>
      </div>

      <div style="overflow-y:auto;flex:1;padding:18px 20px 4px">

        <div id="edDescWrap" style="display:none;margin-bottom:18px">
          <div style="font-size:.63rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--sub);margin-bottom:6px">About this equipment</div>
          <p id="edDesc" style="font-size:.875rem;color:var(--text);line-height:1.65;margin:0"></p>
        </div>

        <div style="font-size:.63rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--sub);margin-bottom:10px">Accessories</div>
        <div id="edAccLoading" style="padding:14px 0;color:var(--sub);font-size:.83rem">Loading…</div>
        <div id="edAccContent" style="display:none">
          <div id="edIncludedWrap" style="margin-bottom:14px;display:none">
            <div style="font-size:.62rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--sub);margin-bottom:6px">Included in rental</div>
            <div id="edIncludedList" style="display:flex;flex-direction:column;gap:5px"></div>
          </div>
          <div id="edOptionalWrap" style="margin-bottom:14px;display:none">
            <div style="font-size:.62rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--sub);margin-bottom:6px">Optional add-ons <span style="font-weight:400;text-transform:none;letter-spacing:0">(select to include in your request)</span></div>
            <div id="edOptionalList" style="display:flex;flex-direction:column;gap:6px"></div>
          </div>
          <div id="edNoAccNote" style="font-size:.82rem;color:var(--sub);display:none;padding:4px 0 10px">No accessories linked to this item.</div>
        </div>

        <div style="height:1px;background:var(--border);margin:12px 0"></div>
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:13px 15px;margin-bottom:8px">
          <div style="font-size:.62rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--sub);margin-bottom:9px">Daily Rate Summary</div>
          <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:6px">
            <span style="color:var(--sub)" id="edPriceLabelEq">Equipment</span>
            <span style="font-weight:600" id="edPriceEq">—</span>
          </div>
          <div id="edPriceOptRows"></div>
          <div style="height:1px;background:var(--border);margin:8px 0"></div>
          <div style="display:flex;justify-content:space-between;font-size:.88rem">
            <span style="font-weight:700">Estimated Daily Total</span>
            <span style="font-weight:800;color:var(--blue)" id="edPriceTotal">—</span>
          </div>
          <div style="font-size:.63rem;color:var(--sub);margin-top:4px">VAT included · Crew talent fee billed separately · Final pricing confirmed on booking</div>
        </div>
      </div>

      <div style="padding:14px 20px;border-top:1px solid var(--border);flex-shrink:0">
        <button id="edActionBtn" style="width:100%;padding:12px;border-radius:10px;font-size:.92rem;font-weight:700;border:none;cursor:pointer;font-family:var(--font-d);letter-spacing:.3px;transition:background .15s"></button>
      </div>
    </div>

  </div>
</div>

<div class="toast-w" id="toastW"></div>

<!-- ─── JAVASCRIPT ─── -->
<script>
const IS_LOGIN  = {{ $isLoggedIn ? 'true' : 'false' }};
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
// Uploaded equipment/accessory images still live on the legacy app until it's fully retired.
const ASSET_BASE = "{{ asset('storage') }}";

// All equipment as JS data
const ALL_EQ = {!! $equipment->map(fn ($e) => [
    'id' => (int) $e->equipment_id,
    'name' => $e->equipment_name,
    'brand' => $e->brand ?? '',
    'cat_id' => (int) $e->category_id,
    'cat' => $e->category_name,
    'rate' => (float) $e->daily_rate,
    'avail' => $e->availability_status,
    'img' => $e->image_path ?? '',
    'req_op' => (int) ($e->requires_operator ?? 0),
    'op_pos' => $e->operator_positions ?? '',
    'desc' => $e->description ?? '',
])->values()->toJson(JSON_HEX_TAG | JSON_HEX_APOS) !!};

const EMOJIS = {};

// All active FAQs as JS data (flattened — category stays on each row)
const ALL_FAQS = {!! $faqs->flatten(1)->map(fn ($f) => [
    'id' => (int) $f->faq_id,
    'q' => $f->question,
    'a' => $f->answer,
    'cat' => $f->category,
])->values()->toJson(JSON_HEX_TAG | JSON_HEX_APOS) !!};

let reqList = [];
let panelOpen = false;
let activeCat   = 0;
let searchTerm  = '';
let activeFaqCat = '';
let faqSearchTerm = '';
let favIds = [];
let showFavsOnly = false;

function showPage(id, el) {
  document.querySelectorAll('.pg').forEach(p=>p.classList.remove('on'));
  document.getElementById('pg-'+id)?.classList.add('on');
  document.querySelectorAll('.nl').forEach(b=>b.classList.remove('on'));
  if (el) el.classList.add('on');
  window.scrollTo(0,0);
}

function toggleMobileMenu() {
  document.getElementById('navMobileMenu')?.classList.toggle('open');
}

function togglePanel() {
  panelOpen = !panelOpen;
  document.getElementById('rlPanel').classList.toggle('open', panelOpen);
  document.getElementById('rlOverlay').classList.toggle('on', panelOpen);
  if (panelOpen) loadList();
}

function loadList() {
  if (!IS_LOGIN) return;
  fetch('/cart?action=get')
    .then(r=>r.json())
    .then(d => {
      reqList = d.items || [];
      renderPanel(d.required_operators||{});
      updateCount();
      updateBtns();
    });
}

function updateCount() {
  const el = document.getElementById('rlCount');
  if (!el) return;
  if (reqList.length) { el.textContent=reqList.length; el.style.display='flex'; }
  else el.style.display='none';
}

function renderPanel(reqOps) {
  const body = document.getElementById('rlBody');
  if (!reqList.length) {
    body.innerHTML = '<div class="rl-empty"><div class="ico">&#128247;</div><h3>Request list is empty</h3><p>Browse equipment and add items you need for your production.</p></div>';
    document.getElementById('ceSumBox').style.display='none';
    const pb = document.getElementById('procBtn'); if(pb) pb.disabled=true;
    return;
  }

  const fmt = n=>n.toLocaleString('en-PH',{minimumFractionDigits:2});
  let html = '';
  let equipSubtotal = 0;

  const eqInList = reqList.filter(i=>i.item_type==='equipment');
  eqInList.forEach(item=>{
    const rate  = parseFloat(item.daily_rate||0);
    const days  = parseInt(item.days||1);
    const qty   = parseInt(item.quantity||1);
    const sub   = rate*days*qty;
    equipSubtotal += sub;
    const img   = item.image_path||'';
    const name  = item.equipment_name;
    const sub2  = item.category_name||'';
    const catAbbr = (item.category_name||'').substring(0,2).toUpperCase();

    let crewEstHtml = '';
    if (parseInt(item.requires_operator)) {
      const ops = reqOps[item.equipment_id]||[];
      ops.forEach(op=>{
        crewEstHtml += `<div class="rli-crew-est">
          <span class="crew-est-icon">&#128100;</span>
          <span>${op.position_name} TF</span>
          <span class="rli-amount crew-est-amt" style="color:var(--muted);font-size:11px;font-weight:600">TBD</span>
        </div>`;
      });
    }

    html += `<div class="rli">
      <div class="rli-img">${img?`<img src="${ASSET_BASE}/${img}" alt="">`:(`<span class="eq-cat-icon">${catAbbr}</span>`)}</div>
      <div class="rli-info">
        <div class="rli-name">${name}</div>
        <div class="rli-sub">${sub2}</div>
        <div class="rli-calc">
          ${qty>1?`<span style="font-size:11px;color:var(--muted)">×${qty}</span>`:''}
          <span class="rli-amount">₱${fmt(sub)}</span>
        </div>
        ${crewEstHtml}
      </div>
      <button class="rli-remove" onclick="removeItem(${item.cart_id})" title="Remove">&times;</button>
    </div>`;
  });

  body.innerHTML = html;

  const box = document.getElementById('ceSumBox');
  box.style.display='block';
  box.innerHTML=`<div class="ce-sum">
    <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;font-weight:700">Estimated Cost</div>
    <div class="ce-row"><span>Equipment (VAT incl.)</span><strong>₱${fmt(equipSubtotal)}</strong></div>
    <div class="ce-row"><span>Crew TF</span><strong style="color:var(--muted);font-size:11px">TBD — assigned after booking</strong></div>
    <div class="ce-row"><span>Transportation</span><strong style="color:var(--muted);font-size:11px">TBD — based on shoot location</strong></div>
    <div class="ce-row total"><span>Equipment Total</span><span>₱${fmt(equipSubtotal)}</span></div>
    <div style="font-size:10px;color:var(--muted);margin-top:8px">Equipment rates include 12% VAT. Crew and transport costs will be confirmed by admin after booking review.</div>
  </div>`;

  const pb=document.getElementById('procBtn'); if(pb) pb.disabled=false;
}

function updateBtns() {
  const eqIds   = reqList.filter(i=>i.item_type==='equipment').map(i=>parseInt(i.equipment_id));
  document.querySelectorAll('[data-eid]').forEach(b=>{
    const inList = eqIds.includes(parseInt(b.dataset.eid));
    b.textContent = inList ? 'In List — Remove' : '+ Add to Request List';
    b.classList.toggle('selected', inList);
    if (inList) b.onclick = ()=>removeByEqId(parseInt(b.dataset.eid));
    else b.onclick = ()=>toggleEquipment(parseInt(b.dataset.eid));
  });
  if (document.getElementById('eqDetailMo')?.classList.contains('on')) refreshEdActionBtn();
}

function toggleEquipment(eid, selectedAccessories) {
  if (!IS_LOGIN) { requireAuth(); return; }
  const inList = reqList.some(i=>i.item_type==='equipment'&&parseInt(i.equipment_id)===eid);
  if (inList) { removeByEqId(eid); return; }
  const fd=new FormData();
  fd.append('_token', CSRF_TOKEN);
  fd.append('action','add_equipment');
  fd.append('equipment_id',eid);
  fd.append('quantity',1);
  fd.append('days',1);
  if (selectedAccessories && selectedAccessories.length > 0) {
    fd.append('accessories_json', JSON.stringify(selectedAccessories));
  }
  fetch('/cart',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.ok){
      loadList();
      toast('Added to request list','blue');
    } else {
      toast(d.error || 'Failed to add equipment','red');
    }
  });
}

function getSelectedAccessories() {
  const accessories = [];
  document.querySelectorAll('.ed-opt-cb:checked').forEach(cb => {
    accessories.push({
      accessory_id: parseInt(cb.dataset.id),
      accessory_name: cb.dataset.name,
      daily_rate: parseFloat(cb.dataset.rate)
    });
  });
  return accessories;
}

function filterMyBookings() {
  const q = (document.getElementById('myBkSearch')?.value || '').trim().toLowerCase();
  const status = document.getElementById('myBkStatus')?.value || '';
  const rows = document.querySelectorAll('#myBkTbody tr[data-search]');
  let anyVisible = false;
  rows.forEach(row => {
    const matchesQ = !q || row.dataset.search.includes(q);
    const matchesStatus = !status || row.dataset.status === status;
    const show = matchesQ && matchesStatus;
    row.style.display = show ? '' : 'none';
    if (show) anyVisible = true;
  });
  const noMatch = document.getElementById('myBkNoMatch');
  if (noMatch) noMatch.style.display = anyVisible ? 'none' : '';
}

function bookAgain(bookingId, btn) {
  if (btn) { btn.disabled = true; btn.textContent = 'Adding…'; }
  const fd = new FormData();
  fd.append('_token', CSRF_TOKEN);
  fd.append('action', 'copy_from_booking');
  fd.append('booking_id', bookingId);
  fetch('/cart', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (btn) { btn.disabled = false; btn.textContent = 'Book Again'; }
      if (d.error) { toast(d.error, 'red'); return; }
      loadList();
      if (!panelOpen) togglePanel();
      let msg = `${d.copied} item${d.copied === 1 ? '' : 's'} added to your Request List.`;
      if (d.skipped && d.skipped.length) msg += ` ${d.skipped.length} unavailable item${d.skipped.length === 1 ? '' : 's'} skipped.`;
      toast(msg, d.copied ? 'green' : 'blue');
    })
    .catch(() => {
      if (btn) { btn.disabled = false; btn.textContent = 'Book Again'; }
      toast('Network error. Please try again.', 'red');
    });
}

// ── Gear list upload (bulk add) ─────────────────────────────
const FLAG_REASON_LABEL = {
  not_found: 'Not found in catalog',
  ambiguous: 'Matches multiple items',
  unavailable: 'Currently unavailable',
  external_source: 'External — not matched',
};
const FLAG_REASON_ORDER = ['external_source', 'ambiguous', 'unavailable', 'not_found'];

function onGearFilePicked(input) {
  const pick = document.getElementById('rlUploadPick');
  const go = document.getElementById('rlUploadGo');
  if (input.files && input.files[0]) {
    pick.textContent = input.files[0].name;
    pick.classList.add('has-file');
    go.disabled = false;
  } else {
    pick.textContent = 'Choose file…';
    pick.classList.remove('has-file');
    go.disabled = true;
  }
}

function resetGearUpload() {
  document.getElementById('rlUploadForm').style.display = 'block';
  document.getElementById('rlUploadProcessed').style.display = 'none';
  document.getElementById('rlGearFile').value = '';
  onGearFilePicked(document.getElementById('rlGearFile'));
  const flagBox = document.getElementById('rlFlaggedBox');
  flagBox.style.display = 'none';
  flagBox.innerHTML = '';
}

function uploadGearList() {
  const fileInput = document.getElementById('rlGearFile');
  const file = fileInput.files[0];
  if (!file) return;

  const days = Math.max(1, parseInt(document.getElementById('rlGearDays').value) || 1);
  const fd = new FormData();
  fd.append('_token', CSRF_TOKEN);
  fd.append('action', 'bulk_add');
  fd.append('file', file);
  fd.append('days', days);

  const go = document.getElementById('rlUploadGo');
  go.disabled = true;
  go.textContent = 'Uploading…';

  fetch('/cart', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      go.disabled = false;
      go.textContent = 'Upload & Match';
      if (d.error) { toast(d.error, 'red'); return; }

      loadList();
      renderFlagged(d.flagged || []);

      document.getElementById('rlUploadForm').style.display = 'none';
      const processed = document.getElementById('rlUploadProcessed');
      processed.style.display = 'flex';
      document.getElementById('rlUploadProcessedName').innerHTML =
        `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg> ${file.name}`;

      let msg = `${d.added} item${d.added === 1 ? '' : 's'} added to your Request List.`;
      if (d.flagged && d.flagged.length) msg += ` ${d.flagged.length} row${d.flagged.length === 1 ? '' : 's'} need review.`;
      toast(msg, d.added ? 'green' : 'blue');
    })
    .catch(() => {
      go.disabled = false;
      go.textContent = 'Upload & Match';
      toast('Network error. Please try again.', 'red');
    });
}

function renderFlagged(flagged) {
  const box = document.getElementById('rlFlaggedBox');
  if (!flagged.length) { box.style.display = 'none'; box.innerHTML = ''; return; }

  const groups = {};
  flagged.forEach(f => { (groups[f.reason] = groups[f.reason] || []).push(f); });

  let html = `<div class="rl-flagged-title">${flagged.length} row${flagged.length === 1 ? '' : 's'} need your review</div>`;
  FLAG_REASON_ORDER.forEach(reason => {
    const rows = groups[reason];
    if (!rows || !rows.length) return;
    html += `<div class="rl-flag-group">
      <div class="rl-flag-group-h ${reason}">${FLAG_REASON_LABEL[reason] || reason} (${rows.length})</div>
      ${rows.map(r => `<div class="rl-flag-row">${escapeHtml(r.line)}</div>`).join('')}
    </div>`;
  });

  box.innerHTML = html;
  box.style.display = 'block';
}

function escapeHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

function removeItem(cartId) {
  const fd=new FormData(); fd.append('_token', CSRF_TOKEN); fd.append('action','remove'); fd.append('cart_id',cartId);
  fetch('/cart',{method:'POST',body:fd}).then(()=>loadList());
}
function removeByEqId(eid) {
  const it=reqList.find(i=>i.item_type==='equipment'&&parseInt(i.equipment_id)===eid);
  if(it) removeItem(it.cart_id);
}
function updateDays(cartId, days) {
  const fd=new FormData(); fd.append('_token', CSRF_TOKEN); fd.append('action','update_days'); fd.append('cart_id',cartId); fd.append('days',Math.max(1,parseInt(days)||1));
  fetch('/cart',{method:'POST',body:fd}).then(()=>loadList());
}

// ── Navigate to CE preview ──────────────────────────────────
function goToEstimate() {
  if (!reqList.length) return;
  window.location.href = '/ce-preview';
}

// ── Favorites ────────────────────────────
function loadFavorites() {
  if (!IS_LOGIN) return;
  fetch('/favorites?action=get')
    .then(r=>r.json())
    .then(d => { favIds = (d.equipment_ids||[]).map(id=>parseInt(id)); renderGrid(); });
}
function toggleFavorite(eid) {
  if (!IS_LOGIN) { requireAuth(); return; }
  const fd=new FormData(); fd.append('_token', CSRF_TOKEN); fd.append('action','toggle'); fd.append('equipment_id',eid);
  fetch('/favorites',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>{
      if (d.favorited) favIds.push(eid); else favIds = favIds.filter(id=>id!==eid);
      renderGrid();
    });
}
function toggleFavFilter(el) {
  showFavsOnly = !showFavsOnly;
  el.classList.toggle('on', showFavsOnly);
  renderGrid();
}

function filterCat(id, el) {
  activeCat=id;
  document.querySelectorAll('.pill').forEach(p=>p.classList.remove('on'));
  el?.classList.add('on');
  renderGrid();
}
function filterSearch(val) { searchTerm=val.toLowerCase(); renderGrid(); }

// ── FAQ / Help panel ────────────────────────────
function buildFaqCatPills() {
  const wrap = document.getElementById('faqCatPills');
  if (!wrap) return;
  const cats = [...new Set(ALL_FAQS.map(f=>f.cat))];
  wrap.innerHTML = '<button class="pill on" onclick="filterFaqCat(\'\',this)">All Topics</button>' +
    cats.map(c=>`<button class="pill" onclick="filterFaqCat('${c.replace(/'/g,"\\'")}',this)">${c}</button>`).join('');
}
function filterFaqCat(cat, el) {
  activeFaqCat = cat;
  document.querySelectorAll('#faqCatPills .pill').forEach(p=>p.classList.remove('on'));
  el?.classList.add('on');
  renderFaqList();
}
function filterFaqSearch(val) { faqSearchTerm = val.toLowerCase(); renderFaqList(); }
function toggleFaq(id) {
  const a = document.getElementById('faq-a-'+id);
  const icon = document.getElementById('faq-icon-'+id);
  if (!a) return;
  const isOpen = a.style.maxHeight && a.style.maxHeight !== '0px';
  a.style.maxHeight = isOpen ? '0px' : a.scrollHeight + 'px';
  if (icon) icon.style.transform = isOpen ? '' : 'rotate(180deg)';
}
function renderFaqList() {
  const list = document.getElementById('faqList');
  const empty = document.getElementById('faqEmpty');
  if (!list) return;
  const f = ALL_FAQS.filter(x=>{
    const mc = !activeFaqCat || x.cat===activeFaqCat;
    const ms = !faqSearchTerm || x.q.toLowerCase().includes(faqSearchTerm) || x.a.toLowerCase().includes(faqSearchTerm);
    return mc && ms;
  });
  if (!f.length) { list.innerHTML=''; empty.style.display='block'; return; }
  empty.style.display='none';
  list.innerHTML = f.map(x=>`
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);overflow:hidden">
      <button onclick="toggleFaq(${x.id})" style="width:100%;text-align:left;padding:16px 18px;background:none;border:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:12px;font-family:inherit">
        <span style="font-weight:700;font-size:.9rem;color:var(--text)">${escHtml(x.q)}</span>
        <svg id="faq-icon-${x.id}" class="faq-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--sub)" stroke-width="2.5" style="flex-shrink:0;transition:transform .2s"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div id="faq-a-${x.id}" class="faq-answer" style="max-height:0;overflow:hidden;transition:max-height .25s ease">
        <div style="padding:0 18px 16px;font-size:.85rem;color:var(--sub);line-height:1.65">${escHtml(x.a)}</div>
      </div>
    </div>
  `).join('');
}
function renderGrid() {
  const grid=document.getElementById('eqGrid'); if(!grid) return;
  const f=ALL_EQ.filter(e=>{
    const mc=!activeCat||e.cat_id===activeCat;
    const ms=!searchTerm||e.name.toLowerCase().includes(searchTerm)||e.brand.toLowerCase().includes(searchTerm);
    const mf=!showFavsOnly||favIds.includes(e.id);
    return mc&&ms&&mf;
  });
  if (!f.length){grid.innerHTML=`<div style="grid-column:1/-1;text-align:center;padding:72px 24px;color:var(--muted);font-size:14px"><div style="font-family:var(--font-d);font-size:22px;letter-spacing:.5px;margin-bottom:10px;color:var(--border2)">NO RESULTS</div>${showFavsOnly?'No favorited equipment yet.':'No equipment found matching your search.'}</div>`;return;}
  const eqIds=reqList.filter(i=>i.item_type==='equipment').map(i=>parseInt(i.equipment_id));
  grid.innerHTML=f.map(eq=>{
    const av=eq.avail==='available';
    const isBooked=eq.avail==='booked';
    const inList=eqIds.includes(eq.id);
    const isFav=favIds.includes(eq.id);
    const statusText = av ? 'Available' : (isBooked ? 'Booked' : 'In Use');
    const availClass = av ? 'av' : (isBooked ? 'busy' : 'inuse');
    const catAb=escHtml((eq.cat||'').substring(0,2).toUpperCase());
    const imgHtml=eq.img?`<img src="${ASSET_BASE}/${escAttr(eq.img)}" alt="">`:(`<span class="eq-cat-icon">${catAb}</span>`);
    const opHtml=eq.req_op?`<div class="eq-op">Operator req'd</div>`:'';
    const btnHtml=IS_LOGIN
      ?`<button class="req-btn${inList?' selected':''}" data-eid="${eq.id}" onclick="${inList?`removeByEqId(${eq.id})`:`toggleEquipment(${eq.id})`}" ${!av&&!inList?'disabled':''}>
          ${inList?'In List — Remove':(!av?'Unavailable':'+ Add to Request List')}
        </button>`
      :`<button class="req-btn" onclick="requireAuth()">+ Add to Request List</button>`;
    const favBtnHtml=`<button class="fav-star${isFav?' on':''}" onclick="event.stopPropagation();toggleFavorite(${eq.id})" title="${isFav?'Remove from favorites':'Add to favorites'}">
        <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      </button>`;
    const displayRate = Math.round(eq.rate);
    return `<div class="eq-card">
      <div class="eq-img" onclick="openEqDetail(${eq.id})" style="cursor:pointer" title="View details">
        ${imgHtml}
        ${favBtnHtml}
        <span class="eq-cat-pill">${escHtml(eq.cat)}</span>
        <span class="avail-pill ${availClass}">${statusText}</span>
      </div>
      <div class="eq-body">
        <div class="eq-name">${escHtml(eq.name)}</div>
        <div class="eq-brand">${escHtml(eq.brand)}</div>
        <div class="eq-divider"></div>
        ${opHtml}
        <div class="eq-foot">
          <div class="eq-rate">₱${displayRate.toLocaleString('en-PH')}</div>
          <div class="eq-rate-sub">/day &middot; VAT included</div>
        </div>
        ${btnHtml}
      </div>
    </div>`;
  }).join('');
}

let edCurrentEqId = null;
let edBaseRate    = 0;

function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function escAttr(s){return String(s).replace(/"/g,'&quot;').replace(/'/g,'&#39;');}

function openEqDetail(eid) {
  const eq = ALL_EQ.find(e => e.id === eid);
  if (!eq) return;
  edCurrentEqId = eid;
  edBaseRate = Math.round(eq.rate);

  const img = document.getElementById('edImg');
  const fb  = document.getElementById('edImgFallback');
  if (eq.img) {
    img.src = ASSET_BASE + '/' + eq.img;
    img.style.display = 'block';
    fb.style.display  = 'none';
  } else {
    img.style.display = 'none';
    fb.textContent    = (eq.cat || '').substring(0,2).toUpperCase();
    fb.style.display  = 'block';
  }
  document.getElementById('edName').textContent  = eq.name;
  document.getElementById('edBrand').textContent = eq.brand || '';
  document.getElementById('edCatPill').textContent = eq.cat;

  const ap = document.getElementById('edAvailPill');
  const av = eq.avail === 'available';
  const isBooked = eq.avail === 'booked';
  ap.textContent      = av ? 'Available' : (isBooked ? 'Booked' : 'In Use');
  ap.style.background = av ? 'rgba(21,128,61,.28)' : 'rgba(185,28,28,.28)';
  ap.style.color      = av ? '#bbf7d0' : '#fca5a5';

  document.getElementById('edRate').textContent = '₱' + edBaseRate.toLocaleString('en-PH');

  const opEl = document.getElementById('edOpNote');
  if (eq.req_op) {
    opEl.textContent = 'Operator req\'d' + (eq.op_pos ? ' · ' + eq.op_pos : '') + ' · TF: TBD';
    opEl.style.display = '';
  } else {
    opEl.style.display = 'none';
  }

  const descWrap = document.getElementById('edDescWrap');
  if (eq.desc) {
    document.getElementById('edDesc').textContent = eq.desc;
    descWrap.style.display = '';
  } else {
    descWrap.style.display = 'none';
  }

  document.getElementById('edAccLoading').style.display = '';
  document.getElementById('edAccLoading').textContent   = 'Loading accessories…';
  document.getElementById('edAccContent').style.display = 'none';
  document.getElementById('edIncludedWrap').style.display = 'none';
  document.getElementById('edOptionalWrap').style.display = 'none';
  document.getElementById('edNoAccNote').style.display    = 'none';

  updateEdPricing();
  refreshEdActionBtn();
  openMo('eqDetailMo');

  fetch(`/?eq_detail=${eid}`)
    .then(r => r.json())
    .then(accs => {
      document.getElementById('edAccLoading').style.display = 'none';
      document.getElementById('edAccContent').style.display = '';
      const included = accs.filter(a => parseInt(a.is_included));
      const optional = accs.filter(a => !parseInt(a.is_included));

      const accThumb = (a) => a.image_path
        ? `<img src="${ASSET_BASE}/${escAttr(a.image_path)}" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:6px;flex-shrink:0;border:1px solid var(--border)">`
        : `<div style="width:44px;height:44px;border-radius:6px;background:var(--s2);border:1px solid var(--border);flex-shrink:0;display:flex;align-items:center;justify-content:center;color:var(--sub);font-size:1.1rem">📦</div>`;

      const incWrap = document.getElementById('edIncludedWrap');
      if (included.length) {
        document.getElementById('edIncludedList').innerHTML = included.map(a => `
          <div style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:var(--surface);border-radius:8px;border:1px solid var(--border)">
            ${accThumb(a)}
            <div style="flex:1;min-width:0">
              <div style="font-size:.82rem;font-weight:600;color:var(--text)">${escHtml(a.accessory_name)}</div>
              ${a.description?`<div style="font-size:.72rem;color:var(--sub);margin-top:2px">${escHtml(a.description)}</div>`:''}
            </div>
            <span style="font-size:.7rem;color:#15803d;font-weight:700;flex-shrink:0;background:#dcfce7;padding:2px 8px;border-radius:20px">Included</span>
          </div>`).join('');
        incWrap.style.display = '';
      }

      const optWrap = document.getElementById('edOptionalWrap');
      if (optional.length) {
        document.getElementById('edOptionalList').innerHTML = optional.map(a => {
          const rate = Math.round(parseFloat(a.daily_rate));
          return `
            <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--surface);border-radius:8px;border:1.5px solid var(--border);cursor:pointer;transition:border-color .15s"
                   onmouseover="this.style.borderColor='var(--blue)'" onmouseout="this.style.borderColor=this.querySelector('input').checked?'var(--blue)':'var(--border)'">
              ${accThumb(a)}
              <div style="flex:1;min-width:0">
                <div style="font-size:.82rem;font-weight:600;color:var(--text)">${escHtml(a.accessory_name)}</div>
                ${a.description?`<div style="font-size:.72rem;color:var(--sub);margin-top:2px">${escHtml(a.description)}</div>`:''}
              </div>
              <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                <span style="font-size:.78rem;font-weight:700;color:var(--blue);white-space:nowrap">+₱${rate.toLocaleString('en-PH')}/day</span>
                <input type="checkbox" class="ed-opt-cb" data-id="${a.accessory_id}" data-rate="${rate}" data-name="${escAttr(a.accessory_name)}"
                       style="width:16px;height:16px;cursor:pointer;accent-color:var(--blue)"
                       onchange="updateEdPricing();this.closest('label').style.borderColor=this.checked?'var(--blue)':'var(--border)'">
              </div>
            </label>`;
        }).join('');
        optWrap.style.display = '';
      }

      if (!included.length && !optional.length) {
        document.getElementById('edNoAccNote').style.display = '';
      }
    })
    .catch(() => {
      document.getElementById('edAccLoading').textContent = 'Could not load accessories.';
    });
}

function updateEdPricing() {
  const fmt = n => n.toLocaleString('en-PH');
  document.getElementById('edPriceEq').textContent = '₱' + fmt(edBaseRate);
  let optTotal = 0;
  let rowsHtml = '';
  document.querySelectorAll('.ed-opt-cb:checked').forEach(cb => {
    const r = parseInt(cb.dataset.rate);
    optTotal += r;
    rowsHtml += `<div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:6px">
      <span style="color:var(--sub)">${escHtml(cb.dataset.name)}</span>
      <span style="font-weight:600">+₱${fmt(r)}</span></div>`;
  });
  document.getElementById('edPriceOptRows').innerHTML = rowsHtml;
  document.getElementById('edPriceTotal').textContent = '₱' + fmt(edBaseRate + optTotal);
}

function refreshEdActionBtn() {
  const btn = document.getElementById('edActionBtn');
  const eq  = ALL_EQ.find(e => e.id === edCurrentEqId);
  if (!eq || !btn) return;
  const av     = eq.avail === 'available';
  const inList = reqList.some(i => i.item_type === 'equipment' && parseInt(i.equipment_id) === edCurrentEqId);

  if (inList) {
    btn.textContent      = 'In List — Remove';
    btn.style.background = '#fef2f2';
    btn.style.color      = '#b91c1c';
    btn.style.outline    = '1.5px solid #fca5a5';
    btn.disabled         = false;
    btn.onclick          = () => { removeByEqId(edCurrentEqId); toast('Removed from request list','red'); };
  } else if (!IS_LOGIN) {
    btn.textContent      = '+ Add to Request List';
    btn.style.background = 'var(--blue)';
    btn.style.color      = '#fff';
    btn.style.outline    = 'none';
    btn.disabled         = false;
    btn.onclick          = () => requireAuth();
  } else if (!av) {
    btn.textContent      = 'Currently Unavailable';
    btn.style.background = 'var(--surface)';
    btn.style.color      = 'var(--muted)';
    btn.style.outline    = '1.5px solid var(--border)';
    btn.disabled         = true;
    btn.onclick          = null;
  } else {
    btn.textContent      = '+ Add to Request List';
    btn.style.background = 'var(--blue)';
    btn.style.color      = '#fff';
    btn.style.outline    = 'none';
    btn.disabled         = false;
    btn.onclick          = () => { toggleEquipment(edCurrentEqId, getSelectedAccessories()); setTimeout(refreshEdActionBtn, 450); };
  }
}

function openMo(id){document.getElementById(id)?.classList.add('on');}
function closeMo(id){document.getElementById(id)?.classList.remove('on');}
function requireAuth(){openMo('authMo');}
document.querySelectorAll('.mo').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('on');}));
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('.mo.on').forEach(m=>m.classList.remove('on'));if(panelOpen)togglePanel();}});

function toast(msg,color){
  const w=document.getElementById('toastW');
  const t=document.createElement('div'); t.className='toast';
  const c=color==='blue'?'var(--blue)':color==='green'?'var(--green)':'var(--red)';
  t.innerHTML=`<span class="tdot" style="background:${c}"></span>${msg}`;
  w.appendChild(t);
  requestAnimationFrame(()=>requestAnimationFrame(()=>t.classList.add('show')));
  setTimeout(()=>{t.classList.remove('show');setTimeout(()=>t.remove(),300);},3000);
}

document.addEventListener('DOMContentLoaded',()=>{
  renderGrid();
  buildFaqCatPills();
  renderFaqList();
  if (IS_LOGIN) { loadList(); loadFavorites(); }
});
</script>

@if($isLoggedIn)
<!-- Support chat (general, not tied to a specific booking) -->
<button class="sup-fab" onclick="toggleSupportChat()" aria-label="Message FilmSpec">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/></svg>
  @if ($supportMessages->count() > 0)
  <span class="sup-fab-badge">{{ $supportMessages->count() }}</span>
  @endif
</button>

<div class="sup-overlay" id="supOverlay" onclick="closeSupportChat()"></div>
<div class="sup-panel" id="supPanel">
  <div class="sup-h">
    <div>
      <div class="t">FilmSpec Team</div>
      <div class="s">Usually replies within a few hours</div>
    </div>
    <button class="sup-close" onclick="closeSupportChat()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="sup-body" id="supBody" data-last-id="{{ $supportMessages->last()->message_id ?? 0 }}">
    @if ($supportMsg)
    <div style="padding:9px 12px;border-radius:7px;font-size:12px;font-weight:600;
                background:{{ $supportMsg['type'] === 'success' ? 'var(--greenlt)' : 'var(--redlt)' }};
                color:{{ $supportMsg['type'] === 'success' ? '#15803d' : '#b91c1c' }}">
      {{ $supportMsg['text'] }}
    </div>
    @endif

    @if ($supportMessages->isEmpty())
    <div class="sup-empty">No messages yet. Have a general question about FilmSpec? Send us a message below — for questions about a specific booking, open that booking and use the chat bubble there instead, so your staff contact sees it in the right place.</div>
    @else
    @php
      $supClusters = [];
      foreach ($supportMessages as $m) {
          $supRole = $m->author_role === 'client' ? 'me' : 'them';
          if ($supClusters && end($supClusters)['role'] === $supRole) {
              $supClusters[array_key_last($supClusters)]['items'][] = $m;
          } else {
              $supClusters[] = ['role' => $supRole, 'items' => [$m]];
          }
      }
    @endphp
    @foreach ($supClusters as $supCluster)
      <div class="sup-cluster {{ $supCluster['role'] }}">
        @foreach ($supCluster['items'] as $m)
        <div class="sup-bubble">
          @if ($m->body){{ $m->body }}@endif
          @if ($m->attachment_path)
            @if (str_starts_with($m->attachment_mime, 'image/'))
            <img src="{{ route('support-attachment', $m->message_id) }}" class="sup-attach-img" onclick="window.open(this.src,'_blank')" alt="{{ $m->attachment_name }}">
            @else
            <a href="{{ route('support-attachment', $m->message_id) }}" target="_blank" class="sup-attach-file">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              <div class="meta">
                <div class="fname">{{ $m->attachment_name }}</div>
                <div class="fsize">{{ number_format($m->attachment_size / 1024, 0) }} KB</div>
              </div>
            </a>
            @endif
          @endif
        </div>
        @endforeach
      </div>
      @php $supLastItem = end($supCluster['items']); @endphp
      <div class="sup-meta">{{ $supCluster['role'] === 'them' ? 'FilmSpec Support · ' : '' }}{{ \Carbon\Carbon::parse($supLastItem->created_at)->format('M j, g:i A') }}</div>
    @endforeach
    @endif
  </div>
  <form method="POST" action="{{ route('home') }}" class="sup-input" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="action" value="post_support_message">
    <div class="sup-file-chip" id="supFileChip">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M21.44 11.05 12.25 20.24a5 5 0 0 1-7.07-7.07l9.19-9.19a3.33 3.33 0 0 1 4.71 4.71l-9.2 9.19a1.67 1.67 0 0 1-2.36-2.36l8.49-8.48"/></svg>
      <span class="name"></span>
      <span class="rm" onclick="clearSupFile()" title="Remove">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="10" height="10"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </span>
    </div>
    <div class="sup-input-row">
      <button type="button" class="sup-attach-btn" onclick="document.getElementById('supFile').click()" title="Attach file">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21.44 11.05 12.25 20.24a5 5 0 0 1-7.07-7.07l9.19-9.19a3.33 3.33 0 0 1 4.71 4.71l-9.2 9.19a1.67 1.67 0 0 1-2.36-2.36l8.49-8.48"/></svg>
      </button>
      <input type="file" id="supFile" name="attachment" accept="image/*,.pdf,.doc,.docx" style="display:none" onchange="showSupFilePreview(this)">
      <textarea name="body" rows="1" placeholder="Message the FilmSpec team…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit()}"></textarea>
      <button type="submit" class="sup-send" title="Send">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg>
      </button>
    </div>
  </form>
</div>
<script>
function showSupFilePreview(input){
  var chip = document.getElementById('supFileChip');
  if (input.files && input.files[0]) {
    chip.querySelector('.name').textContent = input.files[0].name;
    chip.classList.add('show');
  } else {
    chip.classList.remove('show');
  }
}
function clearSupFile(){
  document.getElementById('supFile').value = '';
  document.getElementById('supFileChip').classList.remove('show');
}
function toggleSupportChat(){
  var p = document.getElementById('supPanel'), o = document.getElementById('supOverlay');
  var open = p.classList.toggle('open');
  o.classList.toggle('open', open);
  if (open) { var b = document.getElementById('supBody'); b.scrollTop = b.scrollHeight; }
}
function closeSupportChat(){
  document.getElementById('supPanel').classList.remove('open');
  document.getElementById('supOverlay').classList.remove('open');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSupportChat(); });
@if (request('action') === 'post_support_message')
document.addEventListener('DOMContentLoaded', toggleSupportChat);
@endif

function supBubbleHtml(m) {
  // Each polled-in message renders as its own one-item cluster (rather than merging into
  // the previous DOM cluster) — keeps this function simple; the only cost is that two
  // consecutive same-sender messages arriving in one poll show two small timestamp lines
  // instead of one, which self-corrects on the next full page load.
  var side = m.author_role === 'client' ? 'me' : 'them';
  var body = m.body ? escapeHtml(m.body) : '';
  var attach = '';
  if (m.attachment_path) {
    var url = '{{ url('/support-attachment') }}/' + m.message_id;
    if ((m.attachment_mime || '').indexOf('image/') === 0) {
      attach = '<img src="' + url + '" class="sup-attach-img" onclick="window.open(this.src,\'_blank\')" alt="">';
    } else {
      var kb = Math.round((m.attachment_size || 0) / 1024);
      attach = '<a href="' + url + '" target="_blank" class="sup-attach-file"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><div class="meta"><div class="fname">' + escapeHtml(m.attachment_name || '') + '</div><div class="fsize">' + kb + ' KB</div></div></a>';
    }
  }
  var when = new Date(m.created_at);
  var timeStr = when.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  var metaPrefix = side === 'them' ? 'FilmSpec Support · ' : '';
  return '<div class="sup-cluster ' + side + '"><div class="sup-bubble">' + body + attach + '</div></div>'
    + '<div class="sup-meta">' + metaPrefix + timeStr + '</div>';
}

function pollSupportChat() {
  if (!IS_LOGIN || document.hidden) return;
  var body = document.getElementById('supBody');
  if (!body) return;
  var lastId = parseInt(body.dataset.lastId || '0');
  fetch('{{ route('support-poll') }}?after=' + lastId)
    .then(r => r.json())
    .then(d => {
      if (!d.messages || !d.messages.length) return;
      var empty = body.querySelector('.sup-empty');
      if (empty) empty.remove();
      var atBottom = body.scrollTop + body.clientHeight >= body.scrollHeight - 30;
      var html = '';
      d.messages.forEach(m => { html += supBubbleHtml(m); });
      body.insertAdjacentHTML('beforeend', html);
      body.dataset.lastId = d.messages[d.messages.length - 1].message_id;
      if (atBottom) body.scrollTop = body.scrollHeight;

      var fab = document.querySelector('.sup-fab');
      var badge = document.querySelector('.sup-fab-badge');
      if (fab && !document.getElementById('supPanel').classList.contains('open')) {
        var n = (badge ? parseInt(badge.textContent) : 0) + d.messages.length;
        if (badge) { badge.textContent = n; } else {
          fab.insertAdjacentHTML('beforeend', '<span class="sup-fab-badge">' + n + '</span>');
        }
      }
    })
    .catch(() => {});
}
setInterval(pollSupportChat, 15000);
document.addEventListener('visibilitychange', function () { if (!document.hidden) pollSupportChat(); });
</script>
@endif

<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <span class="footer-logo">FilmSpec</span>
      <span class="footer-tagline">Integrated Film Operations Platform</span>
    </div>
    <div class="footer-copy">&copy; {{ date('Y') }} FilmSpec. All rights reserved.</div>
  </div>
</footer>
<script src="{{ asset('assets/js/keyboard-aware.js') }}"></script>
</body>
</html>
