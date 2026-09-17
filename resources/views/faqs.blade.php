@extends('layouts.app')

@section('pageTitle', 'FAQ / Help Center')

@section('breadcrumb')
<span>FAQ / Help Center</span>
@endsection

@section('topbarActions')
@if ($canPost)
<button onclick="openModal_addFaq()" class="btn btn-primary btn-sm"><i data-feather="plus"></i> Add FAQ</button>
@endif
@endsection

@section('content')

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<div style="font-size:.8rem;color:var(--muted);margin-bottom:16px">
  Entries here appear on the client portal's Help page. Only active entries are shown to clients, grouped and ordered by category.
</div>

@if ($faqs->isEmpty())
<div class="card">
  <div class="empty-state">
    <i data-feather="help-circle"></i>
    <h3>No FAQ entries yet</h3>
    <p>Add your first entry to start building the client Help Center.</p>
  </div>
</div>
@else
@foreach ($faqs as $category => $items)
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <h2 class="card-title">{{ $category }}</h2>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:36px">Order</th>
          <th>Question</th>
          <th>Answer</th>
          <th style="width:80px">Status</th>
          @if ($canPost)<th style="width:110px">Actions</th>@endif
        </tr>
      </thead>
      <tbody>
      @foreach ($items as $f)
      <tr>
        <td style="font-size:.83rem;color:var(--muted)">{{ $f->sort_order }}</td>
        <td style="font-weight:600;max-width:260px">{{ $f->question }}</td>
        <td style="font-size:.83rem;color:var(--muted);max-width:380px">{{ \Illuminate\Support\Str::limit($f->answer, 140) }}</td>
        <td>
          @if ($f->is_active)
          <span class="badge badge-green">Active</span>
          @else
          <span class="badge badge-gray">Hidden</span>
          @endif
        </td>
        @if ($canPost)
        <td>
          <div style="display:flex;gap:6px">
            <button type="button" class="btn btn-sm btn-outline" title="Edit"
                    onclick="openEditFaq({{ Illuminate\Support\Js::from($f)->toHtml() }})"><i data-feather="edit-2"></i></button>
            <form method="POST" onsubmit="return confirm('Delete this FAQ entry?')">
              @csrf
              <input type="hidden" name="action" value="delete_faq">
              <input type="hidden" name="faq_id" value="{{ $f->faq_id }}">
              <button type="submit" class="btn btn-sm btn-outline" title="Delete"><i data-feather="trash-2"></i></button>
            </form>
          </div>
        </td>
        @endif
      </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>
@endforeach
@endif

@include('partials.page-activity')
@include('partials.access-log')

@if ($canPost)
<!-- ADD/EDIT FAQ MODAL -->
<div class="modal-overlay" id="modalAddFaq">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <h3 id="faqModalTitle"><i data-feather="help-circle" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Add FAQ</h3>
      <button class="modal-close" onclick="closeModal('modalAddFaq')">&times;</button>
    </div>
    <form method="POST">
      @csrf
      {{-- One form serves add and edit; the action and id are swapped in by JS. --}}
      <input type="hidden" name="action" id="faqAction" value="add_faq">
      <input type="hidden" name="faq_id" id="faqId">
      <div class="modal-body">
        <div class="form-group">
          <label>Question <span style="color:var(--red)">*</span></label>
          <input type="text" name="question" id="faqQuestion" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Answer <span style="color:var(--red)">*</span></label>
          <textarea name="answer" id="faqAnswer" class="form-control" rows="4" required></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Category</label>
            <input type="text" name="category" id="faqCategory" class="form-control" list="faqCategoryList" placeholder="e.g. Pricing">
            <datalist id="faqCategoryList">
              @foreach ($faqs->keys() as $cat)
              <option value="{{ $cat }}">
              @endforeach
            </datalist>
          </div>
          <div class="form-group">
            <label>Sort Order</label>
            <input type="number" name="sort_order" id="faqSortOrder" class="form-control" value="0">
          </div>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_active" id="faqIsActive" value="1" checked style="width:auto">
            Visible to clients
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalAddFaq')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="faqSubmit"><i data-feather="plus"></i> Add FAQ</button>
      </div>
    </form>
  </div>
</div>
@endif

@endsection

@push('scripts')
<script>
function openModal_addFaq() {
  document.getElementById('faqModalTitle').innerHTML =
    '<i data-feather="help-circle" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Add FAQ';
  document.getElementById('faqAction').value = 'add_faq';
  document.getElementById('faqId').value = '';
  document.getElementById('faqQuestion').value = '';
  document.getElementById('faqAnswer').value = '';
  document.getElementById('faqCategory').value = '';
  document.getElementById('faqSortOrder').value = '0';
  document.getElementById('faqIsActive').checked = true;
  document.getElementById('faqSubmit').innerHTML = '<i data-feather="plus"></i> Add FAQ';
  openModal('modalAddFaq');
  if (window.feather) feather.replace();
}

function openEditFaq(f) {
  document.getElementById('faqModalTitle').innerHTML =
    '<i data-feather="edit-2" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Edit FAQ';
  document.getElementById('faqAction').value = 'edit_faq';
  document.getElementById('faqId').value = f.faq_id;
  document.getElementById('faqQuestion').value = f.question || '';
  document.getElementById('faqAnswer').value = f.answer || '';
  document.getElementById('faqCategory').value = f.category || '';
  document.getElementById('faqSortOrder').value = f.sort_order ?? 0;
  document.getElementById('faqIsActive').checked = !!f.is_active;
  document.getElementById('faqSubmit').innerHTML = '<i data-feather="check"></i> Save Changes';
  openModal('modalAddFaq');
  if (window.feather) feather.replace();
}
</script>
@endpush
