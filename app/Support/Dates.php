<?php

namespace App\Support;

class Dates
{
    public static function relTime(string $dt): string
    {
        $diff = time() - strtotime($dt);
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 172800) return 'Yesterday';

        return date('M j', strtotime($dt));
    }
}
