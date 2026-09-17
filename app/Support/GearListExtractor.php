<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

/**
 * Turns an uploaded gear-list file (Word, Excel, or CSV/plain text) into a flat list of
 * text lines — one per paragraph/row — so GearListParser can run the same
 * quantity/category/external-source parsing regardless of source format.
 */
class GearListExtractor
{
    public static function extractLines(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return match ($ext) {
            'csv', 'txt' => self::fromCsv($file->getRealPath()),
            'xlsx', 'xls' => self::fromExcel($file->getRealPath()),
            'doc', 'docx' => self::fromWord($file->getRealPath()),
            default => [],
        };
    }

    private static function fromCsv(string $path): array
    {
        $lines = [];
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        // Strip a UTF-8 BOM if present so the first line's first cell isn't corrupted.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $cells = array_filter(array_map('trim', $row), fn ($c) => $c !== '');
            if ($cells) {
                $lines[] = implode(' ', $cells);
            }
        }
        fclose($handle);

        return $lines;
    }

    private static function fromExcel(string $path): array
    {
        $lines = [];
        $spreadsheet = SpreadsheetIOFactory::load($path);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = [];
                foreach ($row->getCellIterator() as $cell) {
                    $value = trim((string) $cell->getFormattedValue());
                    if ($value !== '') {
                        $cells[] = $value;
                    }
                }
                if ($cells) {
                    $lines[] = implode(' ', $cells);
                }
            }
        }

        return $lines;
    }

    private static function fromWord(string $path): array
    {
        $lines = [];
        $phpWord = WordIOFactory::load($path);

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof Table) {
                    foreach ($element->getRows() as $row) {
                        $cellText = [];
                        foreach ($row->getCells() as $cell) {
                            $text = trim(self::containerText($cell));
                            if ($text !== '') {
                                $cellText[] = $text;
                            }
                        }
                        if ($cellText) {
                            $lines[] = implode(' ', $cellText);
                        }
                    }
                    continue;
                }

                $text = trim(self::elementText($element));
                if ($text !== '') {
                    $lines[] = $text;
                }
            }
        }

        return $lines;
    }

    private static function containerText(AbstractContainer $container): string
    {
        $parts = [];
        foreach ($container->getElements() as $element) {
            $parts[] = self::elementText($element);
        }

        return implode(' ', array_filter($parts, fn ($p) => $p !== ''));
    }

    private static function elementText(mixed $element): string
    {
        if ($element instanceof Text) {
            return $element->getText();
        }
        if ($element instanceof AbstractContainer) {
            return self::containerText($element);
        }

        return '';
    }
}
