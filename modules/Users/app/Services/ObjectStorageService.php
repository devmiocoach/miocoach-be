<?php

namespace App\Modules\Users\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ObjectStorageService
{
    private function disk(): string
    {
        return config('filesystems.disks.r2_docs') ? 'r2_docs' : 'public';
    }

    public function upload(UploadedFile $file, string $folder, string $visibility = 'public'): array
    {
        if (! in_array($visibility, ['public', 'private'], true)) {
            throw new \InvalidArgumentException("Invalid visibility: {$visibility}");
        }

        $disk = $this->disk();
        $uuid = (string) Str::uuid();
        $ext = $file->getClientOriginalExtension();
        $path = $folder.'/'.$uuid.'.'.$ext;

        Storage::disk($disk)->put($path, $file->get(), $visibility);

        if ($disk === 'public') {
            $url = Storage::disk('public')->url($path);
        } else {
            $cdnBase = rtrim(config('app.cdn_base_url', ''), '/');
            $url = $visibility === 'public' ? $cdnBase.'/'.$path : null;
        }

        return [
            'storage_path' => $path,
            'cdn_url' => $url,
        ];
    }

    public function delete(string $storagePath): void
    {
        Storage::disk($this->disk())->delete($storagePath);
    }
}
