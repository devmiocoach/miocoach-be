<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Certification;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Services\ObjectStorageService;
use Illuminate\Http\UploadedFile;

class StoreCertificationAction
{
    public function __construct(private readonly ObjectStorageService $storage) {}

    public function handle(Coach $coach, UploadedFile $file, array $data): Certification
    {
        $uploaded = $this->storage->upload($file, 'certifications/' . $coach->id);

        return $coach->certificationRecords()->create([
            'name'         => $data['name'],
            'issuer'       => $data['issuer'] ?? null,
            'issued_at'    => $data['issued_at'] ?? null,
            'file_url'     => $uploaded['cdn_url'],
            'storage_path' => $uploaded['storage_path'],
        ]);
    }
}
