<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  /* dompdf-safe: DejaVu Sans ships with dompdf itself, no remote font fetch needed. */
  * { box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 24px; }
  .hdr { border-bottom: 2px solid #003D80; padding-bottom: 10px; margin-bottom: 14px; }
  .hdr h1 { font-size: 16px; color: #003D80; margin: 0 0 3px; }
  .hdr .sub { font-size: 10px; color: #64748b; }
  table { width: 100%; border-collapse: collapse; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  th { background: #003D80; color: #fff; text-align: left; padding: 6px 8px; font-size: 9.5px; }
  td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 9.5px; }
  tbody tr:nth-child(even) { background: #f8fafc; }
  .ftr { margin-top: 14px; font-size: 8.5px; color: #94a3b8; text-align: right; }
</style>
</head>
<body>
  <div class="hdr">
    <h1>{{ $title }}</h1>
    <div class="sub">
      @if ($subtitle){{ $subtitle }} &middot; @endif
      Generated {{ now()->format('M j, Y g:ia') }} &middot; {{ count($rows) }} row{{ count($rows) === 1 ? '' : 's' }}
    </div>
  </div>

  @if (empty($rows))
  <p>No matching records.</p>
  @else
  <table>
    <thead>
      <tr>
        @foreach ($headers as $h)
        <th>{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach ($rows as $row)
      <tr>
        @foreach ($row as $cell)
        <td>{{ $cell }}</td>
        @endforeach
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  <div class="ftr">FilmSpec &middot; {{ now()->format('Y-m-d H:i') }}</div>
</body>
</html>
