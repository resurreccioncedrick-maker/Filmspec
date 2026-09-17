<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Incident Report {{ $ir->incident_number }} — FilmSpec</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', Arial, sans-serif;
      font-size: 11pt;
      color: #111;
      background: #f0f0f0;
      padding: 24px;
    }

    .print-page {
      background: #fff;
      max-width: 800px;
      margin: 0 auto;
      padding: 36px 44px;
      box-shadow: 0 2px 16px rgba(0,0,0,.12);
    }

    /* ── Header ── */
    .doc-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 3px solid #111;
      padding-bottom: 14px;
      margin-bottom: 18px;
    }
    .doc-header-left img {
      height: 48px;
      object-fit: contain;
    }
    .doc-header-center {
      text-align: center;
      flex: 1;
    }
    .doc-header-center .doc-title {
      font-size: 20pt;
      font-weight: 700;
      letter-spacing: .04em;
      text-transform: uppercase;
    }
    .doc-header-center .doc-subtitle {
      font-size: 9pt;
      color: #555;
      margin-top: 2px;
    }
    .doc-header-right {
      text-align: right;
      min-width: 160px;
    }
    .doc-header-right .ir-number {
      font-family: 'JetBrains Mono', monospace;
      font-size: 13pt;
      font-weight: 700;
      color: #c00;
    }
    .doc-header-right .ir-meta {
      font-size: 8.5pt;
      color: #555;
      margin-top: 3px;
    }

    /* ── Sections ── */
    .section {
      margin-bottom: 18px;
    }
    .section-title {
      font-size: 9pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      background: #111;
      color: #fff;
      padding: 4px 10px;
      margin-bottom: 10px;
    }

    /* ── Field grid ── */
    .field-grid {
      display: grid;
      gap: 8px 20px;
    }
    .field-grid.cols-2 { grid-template-columns: 1fr 1fr; }
    .field-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }

    .field-item label {
      display: block;
      font-size: 7.5pt;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #666;
      margin-bottom: 2px;
    }
    .field-item .field-value {
      border-bottom: 1px solid #bbb;
      min-height: 22px;
      padding: 2px 0;
      font-size: 10.5pt;
    }
    .field-item .field-value.mono {
      font-family: 'JetBrains Mono', monospace;
      font-size: 9.5pt;
    }

    /* ── Damage checkboxes ── */
    .damage-list {
      display: flex;
      flex-wrap: wrap;
      gap: 8px 24px;
    }
    .damage-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 10.5pt;
    }
    .damage-item .checkbox {
      width: 16px;
      height: 16px;
      border: 1.5px solid #555;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: 11pt;
      line-height: 1;
    }
    .damage-item .checkbox.checked {
      border-color: #111;
      background: #111;
      color: #fff;
    }
    .others-note {
      margin-top: 8px;
    }
    .others-note label {
      font-size: 7.5pt;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #666;
    }
    .others-note .field-value {
      border-bottom: 1px solid #bbb;
      min-height: 20px;
      padding: 2px 0;
      font-size: 10.5pt;
      font-style: italic;
    }

    /* ── Written report / notes ── */
    .text-block {
      border: 1px solid #bbb;
      min-height: 80px;
      padding: 8px 10px;
      font-size: 10.5pt;
      line-height: 1.55;
      white-space: pre-wrap;
    }

    /* ── Signature lines ── */
    .sig-grid {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 0 24px;
    }
    .sig-item {
      text-align: center;
    }
    .sig-name {
      font-size: 10.5pt;
      font-weight: 600;
      border-bottom: 1px solid #111;
      padding: 2px 0 20px;
      min-height: 48px;
      display: flex;
      align-items: flex-end;
      justify-content: center;
    }
    .sig-label {
      font-size: 7.5pt;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #666;
      margin-top: 4px;
    }

    /* ── Crew lineup table ── */
    .crew-table {
      width: 100%;
      border-collapse: collapse;
    }
    .crew-table th {
      font-size: 8pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      background: #f0f0f0;
      border: 1px solid #bbb;
      padding: 5px 8px;
      text-align: left;
    }
    .crew-table td {
      border: 1px solid #bbb;
      padding: 6px 8px;
      font-size: 10pt;
    }
    .crew-table td.sig-cell {
      width: 200px;
    }
    .crew-table .pos-label {
      font-size: 8.5pt;
      color: #666;
      font-weight: 600;
    }

    /* ── Resolution box ── */
    .resolution-box {
      border: 1.5px solid #c00;
      padding: 10px 14px;
      margin-bottom: 18px;
    }
    .resolution-box .res-title {
      font-size: 8pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #c00;
      margin-bottom: 8px;
    }

    /* ── Print controls ── */
    .print-controls {
      text-align: center;
      margin-bottom: 24px;
    }
    .print-controls button {
      background: #111;
      color: #fff;
      border: none;
      padding: 10px 32px;
      font-size: 11pt;
      font-family: inherit;
      cursor: pointer;
      border-radius: 4px;
      margin: 0 6px;
    }
    .print-controls a {
      display: inline-block;
      background: #555;
      color: #fff;
      text-decoration: none;
      padding: 10px 24px;
      font-size: 11pt;
      border-radius: 4px;
      margin: 0 6px;
    }

    /* ── Footer ── */
    .doc-footer {
      border-top: 1px solid #bbb;
      padding-top: 8px;
      margin-top: 18px;
      display: flex;
      justify-content: space-between;
      font-size: 7.5pt;
      color: #888;
    }

    @media print {
      body { background: #fff; padding: 0; }
      .print-controls, .no-print { display: none !important; }
      .print-page { box-shadow: none; padding: 20px 28px; max-width: 100%; }
    }
  </style>
</head>
<body>

<div class="print-controls no-print">
  <a href="{{ route('incidents') }}">← Back to Incidents</a>
  <button onclick="window.print()">Print</button>
  <button onclick="window.print()" style="background:#c0392b;color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;font-family:'DM Sans',sans-serif;cursor:pointer;display:inline-flex;align-items:center;gap:6px">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    Save as PDF
  </button>
</div>
@if (request()->boolean('pdf'))
<script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="print-page">

  <!-- ── Document Header ── -->
  <div class="doc-header">
    <div class="doc-header-left">
      <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec">
    </div>
    <div class="doc-header-center">
      <div class="doc-title">Incident Report</div>
      <div class="doc-subtitle">Equipment Incident Documentation</div>
    </div>
    <div class="doc-header-right">
      <div class="ir-number">{{ $ir->incident_number ?? 'N/A' }}</div>
      <div class="ir-meta">
        Date: {{ $ir->incident_date ? date('M j, Y', strtotime($ir->incident_date)) : '—' }}
        @if ($ir->incident_time)
          &nbsp;|&nbsp; {{ date('g:i A', strtotime($ir->incident_time)) }}
        @endif
      </div>
      <div class="ir-meta">Status: <strong>{{ ucfirst($ir->status) }}</strong></div>
    </div>
  </div>

  <!-- ── Section A: Project Shoot Details ── -->
  <div class="section">
    <div class="section-title">A — Project Shoot Details</div>
    <div class="field-grid cols-2" style="margin-bottom:14px">
      <div class="field-item">
        <label>Project Name</label>
        <div class="field-value">{{ $ir->project_title }}</div>
      </div>
      <div class="field-item">
        <label>Booking Reference</label>
        <div class="field-value mono">{{ $ir->booking_reference }}</div>
      </div>
      <div class="field-item">
        <label>Shoot Location</label>
        <div class="field-value">{{ $ir->shoot_location }}</div>
      </div>
      <div class="field-item">
        <label>Rental Client</label>
        <div class="field-value">{{ $ir->company_name ?: $ir->contact_person }}</div>
      </div>
    </div>
    <div class="sig-grid">
      <div class="sig-item">
        <div class="sig-name">{{ $ir->dop_name }}</div>
        <div class="sig-label">Director of Photography (DOP)</div>
      </div>
      <div class="sig-item">
        <div class="sig-name">{{ $ir->head_crew_name }}</div>
        <div class="sig-label">Head Crew</div>
      </div>
      <div class="sig-item">
        <div class="sig-name">{{ $ir->custodian_name }}</div>
        <div class="sig-label">Custodian / Equipment Handler</div>
      </div>
    </div>
  </div>

  <!-- ── Section B: Equipment Details ── -->
  <div class="section">
    <div class="section-title">B — Equipment Details</div>
    <div class="field-grid cols-3">
      <div class="field-item">
        <label>Equipment Name</label>
        <div class="field-value">{{ $ir->equipment_name }}</div>
      </div>
      <div class="field-item">
        <label>Brand</label>
        <div class="field-value">{{ $ir->brand }}</div>
      </div>
      <div class="field-item">
        <label>Serial No. / Barcode</label>
        <div class="field-value mono">
          {{ $ir->equip_serial ?? '—' }}
        </div>
      </div>
      <div class="field-item">
        <label>Incident Type</label>
        <div class="field-value">{{ $typeLabel[$ir->incident_type] ?? ucfirst($ir->incident_type) }}</div>
      </div>
      <div class="field-item">
        <label>Cause</label>
        <div class="field-value">{{ $causeLabel[$ir->cause] ?? ucfirst($ir->cause) }}</div>
      </div>
      <div class="field-item">
        <label>Charge Amount</label>
        <div class="field-value mono">₱{{ number_format((float) $ir->charge_amount, 2) }}</div>
      </div>
    </div>
  </div>

  <!-- ── Section C: Damage Description ── -->
  <div class="section">
    <div class="section-title">C — Damage Description</div>
    <div class="damage-list">
      @foreach ($damageTypes as $key => $label)
        @continue($key === 'others')
        <div class="damage-item">
          <span class="checkbox {{ in_array($key, $damageTypesSelected) ? 'checked' : '' }}">
            {{ in_array($key, $damageTypesSelected) ? '✓' : '' }}
          </span>
          {{ $label }}
        </div>
      @endforeach
      <div class="damage-item">
        <span class="checkbox {{ in_array('others', $damageTypesSelected) ? 'checked' : '' }}">
          {{ in_array('others', $damageTypesSelected) ? '✓' : '' }}
        </span>
        Others
      </div>
    </div>
    @if (in_array('others', $damageTypesSelected) && !empty($ir->damage_others_note))
    <div class="others-note">
      <label>Description of Others</label>
      <div class="field-value">{{ $ir->damage_others_note }}</div>
    </div>
    @endif
  </div>

  <!-- ── Section D: Incident Written Report ── -->
  <div class="section">
    <div class="section-title">D — Incident Written Report</div>
    <div class="text-block">{{ $ir->description }}</div>
  </div>

  <!-- ── Section E: Crew Line-Up ── -->
  <div class="section">
    <div class="section-title">E — Crew Line-Up</div>
    <table class="crew-table">
      <thead>
        <tr>
          <th style="width:140px">Position</th>
          <th>Name</th>
          <th class="sig-cell">Signature</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($crewPositions as $posKey => $posLabel)
        <tr>
          <td class="pos-label">{{ $posLabel }}</td>
          <td>{{ $crewLineup[$posKey] ?? '' }}</td>
          <td class="sig-cell">&nbsp;</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <!-- ── Resolution block (if resolved/closed) ── -->
  @if (!empty($ir->resolution) && $ir->resolution !== 'pending')
  <div class="resolution-box">
    <div class="res-title">Resolution / Outcome</div>
    <div class="field-grid cols-3">
      <div class="field-item">
        <label>Resolution</label>
        <div class="field-value">{{ $resLabel[$ir->resolution] ?? ucfirst($ir->resolution) }}</div>
      </div>
      <div class="field-item">
        <label>Final Status</label>
        <div class="field-value">{{ ucfirst($ir->status) }}</div>
      </div>
      <div class="field-item">
        <label>Final Charge</label>
        <div class="field-value mono">₱{{ number_format((float) $ir->charge_amount, 2) }}</div>
      </div>
    </div>
    @if (!empty($ir->notes))
    <div style="margin-top:10px">
      <label style="font-size:7.5pt;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#c00;display:block;margin-bottom:4px">Notes</label>
      <div style="font-size:10.5pt;line-height:1.5">{!! nl2br(e($ir->notes)) !!}</div>
    </div>
    @endif
  </div>
  @endif

  <!-- ── Section F: Notes ── -->
  <div class="section">
    <div class="section-title">F — Additional Notes</div>
    <div class="text-block" style="min-height:60px">
      @if (!empty($ir->notes) && (empty($ir->resolution) || $ir->resolution === 'pending'))
        {{ $ir->notes }}
      @elseif (empty($ir->notes))
        &nbsp;
      @endif
    </div>
  </div>

  <!-- ── Document Footer ── -->
  <div class="doc-footer">
    <span>FilmSpec Operations Platform &mdash; FilmSpec</span>
    <span>Reported by: {{ $ir->reported_by_name ?? 'System' }} &nbsp;|&nbsp; Filed: {{ date('M j, Y g:i A', strtotime($ir->created_at)) }}</span>
    <span>Printed: {{ date('M j, Y g:i A') }}</span>
  </div>

</div><!-- /.print-page -->
</body>
</html>
