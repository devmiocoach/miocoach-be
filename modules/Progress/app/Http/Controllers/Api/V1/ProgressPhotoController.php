<?php

namespace Modules\Progress\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Progress\Http\Controllers\Api\V1\Concerns\VerifiesCoachOwnership;
use Modules\Progress\Http\Requests\StoreProgressPhotoRequest;
use Modules\Progress\Models\ProgressPhoto;

class ProgressPhotoController extends Controller
{
    use VerifiesCoachOwnership;

    public function index(Request $request, Client $client): JsonResponse
    {
        /** @var Coach|null $coach */
        $coach = $request->user()->coach;
        if (! $coach || ! $this->ownsClient($coach, $client)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $photos = ProgressPhoto::where('client_id', $client->id)
            ->where('coach_id', $coach->id)
            ->orderByDesc('taken_at')
            ->paginate(24);

        return response()->json($photos);
    }

    public function store(StoreProgressPhotoRequest $request, Client $client): JsonResponse
    {
        /** @var Coach|null $coach */
        $coach = $request->user()->coach;
        if (! $coach || ! $this->ownsClient($coach, $client)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $path = $request->file('photo')->store(
            "progress-photos/{$coach->id}/{$client->id}",
            'private'
        );

        $photo = ProgressPhoto::create([
            'client_id' => $client->id,
            'coach_id' => $coach->id,
            'storage_key' => $path,
            'pose' => $request->validated('pose'),
            'taken_at' => $request->validated('taken_at'),
            'notes' => $request->validated('notes'),
        ]);

        return response()->json(['data' => $photo], 201);
    }

    public function destroy(Request $request, Client $client, ProgressPhoto $photo): JsonResponse
    {
        /** @var Coach|null $coach */
        $coach = $request->user()->coach;
        if (! $coach || $photo->coach_id !== $coach->id || $photo->client_id !== $client->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        Storage::disk('private')->delete($photo->storage_key);
        if ($photo->thumbnail_key) {
            Storage::disk('private')->delete($photo->thumbnail_key);
        }

        $photo->delete();

        return response()->json(null, 204);
    }
}
