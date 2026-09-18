<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared "turn this dataset into a downloadable file" for every staff data-table/report page's
 * Export ▾ dropdown (partials/export-dropdown.blade.php). Callers pass rows that are ALREADY
 * display-formatted (dates via ->format(), money via App\Support\Money, status enums turned into
 * their plain-text label) — CSV/Excel/PDF never re-derive formatting themselves, so the three
 * formats can never drift apart from each other or from what's shown on screen.
 */
class DataExporter
{
    /**
     * @param  string  $format  csv|xlsx|pdf
     * @param  string[]  $headers  column labels, in display order
     * @param  iterable<array<int, string>>  $rows  each row a plain array of display strings, same order as $headers
     */
    public static function respond(string $format, string $title, array $headers, iterable $rows, string $filenameBase, ?string $subtitle = null): StreamedResponse|Response
    {
        $rows = is_array($rows) ? $rows : iterator_to_array($rows);
        $stamp = now()->format('Y-m-d_His');

        return match ($format) {
            'xlsx' => self::xlsx($title, $headers, $rows, "$filenameBase-$stamp.xlsx"),
            'pdf' => self::pdf($title, $headers, $rows, "$filenameBase-$stamp.pdf", $subtitle),
            default => self::csv($headers, $rows, "$filenameBase-$stamp.csv"),
        };
    }

    private static function csv(array $headers, array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers, ',', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '\\');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private static function xlsx(string $title, array $headers, array $rows, string $filename): StreamedResponse
    {
        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $title), 0, 31) ?: 'Export');
        $ws->fromArray(array_merge([$headers], $rows));
        $ws->getStyle('1:1')->getFont()->setBold(true);
        foreach (range('A', $ws->getHighestColumn()) as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = IOFactory::createWriter($sheet, 'Xlsx');

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private static function pdf(string $title, array $headers, array $rows, string $filename, ?string $subtitle): Response
    {
        $orientation = count($headers) > 6 ? 'landscape' : 'portrait';
        $html = view('exports.tabular-pdf', [
            'title' => $title, 'subtitle' => $subtitle, 'headers' => $headers, 'rows' => $rows,
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4', $orientation)->download($filename);
    }
}
