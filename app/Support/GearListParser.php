<?php

namespace App\Support;

/**
 * Classifies raw text lines (from GearListExtractor) into gear-list rows: a quantity, the
 * item text to match against the catalog, the section/category the line fell under, and
 * whether it names an outside supplier rather than FilmSpec's own inventory.
 *
 * Matching itself (and what "external"/"not found"/etc. mean for the cart) lives in
 * CartController::bulkAdd() — this class only turns messy free text into structured rows.
 */
class GearListParser
{
    private const MAX_ROWS = 500;

    /**
     * Parenthetical content made only of these words (plus digits/punctuation, handled
     * separately) reads as a spec/descriptor, not a vendor name — e.g. "(Bowens Mount)",
     * "(60Ccm)", "(for Forza 300,500,720B)" should never be flagged external.
     */
    private const DESCRIPTOR_WORDS = [
        'mount', 'mounts', 'kit', 'degree', 'degrees', 'controller', 'softbox', 'soft', 'box',
        'grid', 'reflector', 'fresnel', 'bowens', 'forza', 'ccm', 'cm', 'vat', 'inclusive',
        'included', 'for', 'with', 'and', 'stand', 'diffuser', 'eggcrate', 'egg', 'crate',
        'lens', 'optic', 'projection', 'strip', 'parabolic', 'lantern', 'silk', 'frame',
        'backing', 'clamp', 'head', 'arm', 'long', 'short', 'full', 'set',
    ];

    /**
     * @param  string[]  $lines  raw lines from GearListExtractor
     * @return array{rows: array<int, array{raw: string, quantity: int, text: string, category: ?string, external: bool, external_source: ?string}>, truncated: bool}
     */
    public static function parse(array $lines): array
    {
        $rows = [];
        $category = null;
        $truncated = false;

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (self::isSectionHeader($line)) {
                $category = rtrim($line, ": \t");
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                $truncated = true;
                break;
            }

            [$quantity, $text] = self::extractQuantity($line);
            $text = trim($text);
            if ($text === '') {
                continue;
            }

            [$external, $source] = self::detectExternalSource($text);

            $rows[] = [
                'raw' => $line,
                'quantity' => $quantity,
                'text' => $text,
                'category' => $category,
                'external' => $external,
                'external_source' => $source,
            ];
        }

        return ['rows' => $rows, 'truncated' => $truncated];
    }

    private static function isSectionHeader(string $line): bool
    {
        // A header is a short label ending in ':' with no leading quantity — "Lighting:",
        // "Camera accessories:", "JA Tadena lights:". A line that happens to end in ':' but
        // starts with a number (unlikely in practice) is treated as an item instead.
        return str_ends_with($line, ':') && ! preg_match('/^\s*\d/', $line);
    }

    /**
     * @return array{0: int, 1: string} [quantity, remaining text]
     */
    private static function extractQuantity(string $line): array
    {
        // Style A: "2pc", "1pcs", "4pc" — digit glued directly to a pc/pcs suffix.
        if (preg_match('/^\s*(\d+)\s*pcs?\b\.?\s*(.*)$/i', $line, $m)) {
            return [(int) $m[1], $m[2]];
        }

        // Style B: "20     x     C-Stand..." — a standalone "x" token between whitespace.
        // Requires whitespace on both sides so "12x12 Black backing" is never misread.
        if (preg_match('/^\s*(\d+)\s+x\s+(.*)$/i', $line, $m)) {
            return [(int) $m[1], $m[2]];
        }

        // Style C: a bare leading number and a gap — "2    Hihi Roller stand", "30    Sandbag".
        if (preg_match('/^\s*(\d+)\s+(.*)$/', $line, $m)) {
            return [(int) $m[1], $m[2]];
        }

        // No leading quantity at all — e.g. "Electrician", "power box" — default to 1 and
        // let the whole line stand as the item text.
        return [1, $line];
    }

    /**
     * @return array{0: bool, 1: ?string} [is external, the detected vendor name if any]
     */
    private static function detectExternalSource(string $text): array
    {
        // "...- from JA Tadena" / "...from Boy's Rentals" — an explicit attribution.
        if (preg_match('/[-–—]?\s*from\s+([A-Z][\w.\'&]*(?:\s+[A-Z][\w.\'&]*){0,3})\s*$/', $text, $m)) {
            return [true, trim($m[1])];
        }

        // A parenthetical that reads as a name, not a spec — "(JA Tadena)" but not
        // "(Bowens Mount)" or "(60Ccm)": short, title-cased, no digits, no descriptor words.
        if (preg_match_all('/\(([^)]+)\)/', $text, $matches)) {
            foreach ($matches[1] as $inner) {
                $inner = trim($inner);
                if ($inner === '' || preg_match('/\d/', $inner)) {
                    continue;
                }
                $words = preg_split('/\s+/', $inner);
                if (count($words) > 4) {
                    continue;
                }
                $looksLikeName = true;
                foreach ($words as $word) {
                    $clean = strtolower(trim($word, ".,'"));
                    if ($clean === '' || in_array($clean, self::DESCRIPTOR_WORDS, true) || ! preg_match('/^[A-Z]/', $word)) {
                        $looksLikeName = false;
                        break;
                    }
                }
                if ($looksLikeName) {
                    return [true, $inner];
                }
            }
        }

        return [false, null];
    }
}
