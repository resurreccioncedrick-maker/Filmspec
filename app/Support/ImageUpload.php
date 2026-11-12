<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageUpload
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];

    private const EXT_BY_MIME = [
        'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png',
        'image/webp' => 'webp', 'image/gif' => 'gif',
    ];

    private const MAX_SIZE = 5 * 1024 * 1024;

    public static function handle(?UploadedFile $file, string $subDir): array
    {
        if (! $file) {
            return ['success' => false, 'path' => '', 'error' => 'No file uploaded'];
        }
        if (! $file->isValid()) {
            return ['success' => false, 'path' => '', 'error' => 'Upload error: ' . $file->getErrorMessage()];
        }
        if ($file->getSize() > self::MAX_SIZE) {
            return ['success' => false, 'path' => '', 'error' => 'File too large. Max 5MB.'];
        }
        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            return ['success' => false, 'path' => '', 'error' => 'Only JPG, PNG, WebP, GIF allowed.'];
        }

        $ext = self::EXT_BY_MIME[$mime];
        $filename = uniqid($subDir . '_', true) . '.' . $ext;
        $path = 'assets/uploads/' . $subDir . '/' . $filename;

        $stored = Storage::disk('public')->putFileAs('assets/uploads/' . $subDir, $file, $filename);
        if ($stored === false) {
            return ['success' => false, 'path' => '', 'error' => 'Failed to save the uploaded file.'];
        }

        return ['success' => true, 'path' => $path, 'error' => ''];
    }

    public static function deleteOld(?string $path): void
    {
        if (! $path) {
            return;
        }
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
