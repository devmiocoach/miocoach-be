<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientFile;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Services\ObjectStorageService;
use Illuminate\Http\UploadedFile;

class StoreClientFileAction
{
    public function __construct(private readonly ObjectStorageService $storage) {}

    public function handle(Client $client, Coach $coach, UploadedFile $file, ?string $displayName = null): ClientFile
    {
        $result = $this->storage->upload(
            $file,
            "client-files/{$coach->id}/{$client->id}",
            'private'
        );

        $record              = new ClientFile();
        $record->coach_id    = $coach->id;
        $record->client_id   = $client->id;
        $record->storage_key = $result['storage_path'];
        $record->name        = $displayName ?? $file->getClientOriginalName();
        $record->mime_type   = $file->getMimeType() ?? $file->getClientMimeType();
        $record->size        = $file->getSize();
        $record->save();

        return $record;
    }
}
