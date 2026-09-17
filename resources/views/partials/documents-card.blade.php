{{-- Documents card (Part 18). Included from booking-detail.blade.php (Documents tab) and
     client-documents.blade.php, scoped by whichever of $booking/$client is in context.
     Expects: $documents, $canManage, and exactly one of $booking / $client. --}}
@php
  $docBookingId = isset($booking) ? $booking->booking_id : null;
  $docClientId = isset($client) ? $client->client_id : null;
  $docCatBadge = ['contract' => 'badge-blue', 'id' => 'badge-purple', 'permit' => 'badge-orange', 'other' => 'badge-gray'];
  $docCatLabel = ['contract' => 'Contract', 'id' => 'ID', 'permit' => 'Permit', 'other' => 'Other'];
  // Computed here rather than requiring every host controller to pass it — delete is gated
  // the same way everywhere Documents appears.
  $canManage = $canManage ?? in_array(auth()->user()->role->role_name ?? '', config('filmspec.manage_roles'), true);
@endphp

@if (session('doc_msg'))
<div class="alert alert-{{ session('doc_msg')['type'] }}" data-autohide>
  <i data-feather="{{ session('doc_msg')['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {{ session('doc_msg')['text'] }}
</div>
@endif

<div class="card" style="margin-bottom:16px" id="docsCard">
  <div class="card-header">
    <h2 class="card-title">Documents <span class="badge badge-gray" style="margin-left:4px">{{ $documents->count() }}</span></h2>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      @if ($documents->count() > 0)
      <div class="search-input-wrap" style="min-width:160px">
        <i data-feather="search" style="width:13px;height:13px"></i>
        <input type="text" id="docSearchTbl" placeholder="Search documents…" oninput="listFilter({rowSelector:'#docsCard tbody tr', searchId:'docSearchTbl', filterId:'docCatFilter'})">
      </div>
      <select id="docCatFilter" class="form-control" style="width:auto;font-size:12px;padding:7px 10px" onchange="listFilter({rowSelector:'#docsCard tbody tr', searchId:'docSearchTbl', filterId:'docCatFilter'})">
        <option value="">All categories</option>
        <option value="contract">Contract</option>
        <option value="id">ID</option>
        <option value="permit">Permit</option>
        <option value="other">Other</option>
      </select>
      @endif
      <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modalUploadDocument')">
        <i data-feather="upload"></i> Upload
      </button>
    </div>
  </div>
  <div class="table-wrap">
    @if ($documents->isEmpty())
    <div class="empty-state">
      <i data-feather="file-text"></i>
      <h3>No documents yet</h3>
      <p>Contracts, IDs and permits uploaded here are only reachable by staff with access to this {{ $docBookingId ? 'booking' : 'client' }}.</p>
    </div>
    @else
    <table>
      <thead>
        <tr><th>File</th><th>Category</th><th>Uploaded</th><th style="text-align:right">Size</th><th>Actions</th></tr>
      </thead>
      <tbody>
      @foreach ($documents as $d)
      <tr data-filter="{{ $d->category }}">
        <td>
          <div style="font-weight:600">{{ $d->original_name }}</div>
          @if ($d->note)<div style="font-size:.72rem;color:var(--text-muted)">{{ $d->note }}</div>@endif
          @if ($d->category === 'contract')
            @if ($d->signed_at)
            <div style="font-size:.7rem;color:#15803d;font-weight:600;margin-top:2px">Signed by {{ trim((string) $d->signed_by_name) ?: 'client' }} on {{ date('M j, Y', strtotime($d->signed_at)) }}</div>
            @else
            <div style="font-size:.7rem;color:var(--text-muted);margin-top:2px">Not yet signed by client</div>
            @endif
          @endif
        </td>
        <td><span class="badge {{ $docCatBadge[$d->category] ?? 'badge-gray' }}">{{ $docCatLabel[$d->category] ?? ucfirst($d->category) }}</span></td>
        <td style="font-size:.8rem;color:var(--text-muted)">
          {{ date('M j, Y', strtotime($d->created_at)) }}
          @if (trim((string) $d->uploaded_by_name))<div>by {{ $d->uploaded_by_name }}</div>@endif
        </td>
        <td style="text-align:right;font-size:.8rem;color:var(--text-muted);white-space:nowrap">
          {{ number_format($d->size_bytes / 1024, 0) }} KB
        </td>
        <td>
          <div style="display:flex;gap:6px">
            <a href="{{ route('documents.download', $d->document_id) }}" class="btn btn-outline btn-sm" title="Download"><i data-feather="download"></i></a>
            @if ($canManage)
            <form method="POST" action="{{ route('documents.destroy', $d->document_id) }}" onsubmit="return confirm('Delete {{ addslashes($d->original_name) }}?')">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-outline btn-sm" style="border-color:var(--red);color:var(--red)" title="Delete"><i data-feather="trash-2"></i></button>
            </form>
            @endif
          </div>
        </td>
      </tr>
      @endforeach
      </tbody>
    </table>
    @endif
  </div>
</div>

<!-- UPLOAD DOCUMENT MODAL -->
<div class="modal-overlay" id="modalUploadDocument">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h3><i data-feather="upload" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Upload Document</h3>
      <button class="modal-close" onclick="closeModal('modalUploadDocument')">&times;</button>
    </div>
    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
      @csrf
      @if ($docBookingId)<input type="hidden" name="booking_id" value="{{ $docBookingId }}">@endif
      @if ($docClientId)<input type="hidden" name="client_id" value="{{ $docClientId }}">@endif
      <div class="modal-body">
        <div class="form-group">
          <label>File <span style="color:var(--red)">*</span></label>
          <input type="file" name="file" class="form-control" required accept=".pdf,.doc,.docx,image/*">
          <div style="font-size:.72rem;color:var(--text-muted);margin-top:4px">PDF, Word, or image · Max 10MB</div>
        </div>
        <div class="form-group">
          <label>Category</label>
          <select name="category" class="form-control">
            <option value="contract">Contract</option>
            <option value="id">ID</option>
            <option value="permit">Permit</option>
            <option value="other" selected>Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>Note (optional)</label>
          <input type="text" name="note" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalUploadDocument')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="upload"></i> Upload</button>
      </div>
    </form>
  </div>
</div>
