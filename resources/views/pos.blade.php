@extends('layouts.app')

@section('pageTitle', 'Point of Sale')

@section('breadcrumb')
<span>POS</span>
@endsection

@section('content')
@php $posBase = route('pos'); @endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<div class="card">
  <div class="card-header">
    <h3>Quick POS Sale</h3>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ $posBase }}" id="posForm">
      @csrf
      <input type="hidden" name="action" value="quick_sale">

      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label>Client</label>
            <select name="client_id" class="form-control" required>
              <option value="">Select Client</option>
              @foreach ($clients as $client)
              <option value="{{ $client->client_id }}">
                {{ $client->company_name ?: $client->contact_person }}
              </option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="col-md-4">
          <div class="form-group">
            <label>Project Title</label>
            <input type="text" name="project_title" class="form-control" placeholder="POS Sale - {{ date('Y-m-d') }}">
          </div>
        </div>

        <div class="col-md-4">
          <div class="form-group">
            <label>Rental Days</label>
            <input type="number" name="days" class="form-control" value="1" min="1" max="30">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>Equipment</label>
        <div class="equipment-grid" style="max-height:200px;overflow-y:auto;border:1px solid #ddd;padding:10px;">
          @foreach ($equipment as $equip)
          <div class="equipment-item">
            <label>
              <input type="checkbox" name="equipment_ids[]" value="{{ $equip->equipment_id }}">
              <strong>{{ $equip->equipment_name }}</strong> ({{ $equip->category_name }})
              <br>
              <small>₱{{ number_format($equip->daily_rate, 2) }}/day</small>
            </label>
          </div>
          @endforeach
        </div>
      </div>

      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" class="form-control" rows="2" placeholder="Special requirements, delivery notes, etc."></textarea>
      </div>

      <div class="form-group">
        <button type="submit" class="btn btn-primary">
          <i data-feather="shopping-cart"></i> Process Sale
        </button>
      </div>
    </form>
  </div>
</div>

<style>
.equipment-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 10px;
}
.equipment-item {
  padding: 8px;
  border: 1px solid #eee;
  border-radius: 4px;
  cursor: pointer;
}
.equipment-item:hover {
  background: #f8f9fa;
}
.equipment-item label {
  cursor: pointer;
  display: block;
}
</style>

@push('scripts')
<script>
document.getElementById('posForm')?.addEventListener('submit', function (e) {
  var checkedBoxes = document.querySelectorAll('input[name="equipment_ids[]"]:checked');
  if (checkedBoxes.length === 0) {
    e.preventDefault();
    alert('Please select at least one equipment item');
    return false;
  }
  return true;
});
</script>
@endpush
@endsection
