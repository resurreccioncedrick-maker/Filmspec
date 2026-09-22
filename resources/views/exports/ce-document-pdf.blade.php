<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
{{-- dompdf has no CSS Grid/Flexbox support, so this mirrors ce-preview.blade.php's .ce-sheet
     styling (the on-screen "official document") using plain tables — same content, same look,
     just a layout engine dompdf can actually render. Keep both in sync by eye if either changes. --}}
@page { margin: 18px 22px; }
*{box-sizing:border-box;margin:0;padding:0}
{{-- DejaVu Sans is the font dompdf ships with full Unicode coverage — plain "Arial" maps to
     core Helvetica, which has no glyph for ₱ and silently prints "?" instead. --}}
body{font-family:'DejaVu Sans',sans-serif;color:#000;font-size:10.5px}
table{border-collapse:collapse;width:100%}
.sheet{border:1.5px solid #000;margin-bottom:0}
.pagebreak{page-break-after:always}

.hdr-outer td{border:none;vertical-align:top}
.hdr-left{width:50%;border-right:1.5px solid #000;padding:14px 18px;text-align:center}
.hdr-right{width:50%;padding:0}
.hdr-banner{background:#003D80;color:#fff;font-size:19px;font-weight:bold;text-align:center;padding:10px 14px}
.hdr-num{font-size:12px;font-weight:bold;font-style:italic;text-align:center;padding:6px 14px;border-bottom:1px solid #000}
.hdr-meta td{border-top:1px solid #000;padding:3px 8px;font-size:11px}
.hdr-meta .lbl{text-align:right;width:45%}
.hdr-meta .val{font-weight:bold;font-style:italic}
.hdr-fsfront{text-align:center;color:#c0392b;font-weight:bold;font-style:italic;font-size:13px;padding:5px 8px;border-top:1px solid #000}

.info-row td{border-bottom:1px solid #000;padding:3px 10px;font-size:11px}
.info-row .lbl{font-weight:bold;text-transform:uppercase;width:160px}
.info-row .val{font-weight:bold;font-style:italic}

.section-hdr{background:#fff;padding:6px 14px;font-size:11px;font-weight:bold;font-style:italic;text-transform:uppercase;text-align:center;border-bottom:1.5px solid #000;border-top:1.5px solid #000}

.crewnote{padding:6px 14px 0;font-size:11px;font-weight:bold;font-style:italic}
.crewnote .sub{font-size:10.5px;margin-top:2px}

.tbl th{background:#fff;font-size:10.5px;font-weight:bold;text-transform:uppercase;padding:5px 10px;text-align:left;border:1px solid #000}
.tbl th.r{text-align:right}
.tbl td{padding:4px 10px;font-size:11px;border:1px solid #000}
.tbl td.r{text-align:right}
.catgroup td{font-weight:bold;font-style:italic;border-left:none;border-right:none;border-top:1px solid #000;border-bottom:1px solid #000}
.subtot td{font-weight:bold;font-style:italic}

.totals{padding:10px 16px}
.totals table{width:60%;margin-left:40%}
.totals td{padding:4px 10px;font-size:11.5px}
.totals .lbl{font-weight:bold;font-style:italic}
.totals .val{text-align:right;font-weight:bold;font-style:italic}
.totals .grand td{border-top:1.5px solid #000;font-size:12.5px;padding-top:8px}

.words{padding:10px 16px;font-size:11px;font-style:italic;font-weight:bold;text-align:center;text-decoration:underline}
.notes{padding:8px 14px;font-size:10.5px;font-weight:bold;font-style:italic;text-align:center;line-height:1.7}

.summary-row td{border-bottom:1px solid #000;padding:5px 14px;font-size:11.5px}
.summary-row .lbl{font-weight:bold;font-style:italic}
.summary-row .val{text-align:right;font-weight:bold;font-style:italic}
.summary-row.grand td{border-top:1.5px solid #000;border-bottom:none;font-size:14px;padding-top:8px}

.footer-row td{padding:18px 16px 0;font-size:11px;font-weight:bold;font-style:italic;border:none;vertical-align:top}
.sig-line{border-top:1px solid #000;display:inline-block;min-width:160px}
</style>
</head>
<body>
@php
  $vatRate = (float) config('filmspec.vat_rate');
  $eqNet   = $ceBd ? $ceBd['equip_net']   : $equipTotal - round($equipTotal * $vatRate / (1 + $vatRate), 2);
  $eqVat   = $ceBd ? $ceBd['equip_vat']   : round($equipTotal * $vatRate / (1 + $vatRate), 2);
  $eqGrand = $ceBd ? $ceBd['equip_grand'] : $equipTotal;
  $crewNet   = $ceBd ? $ceBd['crew_net']   : $crewTotal - round($crewTotal * $vatRate / (1 + $vatRate), 2);
  $crewVat   = $ceBd ? $ceBd['crew_vat']   : round($crewTotal * $vatRate / (1 + $vatRate), 2);
  $crewGrand = $ceBd ? $ceBd['crew_grand'] : $crewTotal;
@endphp

@php
  $renderHeader = function ($suffix) use ($ceNumber) {
    echo '<table class="hdr-outer"><tr>';
    echo '<td class="hdr-left">';
    echo '<img src="' . public_path('assets/images/logo.png') . '" style="height:46px;max-width:170px">';
    echo '<div style="font-size:10px;font-weight:bold;font-style:italic;margin-top:6px;line-height:1.6">Gate 1, 9110 La Campana St. cor Trabajo St., Olympia, Makati City<br>Cel No. +63927 5056461&nbsp;&nbsp;&nbsp;Tel No. : +632 70004683</div>';
    echo '</td>';
    echo '<td class="hdr-right">';
    echo '<div class="hdr-banner">COST ESTIMATE</div>';
    echo '<div class="hdr-num">CE# ' . e($ceNumber) . '(' . $suffix . ')</div>';
    echo '<table class="hdr-meta">';
    echo '<tr><td class="lbl">Date:</td><td class="val">' . date('F d, Y') . '</td></tr>';
    echo '<tr><td class="lbl">DUE Date:</td><td class="val">' . date('F d, Y', strtotime('+30 days')) . '</td></tr>';
    echo '</table>';
    echo '<div class="hdr-fsfront">FS Front</div>';
    echo '<table class="hdr-meta"><tr><td class="lbl">PREPARED BY:</td><td class="val">Glen Resurreccion</td></tr></table>';
    echo '</td></tr></table>';
  };
  $renderInfo = function () use ($infoRows) {
    echo '<table>';
    foreach ($infoRows as $l => $v) {
      echo '<tr class="info-row"><td class="lbl">' . e($l) . ':</td><td class="val">' . e($v) . '</td></tr>';
    }
    echo '</table>';
  };
@endphp

<!-- ═══ PAGE 1: EQUIPMENT ═══ -->
<div class="sheet pagebreak">
  @php $renderHeader('E'); @endphp
  @php $renderInfo(); @endphp
  <div class="section-hdr">DETAILED COST BREAKDOWN</div>
  <table class="tbl">
    <thead><tr><th style="width:50px">QTY</th><th>EQUIPMENT (S)</th><th class="r" style="width:70px">DAY(S)</th><th class="r" style="width:100px">RATE / DAY</th><th class="r" style="width:110px">AMOUNT</th></tr></thead>
    <tbody>
      @forelse ($equipGroups as $catName => $catLines)
      @php $groupSub = 0; @endphp
      <tr class="catgroup"><td colspan="5">{{ strtoupper($catName) }} (FS):</td></tr>
      @foreach ($catLines as $eq)
      @php $rate = (float) ($eq->daily_rate ?? 0); $qty = (int) ($eq->quantity ?? 1); $groupSub += $qty * $rate; @endphp
      <tr>
        <td style="text-align:center">{{ $qty }}</td>
        <td>{{ $eq->equipment_name . ($eq->brand ? ' (' . $eq->brand . ')' : '') }}</td>
        <td class="r">1</td>
        <td class="r">₱{{ number_format($rate, 2) }}</td>
        <td class="r">₱{{ number_format($qty * $rate, 2) }}</td>
      </tr>
      @endforeach
      <tr class="subtot"><td colspan="4" style="text-align:right">Subtotal — {{ strtoupper($catName) }} (FS):</td><td class="r">₱{{ number_format($groupSub, 2) }}</td></tr>
      @empty
      <tr><td colspan="5" style="text-align:center;font-style:italic">No equipment items</td></tr>
      @endforelse
      <tr class="subtot"><td colspan="4" style="text-align:right">Total (Equipment Only):</td><td class="r">₱{{ number_format($equipTotal, 2) }}</td></tr>
    </tbody>
  </table>
  <div class="totals">
    <table>
      <tr><td class="lbl">Net Amount (ex-VAT):</td><td class="val">₱{{ number_format($eqNet, 2) }}</td></tr>
      <tr><td class="lbl">VAT ({{ round($vatRate * 100) }}/{{ 100 + round($vatRate * 100) }}):</td><td class="val">₱{{ number_format($eqVat, 2) }}</td></tr>
      <tr class="grand"><td class="lbl">EQUIPMENT CE GRAND TOTAL:</td><td class="val">₱{{ number_format($eqGrand, 2) }}</td></tr>
    </table>
  </div>
  <div class="words">Amount in Words: {{ \App\Support\Money::amtWords($eqGrand) }} PESOS ONLY</div>
  <div class="notes">*** All Equipment Returned will be charged as Regular after Pull Out ***<br>*** For more inquiries please call FILM SPEC Cellphone No. 0927 5056461 ***</div>
  <table>
    <tr class="footer-row">
      <td style="width:50%">Prepared by: &nbsp;Glen Resurreccion</td>
      <td style="width:50%">Noted by: <span class="sig-line">&nbsp;</span></td>
    </tr>
  </table>
  <table style="margin-top:18px">
    <tr class="footer-row">
      <td>
        Received &amp; Conformed by:<br><br><br>
        <span class="sig-line" style="width:260px;text-align:center;display:block">(Signature over printed name)</span>
        <div style="margin-top:16px">Date Received: <span class="sig-line">&nbsp;</span></div>
      </td>
    </tr>
  </table>
</div>

<!-- ═══ PAGE 2: CREW TF ═══ -->
<div class="sheet pagebreak">
  @php $renderHeader('M'); @endphp
  @php $renderInfo(); @endphp
  <div class="section-hdr">DETAILED COST BREAKDOWN</div>
  <div class="crewnote">CREW TF :<div class="sub">{{ count($crewLines) }} - Crew (OT after 12Hrs &amp; Double after 22Hrs)</div></div>
  <table class="tbl">
    <thead><tr><th style="width:50px">QTY</th><th>POSITION / NAME</th><th class="r" style="width:70px">DAY(S)</th><th class="r" style="width:100px">RATE / 12H</th><th class="r" style="width:110px">AMOUNT</th></tr></thead>
    <tbody>
      @forelse ($crewLines as $cl)
      @php $clRate = (float) ($cl->rate_used ?? 0); $clDays = (int) ($cl->hours_worked ?? 1); $clSub = $clRate * $clDays; @endphp
      <tr>
        <td style="text-align:center">1</td>
        <td>{{ $cl->position_name ?? 'Crew' }}@if ($cl->crew_name)<br><span style="font-size:10px">{{ $cl->crew_name }}</span>@endif</td>
        <td class="r">{{ $clDays }}</td>
        <td class="r">₱{{ number_format($clRate, 2) }}</td>
        <td class="r">₱{{ number_format($clSub, 2) }}</td>
      </tr>
      @empty
      <tr><td colspan="5" style="text-align:center;font-style:italic">No crew assigned</td></tr>
      @endforelse
      <tr class="subtot"><td colspan="4" style="text-align:right">TOTAL (CREW TF):</td><td class="r">₱{{ number_format($crewTotal, 2) }}</td></tr>
    </tbody>
  </table>
  <div class="totals">
    <table>
      <tr><td class="lbl">Net Amount (ex-VAT):</td><td class="val">₱{{ number_format($crewNet, 2) }}</td></tr>
      <tr><td class="lbl">VAT ({{ round($vatRate * 100) }}/{{ 100 + round($vatRate * 100) }}):</td><td class="val">₱{{ number_format($crewVat, 2) }}</td></tr>
      <tr class="grand"><td class="lbl">CREW CE GRAND TOTAL:</td><td class="val">₱{{ number_format($crewGrand, 2) }}</td></tr>
    </table>
  </div>
  <div class="words">Amount in Words: {{ \App\Support\Money::amtWords($crewGrand) }} PESOS ONLY</div>
  <div class="notes">The Crew Talent Fee shall be due and payable in full upon receipt.</div>
  <table>
    <tr class="footer-row">
      <td style="width:50%">Prepared by: &nbsp;Glen Resurreccion</td>
      <td style="width:50%">Noted by: <span class="sig-line">&nbsp;</span></td>
    </tr>
  </table>
  <table style="margin-top:18px">
    <tr class="footer-row">
      <td>
        Received &amp; Conformed by:<br><br><br>
        <span class="sig-line" style="width:260px;text-align:center;display:block">(Signature over printed name)</span>
        <div style="margin-top:16px">Date Received: <span class="sig-line">&nbsp;</span></div>
      </td>
    </tr>
  </table>
</div>

<!-- ═══ PAGE 3: SUMMARY ═══ -->
<div class="sheet">
  @php $renderHeader('S'); @endphp
  @php $renderInfo(); @endphp
  <div class="section-hdr">SUMMARY</div>
  <table>
    <tr class="summary-row"><td class="lbl">Grip Equipment (FS)</td><td class="val">₱{{ number_format($equipTotal, 2) }}</td></tr>
    <tr class="summary-row"><td class="lbl">Personnel / Crew TF</td><td class="val">₱{{ number_format($crewTotal, 2) }}</td></tr>
    @if (count($accLines) > 0)
    <tr class="summary-row"><td class="lbl">Accessories / Add-ons</td><td class="val">₱{{ number_format($accTotal, 2) }}</td></tr>
    @endif
    <tr class="summary-row"><td class="lbl">Transportation</td><td class="val">₱{{ number_format($ceBd ? $ceBd['transportation'] : $transCost, 2) }}</td></tr>
    @if ($ceBd)
    <tr class="summary-row"><td class="lbl">Equipment CE grand total</td><td class="val">₱{{ number_format($ceBd['equip_grand'], 2) }}</td></tr>
    <tr class="summary-row"><td class="lbl">Crew CE grand total</td><td class="val">₱{{ number_format($ceBd['crew_grand'], 2) }}</td></tr>
    @endif
    <tr class="summary-row"><td class="lbl">Net Amount (ex-VAT)</td><td class="val">₱{{ number_format($subtotal, 2) }}</td></tr>
    <tr class="summary-row"><td class="lbl">VAT ({{ round($vatRate * 100) }}/{{ 100 + round($vatRate * 100) }}) — Included</td><td class="val">₱{{ number_format($vat, 2) }}</td></tr>
    <tr class="summary-row grand"><td class="lbl">GRAND TOTAL (VAT Incl.)</td><td class="val">₱{{ number_format($grand, 2) }}</td></tr>
  </table>
  <div class="words">Amount in Words: {{ \App\Support\Money::amtWords($grand) }} PESOS ONLY</div>
  <div class="notes">
    *** All Equipment Returned will be charged as a Regular after Pull Out ***<br>
    *** For more inquiries please call FILM SPEC Cellphone No. 0927 5056461 ***<br>
    Payment Terms: Regular Clients — 90-day or 6-month credit terms. New Customers — 50% downpayment required.
  </div>
  <table>
    <tr class="footer-row">
      <td style="width:50%">Prepared by: &nbsp;Glen Resurreccion</td>
      <td style="width:50%">Noted by: <span class="sig-line">&nbsp;</span></td>
    </tr>
  </table>
  <table style="margin-top:18px">
    <tr class="footer-row">
      <td>
        Received &amp; Conformed by:<br><br><br>
        <span class="sig-line" style="width:260px;text-align:center;display:block">(Signature over printed name)</span>
        <div style="margin-top:16px">Date Received: <span class="sig-line">&nbsp;</span></div>
      </td>
    </tr>
  </table>
</div>
</body>
</html>
