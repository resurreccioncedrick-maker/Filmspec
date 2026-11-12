<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Settings
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        return $value ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
    }
}
