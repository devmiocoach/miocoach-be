<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Concerns\HandlesCoachOnboarding;
use App\Modules\Users\Http\Requests\OnboardingPublishRequest;
use App\Modules\Users\Http\Requests\OnboardingStep1Request;
use App\Modules\Users\Http\Requests\OnboardingStep2Request;
use App\Modules\Users\Http\Requests\OnboardingStep3Request;
use App\Modules\Users\Http\Requests\OnboardingStep4Request;
use App\Modules\Users\Http\Requests\OnboardingStep5Request;
use App\Modules\Users\Http\Requests\OnboardingStep6Request;
use App\Modules\Users\Http\Requests\OnboardingStep7Request;
use App\Modules\Users\Http\Requests\OnboardingStep8Request;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Rules\MaxVideoDuration;
use App\Modules\Users\Services\ObjectStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachOnboardingController extends Controller
{
    use HandlesCoachOnboarding;

    public function show(Request $request): JsonResponse
    {
        $coach = $this->getOrCreateCoach($request);

        return response()->json(['data' => $this->onboardingStatePayload($coach)]);
    }

    public function updateStep1(OnboardingStep1Request $request): JsonResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep1($coach, $request->validated(), $request->user());

        return $this->stepResponse($coach);
    }

    public function updateStep2(OnboardingStep2Request $request): JsonResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep2($coach, $request->validated());

        return $this->stepResponse($coach);
    }

    public function updateStep3(OnboardingStep3Request $request): JsonResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep3($coach, $request->validated());

        return $this->stepResponse($coach);
    }

    public function updateStep4(OnboardingStep4Request $request): JsonResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep4($coach, $request->validated());

        return $this->stepResponse($coach);
    }

    public function updateStep5(OnboardingStep5Request $request): JsonResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep5($coach, $request->validated());

        return $this->stepResponse($coach);
    }

    public function updateStep6(OnboardingStep6Request $request): JsonResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep6($coach, $request->validated());

        return $this->stepResponse($coach);
    }

    public function updateStep7(OnboardingStep7Request $request): JsonResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep7($coach, $request->validated());

        return $this->stepResponse($coach);
    }

    public function updateStep8(OnboardingStep8Request $request): JsonResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep8($coach, $request->validated());

        return $this->stepResponse($coach);
    }

    public function publish(OnboardingPublishRequest $request): JsonResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $result = $this->publishCoach($coach);

        if (! $result['ok']) {
            return response()->json([
                'message' => 'Completa tutte le sezioni prima di pubblicare.',
                'missing_sections' => $result['missing'],
            ], 422);
        }

        return response()->json(['data' => $this->onboardingStatePayload($coach->fresh())]);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
            'type' => ['required', 'in:profile,cover'],
        ]);
        $coach = $this->getOrCreateCoach($request);
        $url = $this->storeCoachPhoto($coach, $request->file('photo'), $request->input('type'));

        return response()->json(['url' => $url]);
    }

    public function uploadGalleryPhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp']]);
        $coach = $this->getOrCreateCoach($request);
        if ($coach->media()->where('collection_name', 'gallery')->count() >= 6) {
            return response()->json(['error' => 'Limite massimo di 6 foto raggiunto.'], 422);
        }

        return response()->json($this->storeGalleryPhoto($coach, $request->file('photo')));
    }

    public function deleteGalleryPhoto(Request $request): JsonResponse
    {
        $request->validate(['media_id' => ['required', 'integer']]);
        $coach = $this->getOrCreateCoach($request);
        $result = $this->removeGalleryPhoto($coach, $request->integer('media_id'));

        if ($result === null) {
            return response()->json(['error' => 'Foto non trovata.'], 404);
        }

        return response()->json($result);
    }

    public function uploadVideo(Request $request): JsonResponse
    {
        $request->validate([
            'video' => ['required', 'file', 'max:102400', 'mimes:mp4,mov', new MaxVideoDuration(30)],
        ]);
        $coach = $this->getOrCreateCoach($request);

        return response()->json(['url' => $this->storeIntroVideo($coach, $request->file('video'))]);
    }

    public function uploadCertification(Request $request, ObjectStorageService $storage): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'certification_index' => ['required', 'integer', 'min:0'],
        ]);
        $coach = $this->getOrCreateCoach($request);
        $url = $this->storeCertificationFile($coach, $request->file('file'), $request->integer('certification_index'), $storage);

        if ($url === null) {
            return response()->json(['error' => 'Certificazione non trovata.'], 404);
        }

        return response()->json(['url' => $url]);
    }

    public function uploadInsurance(Request $request, ObjectStorageService $storage): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:pdf']]);
        $coach = $this->getOrCreateCoach($request);

        return response()->json(['url' => $this->storeInsuranceFile($coach, $request->file('file'), $storage)]);
    }

    private function stepResponse(Coach $coach): JsonResponse
    {
        $state = $this->onboardingStatePayload($coach->fresh());

        return response()->json(['data' => [
            'coach' => $state['coach'],
            'completed_steps' => $state['completed_steps'],
        ]]);
    }
}
