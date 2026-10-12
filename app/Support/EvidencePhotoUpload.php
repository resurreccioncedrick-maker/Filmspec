<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Saves a photo attached to an incident report, maintenance record, or repair/purchase ticket
 * to the private `local` disk — same convention as ChatAttachmentUpload and the inline
 * proof-of-payment validation (10MB cap, image/PDF mimes, random filename), not the public
 * ImageUpload disk used for equipment/accessory catalog photos: evidence photos are never
 * meant to be publicly servable, only viewable through an authenticated route per record.
 */
class EvidencePhotoUpload
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];

    private const MAX_SIZE = 10 * 1024 * 1024;

    public static function handle(?UploadedFile $file, string $subDir): array
    {
        $empty = ['success' => false, 'path' => '', 'error' => ''];

        if (! $file) {
            return $empty;
        }
        if (! $file->isValid()) {
            return array_merge($empty, ['error' => 'Upload error: ' . $file->getErrorMessage()]);
        }
        if ($file->getSize() > self::MAX_SIZE) {
            return array_merge($empty, ['error' => 'Photo is too large. Max 10MB.']);
        }
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            return array_merge($empty, ['error' => 'Photo must be a JPG, PNG, or PDF file.']);
        }

        $ext = strtolower($file->getClientOriginalExtension()) ?: 'bin';
        $storedName = Str::random(40) . '.' . $ext;

        $stored = Storage::disk('local')->putFileAs($subDir, $file, $storedName);
        if ($stored === false) {
            return array_merge($empty, ['error' => 'Failed to save the uploaded photo.']);
        }

        return ['success' => true, 'path' => $subDir . '/' . $storedName, 'error' => ''];
    }
}
