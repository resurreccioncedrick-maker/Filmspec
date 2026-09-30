@extends('layouts.app')

@section('pageTitle', 'Equipment Checklist')

@section('breadcrumb')
<span>Checklist</span>
@endsection

@if ($canManage)
@section('topbarActions')
<a href="{{ route('checklist', ['booking_id' => $bid, 'dir' => 'out']) }}" class="btn {{ $dir === 'out' ? 'btn-primary' : 'btn-outline' }} btn-sm"><i data-feather="log-out"></i> Checklist Out</a>
<a href="{{ route('checklist', ['booking_id' => $bid, 'dir' => 'in']) }}" class="btn {{ $dir === 'in' ? 'btn-primary' : 'btn-outline' }} btn-sm"><i data-feather="log-in"></i> Checklist In</a>
<a href="{{ route('checklist.print', ['booking_id' => $bid]) }}" target="_blank" class="btn btn-outline btn-sm"><i data-feather="printer"></i> Print Record</a>
<a href="{{ route('booking-detail', $bid) }}" class="btn btn-outline btn-sm"><i data-feather="arrow-left"></i> Back to Booking</a>
@endsection
@endif

@section('content')
@php $checklistBase = route('checklist'); @endphp
<style>
.grp-badge{display:inline-flex;align-items:center;gap:5px;font-family:var(--font-mono);font-size:11px;font-weight:700;color:var(--muted);background:var(--s2);border:1px solid var(--border);border-radius:20px;padding:4px 9px}
.grp-badge.on{color:var(--green);background:var(--greenl);border-color:#a7e0b8}
</style>

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="check-circle"></i> {!! $msg['text'] !!}
</div>
@endif

<!-- Booking header -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-family:var(--font-display);font-size:1.3rem;color:var(--accent);margin-bottom:3px">{{ $booking->booking_reference }}</div>
      <div style="font-size:.875rem;color:var(--sub)">{{ $booking->company_name ?: $booking->contact_person }} &nbsp;·&nbsp; {{ \Illuminate\Support\Carbon::parse($booking->shoot_date_start)->format('M j, Y') }}</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <div style="text-align:center;padding:10px 18px;background:var(--acclight);border-radius:8px;border:1px solid var(--border2)">
        <div style="font-family:var(--font-display);font-size:22px;color:var(--accent)">{{ $outChecked }}/{{ $totalItems }}</div>
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Checked Out</div>
      </div>
      <div style="text-align:center;padding:10px 18px;background:{{ $inChecked == $totalItems && $totalItems > 0 ? 'var(--greenl)' : 'var(--s3)' }};border-radius:8px;border:1px solid var(--border)">
        <div style="font-family:var(--font-display);font-size:22px;color:{{ $inChecked == $totalItems && $totalItems > 0 ? 'var(--green)' : 'var(--text)' }}">{{ $inChecked }}/{{ $totalItems }}</div>
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Returned</div>
      </div>
      @if ($damaged > 0)
      <div style="text-align:center;padding:10px 18px;background:var(--redl);border-radius:8px;border:1px solid #fca5a5">
        <div style="font-family:var(--font-display);font-size:22px;color:var(--red)">{{ $damaged }}</div>
        <div style="font-size:10px;color:var(--red);text-transform:uppercase;letter-spacing:.5px">Damaged/Missing</div>
      </div>
      @endif
    </div>
  </div>
</div>

@if ($fieldArrivalConfirmedAt)
<div class="card" style="margin-bottom:20px;background:var(--acclight);border:1px solid var(--border2)">
  <div class="card-body" style="display:flex;align-items:center;gap:10px;padding:14px 20px">
    <i data-feather="map-pin" style="color:var(--accent);width:16px;height:16px;flex-shrink:0"></i>
    <span style="font-size:.83rem;color:var(--sub)">
      Crew confirmed field arrival on
      {{ \Illuminate\Support\Carbon::parse($fieldArrivalConfirmedAt)->format('M j, Y g:i A') }}
      @if ($fieldArrivalConfirmedByName)&middot; {{ trim($fieldArrivalConfirmedByName) }}@endif
    </span>
  </div>
</div>
@endif

@if ($equipLines->isEmpty())
<div class="card"><div class="empty-state">
  <i data-feather="camera"></i>
  <h3>No equipment assigned</h3>
  <p>Add equipment to this booking first via the booking detail page.</p>
</div></div>
@else

@if ($dir === 'out' && $canManage)
<!-- Pre-flight gate checks -->
<div class="card" style="margin-bottom:20px;border-left:4px solid {{ $gateOk ? 'var(--green)' : 'var(--red)' }}">
  <div class="card-header" style="padding-bottom:8px">
    <h2 class="card-title" style="color:{{ $gateOk ? 'var(--green)' : 'var(--red)' }}">
      <i data-feather="{{ $gateOk ? 'check-circle' : 'alert-circle' }}"></i>
      Pre-Release Gate Check
    </h2>
    @if ($gateOk)
    <span class="badge badge-green">All Clear — Ready to Release</span>
    @else
    <span class="badge badge-red">Blocked — Resolve issues before releasing</span>
    @endif
  </div>
  <div style="padding:0 20px 16px;display:flex;flex-direction:column;gap:8px">

    <div style="display:flex;align-items:center;gap:10px;font-size:.875rem">
      @if ($totalItems > 0)
      <i data-feather="check-circle" style="color:var(--green);width:16px;height:16px;flex-shrink:0"></i>
      <span style="color:var(--text)">{{ $totalItems }} equipment item{{ $totalItems !== 1 ? 's' : '' }} assigned</span>
      @else
      <i data-feather="x-circle" style="color:var(--red);width:16px;height:16px;flex-shrink:0"></i>
      <span style="color:var(--red)"><strong>No equipment assigned.</strong> Add equipment via the booking detail page.</span>
      @endif
    </div>

    <div style="display:flex;align-items:center;gap:10px;font-size:.875rem">
      @if ($crewCount > 0)
      <i data-feather="check-circle" style="color:var(--green);width:16px;height:16px;flex-shrink:0"></i>
      <span style="color:var(--text)">{{ $crewCount }} crew member{{ $crewCount !== 1 ? 's' : '' }} assigned</span>
      @else
      <i data-feather="x-circle" style="color:var(--red);width:16px;height:16px;flex-shrink:0"></i>
      <span style="color:var(--red)"><strong>No crew assigned.</strong> Assign crew on the booking detail page before releasing equipment.</span>
      @endif
    </div>

    <div style="display:flex;align-items:center;gap:10px;font-size:.875rem">
      @if ($costApproved)
      <i data-feather="check-circle" style="color:var(--green);width:16px;height:16px;flex-shrink:0"></i>
      <span style="color:var(--text)">Client approved the cost estimate</span>
      @else
      <i data-feather="x-circle" style="color:var(--red);width:16px;height:16px;flex-shrink:0"></i>
      <span style="color:var(--red)"><strong>Client cost approval required.</strong> The client hasn't approved the cost estimate yet — equipment cannot be released until they do.</span>
      @endif
    </div>

    <div style="display:flex;align-items:center;gap:10px;font-size:.875rem">
      @if (! $transportConfirmed)
      <i data-feather="x-circle" style="color:var(--red);width:16px;height:16px;flex-shrink:0"></i>
      <span style="color:var(--red)"><strong>Transport not yet reviewed.</strong> Open Add/Edit Transport on the booking page, even to confirm none is needed.</span>
      @elseif ($driverNeeded && $driverCount === 0)
      <i data-feather="x-circle" style="color:var(--red);width:16px;height:16px;flex-shrink:0"></i>
      <span style="color:var(--red)"><strong>Driver required</strong> — this booking's transport requires one. Assign a crew member with the Driver position.</span>
      @else
      <i data-feather="check-circle" style="color:var(--green);width:16px;height:16px;flex-shrink:0"></i>
      <span style="color:var(--text)">{{ $driverNeeded ? 'Driver assigned' : 'Transport confirmed' }}</span>
      @endif
    </div>


  </div>
</div>
@endif

<!-- Direction toggle -->
<div style="display:flex;gap:0;margin-bottom:20px;border:1px solid var(--border2);border-radius:8px;overflow:hidden;width:fit-content">
  <a href="{{ route('checklist', ['booking_id' => $bid, 'dir' => 'out']) }}"
     style="padding:10px 24px;font-weight:600;font-size:13px;display:flex;align-items:center;gap:7px;
     background:{{ $dir === 'out' ? 'var(--accent)' : 'var(--surface)' }};
     color:{{ $dir === 'out' ? '#fff' : 'var(--sub)' }};border:none;text-decoration:none;transition:all .15s">
    Check-Out List
  </a>
  <a href="{{ route('checklist', ['booking_id' => $bid, 'dir' => 'in']) }}"
     style="padding:10px 24px;font-weight:600;font-size:13px;display:flex;align-items:center;gap:7px;
     background:{{ $dir === 'in' ? 'var(--accent)' : 'var(--surface)' }};
     color:{{ $dir === 'in' ? '#fff' : 'var(--sub)' }};border:none;text-decoration:none;border-left:1px solid var(--border2);transition:all .15s">
    Check-In List
  </a>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">
      {{ $dir === 'out' ? 'Equipment Check-Out' : 'Equipment Check-In' }}
      <span class="badge badge-blue" style="margin-left:8px">{{ $totalItems }} items</span>
    </h2>
    @if ($canManage)
    <div style="display:flex;gap:8px">
      <button onclick="checkAll()" class="btn btn-outline btn-sm"><i data-feather="check-square"></i> Check All</button>
      @if ($dir === 'out' && $gateOk)
      <button onclick="releaseAll()" class="btn btn-primary btn-sm"><i data-feather="send"></i> Release All</button>
      @endif
    </div>
    @endif
  </div>

  @if ($canManage)
  <form method="POST" action="{{ $checklistBase }}?booking_id={{ $bid }}&dir={{ $dir }}" id="checklistForm">
    @csrf
    <input type="hidden" name="action" value="save_checklist">
    <input type="hidden" name="direction" value="{{ $dir }}">
  @endif

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          @if ($canManage)<th style="width:40px">#</th>@endif
          <th>Equipment</th>
          <th>Category</th>
          <th>Qty</th>
          @if ($dir === 'out')
          <th>Qty Released</th>
          <th>Condition Out</th>
          @else
          <th>Qty Returned</th>
          <th>Condition In</th>
          @endif
          <th>Status</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($equipLines as $eq)
        @php
          $isOut = $dir === 'out';
          $lineKey = $eq->line_key;
          $checked = $isOut ? $eq->co_checked : $eq->ci_checked;
          $condVal = $isOut ? ($eq->condition_out ?? 'good') : ($eq->condition_in ?? 'good');
          $qActual = $isOut ? ($eq->co_qty ?? $eq->quantity) : ($eq->ci_qty ?? $eq->quantity);
          $notes = $isOut ? ($eq->co_notes ?? '') : ($eq->ci_notes ?? '');
          $rowStyle = $checked ? 'background:var(--greenl);' : '';
          if (! $isOut && $eq->is_multi && $eq->any_damaged_in) $rowStyle = 'background:var(--redl);';
          if (! $isOut && ! $eq->is_multi && in_array($condVal, ['damaged', 'missing'], true)) $rowStyle = 'background:var(--redl);';
        @endphp
        <tr style="{{ $rowStyle }}">
          @if ($canManage)
          <td style="text-align:center">
            @if ($eq->is_multi)
            {{-- A multi-unit line has no checkbox of its own — that would imply one action for
                 several physical items. The real check happens per-unit in the panel below;
                 this is just a read-only tally of how many of its units are done. --}}
            <span class="grp-badge {{ $qActual >= $eq->quantity ? 'on' : '' }}" data-line="{{ $lineKey }}" title="Checked per-unit below">
              <i data-feather="layers" style="width:12px;height:12px"></i>{{ $qActual }}/{{ $eq->quantity }}
            </span>
            @else
            <input type="checkbox" name="items[{{ $lineKey }}][checked]" value="1"
                   {{ $checked ? 'checked' : '' }}
                   onchange="this.closest('tr').style.background=this.checked?'var(--greenl)':''"
                   class="checklist-cb" style="width:18px;height:18px;accent-color:var(--accent);cursor:pointer">
            @endif
          </td>
          @endif
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              @if ($eq->image_path)
              <img src="{{ asset('storage/' . $eq->image_path) }}"
                   style="width:38px;height:38px;border-radius:6px;object-fit:cover;border:1px solid var(--border);flex-shrink:0" alt="">
              @else
              <div style="width:38px;height:38px;border-radius:6px;background:var(--s3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--accent);letter-spacing:.5px;flex-shrink:0">{{ strtoupper(substr($eq->category_name ?? '', 0, 2)) }}</div>
              @endif
              <div>
                <div style="font-weight:600;font-size:.875rem">{{ $eq->item_name }}
                  @if ($eq->item_type === 'accessory')<span class="badge badge-gray" style="margin-left:5px;font-size:9px;vertical-align:middle">ACCESSORY</span>@endif
                </div>
                <div style="font-size:.72rem;color:var(--muted)">{{ trim($eq->brand . ' ' . $eq->model) }}</div>
              </div>
            </div>
            @if ($canManage && ! $eq->is_multi)
            <input type="hidden" name="items[{{ $lineKey }}][quantity_expected]" value="{{ $eq->quantity }}">
            @endif
          </td>
          <td><span class="badge badge-blue">{{ $eq->category_name }}</span></td>
          <td style="font-weight:600;font-family:var(--font-mono)">{{ $eq->quantity }}</td>

          @if ($eq->is_multi)
          <td colspan="2">
            @if ($canManage)
            <select class="unit-pick" style="padding:4px 8px;border:1.5px solid var(--accent);border-radius:20px;font-size:12px;font-weight:600;color:var(--accent);background:var(--acclight)"
                    onchange="pickUnit('{{ $lineKey }}', this.value, this)">
              <option value="">{{ $eq->quantity }} units — select to check</option>
              @foreach ($eq->slots as $s)
              @php $slotCond = $isOut ? $s->condition_out : $s->condition_in; @endphp
              <option value="{{ $s->slotKey }}">{{ $s->tag }} &middot; {{ $s->sn }}{{ in_array($slotCond, ['damaged','missing'], true) ? ' — Flagged' : '' }}</option>
              @endforeach
            </select>
            @else
            <span style="color:var(--muted);font-size:11px">{{ $qActual }} of {{ $eq->quantity }} — see units</span>
            @endif
          </td>
          @elseif ($isOut)
          <td>
            @if ($canManage)
            <input type="number" name="items[{{ $lineKey }}][quantity_actual]"
                   value="{{ $qActual }}" min="0" max="{{ $eq->quantity * 10 }}"
                   style="width:60px;padding:4px 8px;border:1px solid var(--border2);border-radius:5px;font-size:13px;font-family:var(--font-mono);background:var(--surface);color:var(--text);outline:none">
            @else
            {{ $qActual }}
            @endif
          </td>
          <td>
            @if ($canManage)
            <select name="items[{{ $lineKey }}][condition_out]"
                    style="padding:4px 8px;border:1px solid var(--border2);border-radius:5px;font-size:12px;background:var(--surface);color:var(--text);outline:none">
              @foreach ($condOut as $v => $l)
              <option value="{{ $v }}" {{ $condVal === $v ? 'selected' : '' }}>{{ $l }}</option>
              @endforeach
            </select>
            @else
            <span class="badge {{ $condBadge[$condVal] ?? 'badge-gray' }}">{{ $condOut[$condVal] ?? $condVal }}</span>
            @endif
          </td>
          @else
          <td>
            @if ($canManage)
            <input type="number" name="items[{{ $lineKey }}][quantity_actual]"
                   value="{{ $qActual }}" min="0" max="{{ $eq->quantity * 10 }}"
                   style="width:60px;padding:4px 8px;border:1px solid var(--border2);border-radius:5px;font-size:13px;font-family:var(--font-mono);background:var(--surface);color:var(--text);outline:none">
            @else
            {{ $qActual }}
            @endif
          </td>
          <td>
            @if ($canManage)
            <select name="items[{{ $lineKey }}][condition_in]"
                    onchange="highlightDamaged(this)"
                    style="padding:4px 8px;border:1px solid var(--border2);border-radius:5px;font-size:12px;background:var(--surface);color:var(--text);outline:none">
              @foreach ($condIn as $v => $l)
              <option value="{{ $v }}" {{ $condVal === $v ? 'selected' : '' }}>{{ $l }}</option>
              @endforeach
            </select>
            @else
            <span class="badge {{ $condBadge[$condVal] ?? 'badge-gray' }}">{{ $condIn[$condVal] ?? $condVal }}</span>
            @endif
          </td>
          @endif

          <td>
            @if ($eq->is_multi)
              @if ($isOut)
                <span class="badge {{ $qActual >= $eq->quantity ? 'badge-green' : ($qActual > 0 ? 'badge-yellow' : 'badge-gray') }}">{{ $qActual }}/{{ $eq->quantity }} Released</span>
              @else
                <span class="badge {{ $eq->any_damaged_in ? 'badge-red' : ($qActual >= $eq->quantity ? 'badge-green' : 'badge-gray') }}">
                  {{ $eq->any_damaged_in ? 'Damage Reported' : $qActual . '/' . $eq->quantity . ' Returned' }}
                </span>
              @endif
            @elseif ($isOut)
              @if ($eq->co_checked)
              <span class="badge badge-green">Released</span>
              @else
              <span class="badge badge-gray">Pending</span>
              @endif
            @else
              @if ($eq->ci_checked)
              <span class="badge {{ in_array($condVal, ['damaged', 'missing'], true) ? 'badge-red' : 'badge-green' }}">
                {{ in_array($condVal, ['damaged', 'missing'], true) ? ucfirst($condVal) : 'Returned' }}
              </span>
              @elseif ($eq->co_checked)
              <span class="badge badge-yellow">In Field</span>
              @else
              <span class="badge badge-gray">Not Out</span>
              @endif
            @endif
          </td>
          <td>
            @if ($eq->is_multi)
            <span style="font-size:.78rem;color:var(--muted)">—</span>
            @elseif ($canManage)
            <input type="text" name="items[{{ $lineKey }}][notes]"
                   value="{{ $notes }}"
                   placeholder="Notes…"
                   style="width:140px;padding:4px 8px;border:1px solid var(--border2);border-radius:5px;font-size:12px;background:var(--surface);color:var(--text);outline:none">
            @else
            <span style="font-size:.78rem;color:var(--muted)">{{ $notes ?: '—' }}</span>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if ($canManage)
  {{-- Unit Check panel: one multi-unit item's controls at a time, picked from that line's
       dropdown above, instead of everything expanding inline inside the table. Every unit's
       real, name-attributed inputs exist in the DOM the whole time (just hidden) so Save
       Checklist / Release All / Check All still submit every unit in one POST — the panel only
       toggles which group is visible, it never moves data between them. --}}
  <div class="unit-panel" id="unitPanel" style="display:none;margin:0 20px 16px;border:1.5px solid var(--accent);border-radius:10px;background:var(--acclight);overflow:hidden">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:12px 16px;background:var(--surface);border-bottom:1px solid var(--border2)">
      <div>
        <div id="upEyebrow" style="font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px">Checking</div>
        <div id="upTag" style="font-family:var(--font-mono);font-weight:700;font-size:14px;margin-top:3px"></div>
        <div id="upSn" style="font-size:11px;color:var(--muted);font-family:var(--font-mono)"></div>
      </div>
      <button type="button" onclick="closeUnitPanel()" style="border:none;background:none;color:var(--accent);cursor:pointer"><i data-feather="x" style="width:16px;height:16px"></i></button>
    </div>
    <div id="upIncidentBanner" style="display:none;padding:8px 16px;background:var(--redl);color:var(--red);font-size:11.5px;font-weight:600"></div>
    <div style="padding:14px 16px">
      @foreach ($equipLines->where('is_multi', true) as $eq)
      @foreach ($eq->slots as $s)
      @php
        $slotBase = $eq->line_key . '_' . $s->slotKey;
        $slotChecked = $isOut ? $s->co_checked : $s->ci_checked;
        $slotCond = $isOut ? $s->condition_out : $s->condition_in;
        $slotNotes = $isOut ? $s->co_notes : $s->ci_notes;
        $condOptions = $isOut ? $condOut : $condIn;
      @endphp
      <div class="unit-slot-group" data-line="{{ $eq->line_key }}" data-slot="{{ $s->slotKey }}"
           data-name="{{ $eq->item_name }}" data-tag="{{ $s->tag }}" data-sn="{{ $s->sn }}"
           data-incident="{{ $s->incident_number }}" style="display:none">
        <input type="hidden" name="items[{{ $slotBase }}][quantity_expected]" value="1">
        <label style="display:flex;align-items:center;gap:9px;margin-bottom:12px;cursor:pointer">
          <input type="checkbox" name="items[{{ $slotBase }}][checked]" value="1" {{ $slotChecked ? 'checked' : '' }}
                 style="width:20px;height:20px;accent-color:var(--accent);cursor:pointer"
                 class="checklist-cb"
                 onchange="refreshLineTally('{{ $eq->line_key }}')">
          <span style="font-weight:600;font-size:13px">This unit {{ $isOut ? 'released' : 'checked in' }}</span>
        </label>
        <div style="display:flex;gap:20px;flex-wrap:wrap">
          <div>
            <div style="font-size:10px;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px">Condition</div>
            <select name="items[{{ $slotBase }}][condition_{{ $dir }}]" style="padding:5px 9px;border:1px solid var(--border2);border-radius:6px;font-size:12px">
              @foreach ($condOptions as $v => $l)
              <option value="{{ $v }}" {{ $slotCond === $v ? 'selected' : '' }}>{{ $l }}</option>
              @endforeach
            </select>
          </div>
          <div style="flex:1;min-width:160px">
            <div style="font-size:10px;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px">Notes</div>
            <input type="text" name="items[{{ $slotBase }}][notes]" value="{{ $slotNotes }}" placeholder="Notes…"
                   style="width:100%;padding:5px 9px;border:1px solid var(--border2);border-radius:6px;font-size:12px">
          </div>
        </div>
      </div>
      @endforeach
      @endforeach
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:var(--surface);border-top:1px solid var(--border2)">
      <button type="button" class="btn btn-outline btn-sm" onclick="stepUnit(-1)"><i data-feather="arrow-left" style="width:13px;height:13px"></i> Prev Unit</button>
      <span id="upCount" style="font-size:11.5px;color:var(--sub);font-weight:600"></span>
      <button type="button" class="btn btn-outline btn-sm" onclick="stepUnit(1)">Next Unit <i data-feather="arrow-right" style="width:13px;height:13px"></i></button>
    </div>
  </div>
  @endif

  @if ($canManage)
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div style="font-size:.83rem;color:var(--sub)">
      @if ($dir === 'out')
        {{ $gateOk ? 'All gates clear. Mark items as released and verify condition before sending to field.' : 'Resolve gate issues above before releasing equipment.' }}
      @else
        Mark items as returned and note any damage for incident reporting. Damaged/missing items will generate incident reports automatically.
      @endif
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      @if ($dir === 'out' && ! $gateOk)
      <span style="font-size:.78rem;color:var(--red);font-weight:600"><i data-feather="lock" style="width:13px;height:13px;vertical-align:middle"></i> Blocked</span>
      @endif
      <button type="submit" form="checklistForm" class="btn btn-primary"
              {{ ($dir === 'out' && ! $gateOk) ? 'disabled title="Resolve gate issues first"' : '' }}>
        <i data-feather="save"></i> Save Checklist {{ strtoupper($dir) }}
      </button>
      @if ($dir === 'in' && $inChecked === $totalItems && $totalItems > 0)
      <a href="{{ route('booking-detail', $bid) }}" class="btn btn-outline" style="border-color:var(--green);color:var(--green)">
        <i data-feather="check-circle"></i> All Returned — View Booking
      </a>
      @endif
    </div>
  </div>
  </form>
  @endif
</div>

@endif

@push('scripts')
<script>
function checkAll() {
  const cbs = document.querySelectorAll('.checklist-cb');
  const anyUnchecked = Array.from(cbs).some(c => !c.checked);
  cbs.forEach(cb => {
    cb.checked = anyUnchecked;
    const row = cb.closest('tr');
    if (row) row.style.background = anyUnchecked ? 'var(--greenl)' : '';
  });
  document.querySelectorAll('.unit-slot-group').forEach(g => refreshLineTally(g.dataset.line));
}

function releaseAll() {
  document.querySelectorAll('.checklist-cb').forEach(cb => {
    cb.checked = true;
    const row = cb.closest('tr');
    if (row) row.style.background = 'var(--greenl)';
  });
  document.querySelectorAll('.unit-slot-group').forEach(g => refreshLineTally(g.dataset.line));
  document.getElementById('checklistForm').submit();
}

function highlightDamaged(sel) {
  const row = sel.closest('tr');
  if (['damaged','missing'].includes(sel.value)) {
    row.style.background = 'var(--redl)';
  } else {
    const cb = row.querySelector('.checklist-cb');
    row.style.background = cb?.checked ? 'var(--greenl)' : '';
  }
}

// A multi-unit line's grp-badge tally is derived straight from its own hidden checkboxes —
// there is no separate JS state to keep in sync, so this can never drift from what will
// actually be submitted.
function refreshLineTally(line) {
  const groups = document.querySelectorAll(`.unit-slot-group[data-line="${line}"]`);
  const total = groups.length;
  const done = Array.from(groups).filter(g => g.querySelector('.checklist-cb')?.checked).length;
  const badge = document.querySelector(`.grp-badge[data-line="${line}"]`);
  if (badge) {
    badge.lastChild.textContent = done + '/' + total;
    badge.classList.toggle('on', done >= total);
  }
}

let currentLine = null;
function lineGroups(line) {
  return Array.from(document.querySelectorAll(`.unit-slot-group[data-line="${line}"]`));
}
function renderSlot(line, slotKey) {
  const groups = lineGroups(line);
  groups.forEach(g => g.style.display = 'none');
  const active = groups.find(g => g.dataset.slot === slotKey);
  if (! active) return;
  active.style.display = '';
  document.getElementById('upEyebrow').textContent = 'Checking · ' + active.dataset.name;
  document.getElementById('upTag').textContent = active.dataset.tag;
  document.getElementById('upSn').textContent = active.dataset.sn;
  const idx = groups.indexOf(active);
  document.getElementById('upCount').textContent = 'Unit ' + (idx + 1) + ' of ' + groups.length;
  const banner = document.getElementById('upIncidentBanner');
  if (active.dataset.incident) {
    banner.style.display = '';
    banner.textContent = active.dataset.incident + ' already open for this unit.';
  } else {
    banner.style.display = 'none';
  }
}
function pickUnit(line, slotKey, selectEl) {
  if (! slotKey) { closeUnitPanel(); return; }
  currentLine = line;
  document.getElementById('unitPanel').style.display = '';
  renderSlot(line, slotKey);
  document.getElementById('unitPanel').scrollIntoView({behavior: 'smooth', block: 'center'});
}
function stepUnit(dir) {
  if (! currentLine) return;
  const groups = lineGroups(currentLine);
  const visible = groups.findIndex(g => g.style.display !== 'none');
  const next = (visible + dir + groups.length) % groups.length;
  renderSlot(currentLine, groups[next].dataset.slot);
}
function closeUnitPanel() {
  document.getElementById('unitPanel').style.display = 'none';
  currentLine = null;
  document.querySelectorAll('.unit-pick').forEach(s => s.value = '');
}
</script>
@endpush
@endsection
