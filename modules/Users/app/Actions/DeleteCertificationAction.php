<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Certification;
use App\Modules\Users\Services\ObjectStorageService;

class DeleteCertificationAction
{
    public function __construct(private readonly ObjectStorageService $storage) {}

    public function handle(Certification $certification): void
    {
        $storagePath = $certification->storage_path;
        $certification->delete();
        $this->storage->delete($storagePath);
    }
}
