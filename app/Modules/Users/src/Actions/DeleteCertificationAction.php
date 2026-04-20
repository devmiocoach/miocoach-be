<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Certification;
use App\Modules\Users\Services\ObjectStorageService;

class DeleteCertificationAction
{
    public function __construct(private readonly ObjectStorageService $storage) {}

    public function handle(Certification $certification): void
    {
        $this->storage->delete($certification->storage_path);
        $certification->delete();
    }
}
