<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Attendance Record — FilmSpec</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--accent:#003D80;--acclight:#D0E8FF;--text:#0f172a;--sub:#475569;--muted:#94a3b8;--border:#e2e8f0;--surface:#ffffff;--bg:#f8fafc}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

.top-bar{background:var(--accent);color:#fff;padding:12px 28px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.top-bar-logo{font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:2px;display:flex;align-items:center;gap:10px}
.top-bar-actions{display:flex;gap:8px}
.tbtn{padding:7px 16px;border-radius:5px;font-size:12.5px;font-weight:600;cursor:pointer;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.12);color:#fff;font-family:'DM Sans',sans-serif;transition:all .15s}
.tbtn:hover{background:rgba(255,255,255,.25)}

.wrap{max-width:1000px;margin:24px auto;padding:0 16px 40px}
.sheet{background:#fff;border:1px solid #d1d5db;border-radius:4px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.06)}

.hdr{display:grid;grid-template-columns:1fr 1fr;border-bottom:2px solid var(--accent)}
.hdr-left{background:var(--accent);color:#fff;padding:14px 18px;display:flex;flex-direction:column;justify-content:center}
.hdr-left .brand{font-family:'Bebas Neue',sans-serif;font-size:34px;letter-spacing:3px;line-height:1}
.hdr-left .sub{font-size:10px;color:rgba(255,255,255,.75);margin-top:4px;letter-spacing:1px}
.hdr-right{background:#E5F0FF;padding:14px 18px;display:flex;flex-direction:column;justify-content:center}
.hdr-right .doctype{font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:1.5px;color:var(--accent);line-height:1}
.hdr-right .ref{font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--accent);font-weight:700;margin-top:4px}

.info{border-bottom:1px solid var(--border)}
.info-row{display:grid;grid-template-columns:160px 1fr;border-bottom:1px solid #f1f5f9;min-height:26px;align-items:center}
.info-row:last-child{border-bottom:none}
.info-lbl{padding:5px 12px;font-size:10.5px;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.5px;background:#f8fafc;border-right:1px solid var(--border)}
.info-val{padding:5px 14px;font-size:12.5px;font-weight:600;color:var(--text)}

.summary{display:flex;gap:0;border-bottom:1px solid var(--border)}
.summary div{flex:1;padding:10px 14px;text-align:center;border-right:1px solid var(--border)}
.summary div:last-child{border-right:none}
.summary .num{font-family:'JetBrains Mono',monospace;font-size:18px;font-weight:800;color:var(--accent)}
.summary .lbl{font-size:9.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-top:2px}

table.tbl{width:100%;border-collapse:collapse}
.tbl th{background:#D0E8FF;color:var(--accent);font-size:9px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;padding:6px 8px;text-align:left;border:1px solid #A8D0FF}
.tbl td{padding:6px 8px;font-size:11px;border:1px solid var(--border);vertical-align:middle}
.tbl tr:nth-child(even) td{background:#f9fbff}
.badge{display:inline-block;padding:2px 7px;border-radius:10px;font-size:9.5px;font-weight:700}
.badge.ok{background:#dcfce7;color:#166534}
.badge.warn{background:#fef9c3;color:#854d0e}
.badge.bad{background:#fee2e2;color:#991b1b}

.footer{padding:16px;border-top:1px solid var(--border);display:grid;grid-template-columns:1fr 1fr;gap:40px}
.sig{border-top:1px solid var(--text);padding-top:5px;font-size:10.5px;color:var(--sub);text-align:center;margin-top:38px}

@media print{.top-bar,.no-print{display:none!important}.wrap{margin:0;padding:0;max-width:100%}.sheet{box-shadow:none;border:1px solid #ccc}}
</style>
</head>
<body>

<div class="top-bar no-print">
  <div class="top-bar-logo">
    <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:26px;object-fit:contain;background:rgba(255,255,255,.92);padding:2px 8px;border-radius:4px">
    <span>— Attendance Record</span>
  </div>
  <div class="top-bar-actions">
    <button class="tbtn" onclick="window.print()">Print</button>
    <button onclick="window.print()" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#c0392b;color:white;border:none;border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;font-weight:500;cursor:pointer">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Save as PDF
    </button>
    <a href="{{ $backUrl ?? route('booking-detail', $bid) }}"><button class="tbtn">&larr; Booking</button></a>
  </div>
</div>

<div class="wrap">
  <div class="sheet">
    <div class="hdr">
      <div class="hdr-left">
        <div class="brand">FILMSPEC</div>
        <div class="sub">FIELD TEAM ATTENDANCE RECORD</div>
      </div>
      <div class="hdr-right">
        <div class="doctype">ATTENDANCE RECORD</div>
        <div class="ref">{{ $booking->booking_reference }}</div>
      </div>
    </div>

    <div class="info">
      <div class="info-row"><div class="info-lbl">Client</div><div class="info-val">{{ $booking->company_name ?: $booking->contact_person }}</div></div>
      <div class="info-row"><div class="info-lbl">Project</div><div class="info-val">{{ $booking->project_title ?: '—' }}</div></div>
      <div class="info-row"><div class="info-lbl">Shoot Dates</div><div class="info-val">{{ \Carbon\Carbon::parse($booking->shoot_date_start)->format('M j, Y') }} – {{ \Carbon\Carbon::parse($booking->shoot_date_end)->format('M j, Y') }}</div></div>
      <div class="info-row"><div class="info-lbl">Booking Status</div><div class="info-val">{{ ucfirst($booking->booking_status) }}</div></div>
      <div class="info-row"><div class="info-lbl">Printed</div><div class="info-val">{{ now()->format('M j, Y g:i A') }}</div></div>
    </div>

    <div class="summary">
      <div><div class="num">{{ $totalCrew }}</div><div class="lbl">Team Size</div></div>
      <div><div class="num">{{ $presentCount }}</div><div class="lbl">Present / Late</div></div>
      <div><div class="num" style="{{ $absentCount ? 'color:#991b1b' : '' }}">{{ $absentCount }}</div><div class="lbl">Absent / No-Show</div></div>
      <div><div class="num">{{ $records->count() }}</div><div class="lbl">Records Logged</div></div>
    </div>

    <table class="tbl">
      <thead>
        <tr>
          <th>Date</th><th>Crew Member</th><th>Position</th><th>Status</th>
          <th>Reason</th><th>Replacement</th><th>Logged By</th><th>Logged At</th>
        </tr>
      </thead>
      <tbody>
        @if ($records->isEmpty())
        <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:16px">No attendance has been logged for this booking yet.</td></tr>
        @endif
        @foreach ($records as $r)
        @php $statusBadgeClass = ['present' => 'ok', 'late' => 'warn', 'absent' => 'bad', 'no_show' => 'bad', 'back_out' => 'warn'][$r->status] ?? ''; @endphp
        <tr>
          <td style="font-family:'JetBrains Mono',monospace">{{ \Carbon\Carbon::parse($r->attendance_date)->format('M j, Y') }}</td>
          <td><strong>{{ $r->crew_name }}</strong></td>
          <td>{{ $r->position_name ?? '—' }}</td>
          <td><span class="badge {{ $statusBadgeClass }}">{{ $attStatusLabel[$r->status] ?? ucfirst($r->status) }}</span></td>
          <td>{{ $r->reason ?: '—' }}</td>
          <td>{{ trim((string) $r->replacement_name) ?: '—' }}</td>
          <td>{{ trim((string) $r->logged_by_name) ?: '—' }}</td>
          <td style="font-family:'JetBrains Mono',monospace;font-size:9.5px">{{ $r->created_at ? \Carbon\Carbon::parse($r->created_at)->format('M j, g:i A') : '—' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <div class="footer">
      <div>
        <div style="font-size:10.5px;color:var(--muted);margin-bottom:4px">Logged by (Crew lead / FilmSpec staff):</div>
        <div class="sig">(Signature over printed name)</div>
      </div>
      <div>
        <div style="font-size:10.5px;color:var(--muted);margin-bottom:4px">Verified by (Operations):</div>
        <div class="sig">(Signature over printed name)</div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
