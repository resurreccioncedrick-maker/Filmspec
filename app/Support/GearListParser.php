<?php

namespace App\Support;

class GearListParser
{
    private const MAX_ROWS = 500;

    private const DESCRIPTOR_WORDS = [
        'mount', 'mounts', 'kit', 'degree', 'degrees', 'controller', 'softbox', 'soft', 'box',
        'grid', 'reflector', 'fresnel', 'bowens', 'forza', 'ccm', 'cm', 'vat', 'inclusive',
        'included', 'for', 'with', 'and', 'stand', 'diffuser', 'eggcrate', 'egg', 'crate',
        'lens', 'optic', 'projection', 'strip', 'parabolic', 'lantern', 'silk', 'frame',
        'backing', 'clamp', 'head', 'arm', 'long', 'short', 'full', 'set',
    ];

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

    private const HEADER_WORDS = [
        'camera', 'cameras', 'lens', 'lenses', 'light', 'lights', 'lighting', 'grip', 'grips',
        'stand', 'stands', 'frame', 'frames', 'gobo', 'gobos', 'clamp', 'clamps', 'other', 'others',
        'misc', 'miscellaneous', 'sound', 'audio', 'accessory', 'accessories', 'monitor', 'monitors',
        'power', 'cable', 'cables', 'electrical', 'production', 'wardrobe', 'makeup', 'art',
        'transpo', 'transport', 'transportation', 'catering', 'purchasing', 'rental', 'rentals',
        'equipment', 'crew', 'vehicle', 'vehicles', 'scaff', 'scaffolding', 'dolly', 'silk',
        'backing', 'black', 'filter', 'filters', 'battery', 'batteries', 'media', 'storage',
        'set', 'sets', 'requirement', 'requirements', 'gel', 'gels', 'for',
    ];

    private static function isSectionHeader(string $line): bool
    {
        if (str_ends_with($line, ':') && ! preg_match('/^\s*\d/', $line)) {
            return true;
        }

        if (strlen($line) > 40 || str_contains($line, ':') || preg_match('/\d/', $line)) {
            return false;
        }

        $words = preg_split('/[^A-Za-z]+/', $line, -1, PREG_SPLIT_NO_EMPTY);
        if (! $words) {
            return false;
        }
        foreach ($words as $word) {
            if (! in_array(strtolower($word), self::HEADER_WORDS, true)) {
                return false;
            }
        }

        return true;
    }

    private static function extractQuantity(string $line): array
    {
        if (preg_match('/^\s*(\d+)\s*pcs?\b\.?\s*(.*)$/i', $line, $m)) {
            return [(int) $m[1], $m[2]];
        }

        if (preg_match('/^\s*(\d+)\s+x\s+(.*)$/i', $line, $m)) {
            return [(int) $m[1], $m[2]];
        }

        if (preg_match('/^\s*(\d+)\s+(.*)$/', $line, $m)) {
            return [(int) $m[1], $m[2]];
        }

        return [1, $line];
    }

    private static function detectExternalSource(string $text): array
    {
        if (preg_match('/[-–—]?\s*from\s+([A-Z][\w.\'&]*(?:\s+[A-Z][\w.\'&]*){0,3})\s*$/', $text, $m)) {
            return [true, trim($m[1])];
        }

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
