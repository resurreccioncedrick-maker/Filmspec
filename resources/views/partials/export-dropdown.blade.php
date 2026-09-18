{{--
  Shared "Export ▾" dropdown for staff data-table/report pages. Plain <a href> links (no
  AJAX) so the browser's normal file-download flow just works with streamDownload()/dompdf.

  Props:
    id           - unique DOM id suffix (required when a page has more than one dropdown,
                   e.g. Billing's 3 tabs)
    formats      - which formats to offer, default all three
    exportParam  - query param name identifying WHAT to export (default 'export')
    exportValue  - if set, exportParam holds this fixed value (a "type", Reports-style
                   multi-dataset pages) and the format goes in a separate formatParam instead
                   of exportParam directly (simple single-dataset pages, the common case)
    formatParam  - query param name for the format when exportValue is set (default 'format')
    label        - button label, default "Export"
--}}
@php
  $formats = $formats ?? ['csv', 'xlsx', 'pdf'];
  $exportParam = $exportParam ?? 'export';
  $formatParam = $formatParam ?? 'format';
  $ddId = 'exportDd' . ($id ?? '');
  $baseQuery = collect(request()->query())->except([$exportParam, $formatParam])->all();
  $formatLabels = ['csv' => 'CSV (.csv)', 'xlsx' => 'Excel (.xlsx)', 'pdf' => 'PDF (.pdf)'];
  $formatIcons = [
      'csv' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline>',
      'xlsx' => '<rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M8 8l8 8M16 8l-8 8"></path>',
      'pdf' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="15" x2="15" y2="15"></line>',
  ];
@endphp
<div class="export-btn" id="{{ $ddId }}Wrap">
  <button type="button" class="btn btn-outline btn-sm export-toggle" data-target="{{ $ddId }}" style="font-size:12px">
    <i data-feather="download" style="width:13px;height:13px"></i> {{ $label ?? 'Export' }}
  </button>
  <div class="export-menu" id="{{ $ddId }}">
    @foreach ($formats as $fmt)
    @php
      $q = $baseQuery;
      if (isset($exportValue)) {
          $q[$exportParam] = $exportValue;
          $q[$formatParam] = $fmt;
      } else {
          $q[$exportParam] = $fmt;
      }
    @endphp
    <a href="{{ request()->url() . '?' . http_build_query($q) }}" class="export-opt">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $formatIcons[$fmt] !!}</svg>{{ $formatLabels[$fmt] }}
    </a>
    @endforeach
  </div>
</div>
