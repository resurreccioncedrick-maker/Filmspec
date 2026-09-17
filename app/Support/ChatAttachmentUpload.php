<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Saves a file/image attached to a chat message (booking messages or the general support
 * chat) to the private `local` disk — these can carry client-supplied documents or photos,
 * so they live alongside DocumentController's uploads rather than the public disk, and only
 * ever leave it through an authenticated download route. Mirrors ImageUpload's shape.
 */
class ChatAttachmentUpload
{
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private const MAX_SIZE = 10 * 1024 * 1024;

    public static function handle(?UploadedFile $file): array
    {
        $empty = ['success' => false, 'path' => '', 'name' => '', 'mime' => '', 'size' => 0, 'error' => ''];

        if (! $file) {
            return $empty;
        }
        if (! $file->isValid()) {
            return array_merge($empty, ['error' => 'Upload error: ' . $file->getErrorMessage()]);
        }
        if ($file->getSize() > self::MAX_SIZE) {
            return array_merge($empty, ['error' => 'File too large. Max 10MB.']);
        }
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            return array_merge($empty, ['error' => 'Only images, PDF, or Word files are allowed.']);
        }

        $ext = strtolower($file->getClientOriginalExtension()) ?: 'bin';
        $storedName = Str::random(40) . '.' . $ext;

        $stored = Storage::disk('local')->putFileAs('chat-attachments', $file, $storedName);
        if ($stored === false) {
            return array_merge($empty, ['error' => 'Failed to save the uploaded file.']);
        }

        return [
            'success' => true, 'path' => 'chat-attachments/' . $storedName,
            'name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType(),
            'size' => $file->getSize(), 'error' => '',
        ];
    }
}
