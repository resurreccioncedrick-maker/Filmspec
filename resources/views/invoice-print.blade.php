<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $invoiceNumber }} — FilmSpec</title>
<style>
{{-- Table-based layout throughout (no CSS grid/flexbox) so this renders identically in a
     normal browser (Print) and in dompdf (Export PDF) — same reasoning as
     exports/ce-document-pdf.blade.php. Matches the company's actual invoice template exactly:
     plain black text on white, no color accents except the FilmSpec logo. --}}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#000;background:#fff;padding:24px}
.doc{max-width:720px;margin:0 auto}
.pagebreak{page-break-after:always}
table{border-collapse:collapse;width:100%}

.hdr td{border:none;vertical-align:top;padding:0}
.hdr .logo-cell{text-align:right}
.hdr .logo-cell img{height:44px}

.title-row td{border:none;vertical-align:top;padding-top:18px}
.doc-title{font-size:34px;font-weight:bold;letter-spacing:.5px}
.meta-lbl{font-size:11px;font-weight:bold}
.meta-val{font-size:12px;margin-top:2px;margin-bottom:10px}
.party-name{font-size:12px;font-weight:bold;margin-bottom:2px}
.party-line{font-size:11.5px;line-height:1.5}

.itbl{margin-top:24px}
.itbl th{border-bottom:2px solid #000;text-align:left;padding:6px 0;font-size:11px}
.itbl th.r{text-align:right}
.itbl td{padding:8px 0;font-size:12px}
.itbl .proj-row td{font-weight:bold;font-size:13px;padding-top:14px}
.itbl .item-lbl{padding-left:14px;font-weight:bold}
.itbl td.r{text-align:right}

.terms{margin-top:18px;padding-top:8px;border-top:1px solid #ccc;font-size:11px}

.totals{margin-top:14px}
.totals table{width:260px;margin-left:auto}
.totals td{padding:3px 0;font-size:12px}
.totals td.r{text-align:right}
.totals .rule td{border-top:1px solid #000;padding-top:6px}
.totals .due td{font-weight:bold;font-size:14px;padding-top:6px}

.dashed{border-top:1px dashed #000;margin:20px 0}

.remit{font-size:11.5px}
.remit-lbl{font-weight:bold;display:inline-block;width:120px}

.annex-title{font-size:28px;font-weight:bold;margin-bottom:14px}
.annex-sub{font-size:12px;font-weight:bold;text-align:center}
.annex-note{font-size:10.5px;font-weight:bold;text-align:center;margin-bottom:8px}
.annex-tbl td{padding:2px 0;font-size:11.5px}
.annex-tbl td.qty{width:30px;font-weight:bold}
.footer-note{text-align:center;font-size:10.5px;margin-top:40px}

@media print{.no-print{display:none!important}body{padding:0}}
</style>
</head>
<body>

@unless (request()->query('export') === 'pdf')
{{-- dompdf doesn't reliably honor @media print, so the action bar is skipped server-side for
     the actual PDF render rather than relying on CSS to hide it there. --}}
<div class="no-print" style="margin-bottom:20px;display:flex;gap:10px">
  <button onclick="window.print()" style="padding:8px 18px;background:#003D80;color:white;border:none;border-radius:6px;cursor:pointer;font-family:inherit">Print</button>
  <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" style="padding:8px 18px;background:#c0392b;color:white;border:none;border-radius:6px;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:6px;text-decoration:none">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    Export PDF
  </a>
  <button onclick="window.location.href='{{ route('billing') }}'" style="padding:8px 18px;background:#f1f5f9;color:#0f172a;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;font-family:inherit">Close</button>
</div>
@endunless

<div class="doc {{ $crewByPosition->isNotEmpty() ? 'pagebreak' : '' }}">

  <table class="hdr">
    <tr>
      <td></td>
      <td class="logo-cell"><img src="{{ public_path('assets/images/logo.png') }}" alt="FilmSpec"></td>
    </tr>
  </table>

  <table class="title-row">
    <tr>
      <td style="width:34%">
        <div class="doc-title">INVOICE</div>
      </td>
      <td style="width:33%">
        <div class="meta-lbl">Invoice Date</div>
        <div class="meta-val">{{ \Illuminate\Support\Carbon::parse($soa->soa_date ?? now())->format('d M Y') }}</div>
        <div class="meta-lbl">Due Date</div>
        <div class="meta-val">{{ \Illuminate\Support\Carbon::parse($soa->due_date ?? ($soa->soa_date ?? now()))->format('d M Y') }}</div>
        <div class="meta-lbl">Invoice Number</div>
        <div class="meta-val">INV# {{ $invoiceNumber }}</div>
      </td>
      <td style="width:33%">
        <div class="party-name">{{ config('filmspec.invoice_from.name') }}</div>
        @foreach (explode("\n", config('filmspec.invoice_from.address')) as $line)
        <div class="party-line">{{ $line }}</div>
        @endforeach
        <div class="party-line">TIN: {{ config('filmspec.invoice_from.tin') }}</div>
      </td>
    </tr>
  </table>

  <table style="margin-top:16px">
    <tr>
      <td style="width:50%;vertical-align:top">
        <div class="party-name">{{ $soa->company_name ?: $soa->contact_person }}</div>
        @if ($soa->address)
        <div class="party-line">{{ $soa->address }}</div>
        @endif
        @if ($soa->tin)
        <div class="party-line">TIN: {{ $soa->tin }}</div>
        @endif
      </td>
      <td style="width:50%"></td>
    </tr>
  </table>

  <table class="itbl">
    <thead><tr><th>Description</th><th class="r" style="width:70px">Tax</th><th class="r" style="width:110px">Amount PHP</th></tr></thead>
    <tbody>
      <tr class="proj-row"><td colspan="3">Project "{{ strtoupper($soa->project_title ?: $soa->booking_reference) }}"</td></tr>
      @forelse ($lines as $line)
      <tr>
        <td class="item-lbl">{{ $line['label'] }}</td>
        <td class="r">{{ $vatRatePct }}%</td>
        <td class="r">{{ number_format($line['amount'], 2) }}</td>
      </tr>
      @empty
      <tr><td colspan="3" style="font-style:italic">No billable charges on this account.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="terms">
    <strong>Terms of Payment</strong><br>
    100% - Due upon receipt
  </div>

  <div class="totals">
    <table>
      <tr><td>Sub-Total:</td><td class="r">{{ number_format($net, 2) }}</td></tr>
      <tr class="rule"><td>Total of VAT {{ $vatRatePct }}%:</td><td class="r">{{ number_format($vat, 2) }}</td></tr>
      <tr><td style="padding-top:8px">Invoice Total PHP:</td><td class="r" style="padding-top:8px">{{ number_format($grand, 2) }}</td></tr>
      <tr><td>Total Payments PHP:</td><td class="r">{{ number_format($totalPayments, 2) }}</td></tr>
      <tr class="due"><td>Amount Due PHP</td><td class="r">{{ number_format($balance, 2) }}</td></tr>
    </table>
  </div>

  <div class="dashed"></div>

  <div class="remit">
    <strong>Kindly remit payment to:</strong><br><br>
    <div><span class="remit-lbl">Account Name:</span> {{ config('filmspec.invoice_bank.account_name') }}</div>
    <div><span class="remit-lbl">Account Number:</span> {{ config('filmspec.invoice_bank.account_number') }}</div>
    <div><span class="remit-lbl">Bank:</span> {{ config('filmspec.invoice_bank.bank') }}</div>
    <div><span class="remit-lbl">Currency:</span> {{ config('filmspec.invoice_bank.currency') }}</div>
  </div>

</div>

@if ($crewByPosition->isNotEmpty())
<div class="doc">
  <div class="annex-title">ANNEX:</div>
  <div class="annex-sub">CREW TF</div>
  <div class="annex-note">{{ $crewCount }} - Crew (OT after 12Hrs &amp; Double after 22Hrs)</div>
  <table class="annex-tbl">
    @foreach ($crewByPosition as $row)
    <tr><td class="qty">{{ $row->qty }}</td><td>{{ $row->position_name ?? 'Crew' }}</td></tr>
    @endforeach
  </table>
  <div class="dashed"></div>
  <div class="footer-note">***This is computer generated invoice. No signature required***</div>
</div>
@endif

</body>
</html>
