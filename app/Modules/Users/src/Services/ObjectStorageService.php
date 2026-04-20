<?php

namespace App\Modules\Users\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ObjectStorageService
{
    private string $disk = 'r2_docs';

    public function upload(UploadedFile $file, string $folder, string $visibility = 'public'): array
    {
        $uuid = (string) Str::uuid();
        $ext  = $file->getClientOriginalExtension();
        $path = $folder . '/' . $uuid . '.' . $ext;

        Storage::disk($this->disk)->put($path, $file->get(), $visibility);

        $cdnBase = rtrim(config('app.cdn_base_url', ''), '/');

        return [
            'storage_path' => $path,
            'cdn_url'      => $visibility === 'public' ? $cdnBase . '/' . $path : null,
        ];
    }

    public function delete(string $storagePath): void
    {
        Storage::disk($this->disk)->delete($storagePath);
    }
}
