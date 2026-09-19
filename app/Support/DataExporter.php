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

    /**
     * For structured multi-section documents (e.g. Profit & Loss) that don't fit one flat
     * table — a sequence of labeled blocks instead. Each section is
     * ['title' => ?string, 'headers' => ?string[], 'rows' => array<array<int,string>>]:
     * a section with no 'headers' is just plain label/value lines (no header row rendered).
     */
    public static function respondSections(string $format, string $title, array $sections, string $filenameBase): StreamedResponse|Response
    {
        $stamp = now()->format('Y-m-d_His');

        return match ($format) {
            'xlsx' => self::sectionsXlsx($title, $sections, "$filenameBase-$stamp.xlsx"),
            'pdf' => self::sectionsPdf($title, $sections, "$filenameBase-$stamp.pdf"),
            default => self::sectionsCsv($sections, "$filenameBase-$stamp.csv"),
        };
    }

    private static function sectionsCsv(array $sections, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($sections) {
            $out = fopen('php://output', 'w');
            foreach ($sections as $section) {
                if (! empty($section['title'])) {
                    fputcsv($out, [$section['title']], ',', '"', '\\');
                }
                if (! empty($section['headers'])) {
                    fputcsv($out, $section['headers'], ',', '"', '\\');
                }
                foreach ($section['rows'] as $row) {
                    fputcsv($out, $row, ',', '"', '\\');
                }
                fputcsv($out, [], ',', '"', '\\');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private static function sectionsXlsx(string $title, array $sections, string $filename): StreamedResponse
    {
        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $title), 0, 31) ?: 'Export');

        $r = 1;
        $maxCols = 1;
        foreach ($sections as $section) {
            if (! empty($section['title'])) {
                $ws->setCellValue("A$r", $section['title']);
                $ws->getStyle("A$r")->getFont()->setBold(true);
                $r++;
            }
            if (! empty($section['headers'])) {
                $ws->fromArray($section['headers'], null, "A$r");
                $ws->getStyle("A$r:" . $ws->getHighestColumn($r) . $r)->getFont()->setBold(true);
                $maxCols = max($maxCols, count($section['headers']));
                $r++;
            }
            foreach ($section['rows'] as $row) {
                $ws->fromArray($row, null, "A$r");
                $maxCols = max($maxCols, count($row));
                $r++;
            }
            $r++; // blank separator row
        }
        foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($maxCols)) as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = IOFactory::createWriter($sheet, 'Xlsx');

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private static function sectionsPdf(string $title, array $sections, string $filename): Response
    {
        $html = view('exports.sections-pdf', ['title' => $title, 'sections' => $sections])->render();

        return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download($filename);
    }
}
