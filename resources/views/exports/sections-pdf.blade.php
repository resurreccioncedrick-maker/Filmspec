<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 24px; }
  .hdr { border-bottom: 2px solid #003D80; padding-bottom: 10px; margin-bottom: 16px; }
  .hdr h1 { font-size: 16px; color: #003D80; margin: 0 0 3px; }
  .hdr .sub { font-size: 10px; color: #64748b; }
  .section { margin-bottom: 16px; }
  .section-title { font-size: 11.5px; font-weight: bold; color: #003D80; margin-bottom: 6px; text-transform: uppercase; }
  table { width: 100%; border-collapse: collapse; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  th { background: #003D80; color: #fff; text-align: left; padding: 5px 8px; font-size: 9.5px; }
  td { padding: 4px 8px; border-bottom: 1px solid #e2e8f0; font-size: 9.5px; }
  .ftr { margin-top: 14px; font-size: 8.5px; color: #94a3b8; text-align: right; }
</style>
</head>
<body>
  <div class="hdr">
    <h1>{{ $title }}</h1>
    <div class="sub">Generated {{ now()->format('M j, Y g:ia') }}</div>
  </div>

  @foreach ($sections as $section)
  <div class="section">
    @if (! empty($section['title']))
    <div class="section-title">{{ $section['title'] }}</div>
    @endif
    @if (! empty($section['rows']))
    <table>
      @if (! empty($section['headers']))
      <thead>
        <tr>
          @foreach ($section['headers'] as $h)
          <th>{{ $h }}</th>
          @endforeach
        </tr>
      </thead>
      @endif
      <tbody>
        @foreach ($section['rows'] as $row)
        <tr>
          @foreach ($row as $cell)
          <td>{{ $cell }}</td>
          @endforeach
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>
  @endforeach

  <div class="ftr">FilmSpec &middot; {{ now()->format('Y-m-d H:i') }}</div>
</body>
</html>
