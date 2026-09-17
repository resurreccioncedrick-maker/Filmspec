<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Saves images to Laravel's own "public" disk (storage/app/public, exposed via the
 * public/storage symlink from `php artisan storage:link`). image_path values stored
 * in the database keep the same relative shape ("assets/uploads/{subDir}/...") they
 * always had, so no data migration was needed when this moved off the legacy webroot.
 * Mirrors core/upload.php's handleImageUpload()/deleteOldImage() in intent, not location.
 */
class ImageUpload
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];

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
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            return ['success' => false, 'path' => '', 'error' => 'Only JPG, PNG, WebP, GIF allowed.'];
        }

        $ext = strtolower($file->getClientOriginalExtension());
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
