<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait FileUploadTrait
{
    public function uploadFile(UploadedFile $file, ?string $oldPath = null, ?string $path = 'uploads'): ?string
    {
        if (! $file->isValid()) {
            return null;
        }

        $ignorePath = ['/default/avatar.png', '/default/banner.png', '/default/shop.png'];

        if ($oldPath && ! in_array($oldPath, $ignorePath, true)) {
            $this->deleteFile($oldPath);
        }

        $folderPath = public_path($path);
        File::ensureDirectoryExists($folderPath);

        $filename = Str::uuid().'.'.$this->publicImageExtension($file);

        $file->move($folderPath, $filename);

        $filepath = $path.'/'.$filename;

        return $filepath;
    }

    public function uploadPrivateFile(UploadedFile $file, ?string $oldPath = null, ?string $path = 'uploads'): ?string
    {
        if (! $file->isValid()) {
            return null;
        }

        /* $ignorePath = ['/default/avatar.png']; */

        /* if ($oldPath && File::exists(public_path($oldPath)) && !in_array($oldPath, $ignorePath)) {
            File::delete(public_path($oldPath));
        } */

        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => throw ValidationException::withMessages([
                'file' => 'This private file type is not allowed.',
            ]),
        };
        $filename = Str::uuid().'.'.$extension;

        $path = $file->storeAs($path, $filename, 'local');

        return $path ?: null;
    }

    public function deleteFile(string $path): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', trim($path)), '/');

        if ($relativePath === '' || in_array('..', explode('/', $relativePath), true)) {
            return false;
        }

        $publicRoot = realpath(public_path());
        $resolvedPath = realpath(public_path($relativePath));

        if (
            $publicRoot === false
            || $resolvedPath === false
            || ! str_starts_with($resolvedPath, $publicRoot.DIRECTORY_SEPARATOR)
            || ! File::isFile($resolvedPath)
        ) {
            return false;
        }

        return File::delete($resolvedPath);
    }

    private function publicImageExtension(UploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp', 'image/x-ms-bmp' => 'bmp',
            'image/avif' => 'avif',
            default => throw ValidationException::withMessages([
                'file' => 'This public image type is not allowed.',
            ]),
        };
    }
}
