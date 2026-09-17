<?php

namespace App\Support;

class Money
{
    public static function amtWords(float $amount): string
    {
        $amount = (int) round($amount);
        if ($amount <= 0) {
            return 'ZERO';
        }

        $ones = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
        $tens = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];

        $three = function ($n) use ($ones, $tens, &$three) {
            if (! $n) return '';
            if ($n < 20) return $ones[$n];
            if ($n < 100) return $tens[$n / 10 | 0] . ($n % 10 ? ' ' . $ones[$n % 10] : '');

            return $ones[$n / 100 | 0] . ' HUNDRED' . ($n % 100 ? ' ' . $three($n % 100) : '');
        };

        $parts = [];
        if ($amount >= 1000000) {
            $parts[] = $three((int) ($amount / 1000000)) . ' MILLION';
            $amount %= 1000000;
        }
        if ($amount >= 1000) {
            $parts[] = $three((int) ($amount / 1000)) . ' THOUSAND';
            $amount %= 1000;
        }
        if ($amount > 0) {
            $parts[] = $three($amount);
        }

        return implode(' ', $parts);
    }
}
