<?php

namespace App\Modules\Users\Http\Controllers;

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
use App\Modules\Users\Rules\MaxVideoDuration;
use App\Modules\Users\Services\ObjectStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    use HandlesCoachOnboarding;

    public function index(Request $request): Response
    {
        $coach = $this->getOrCreateCoach($request);
        $requested = max(1, (int) $request->query('step', 1));
        $initialStep = min($requested, $this->maxReachableStep($coach));
        $state = $this->onboardingStatePayload($coach);

        return Inertia::render('Users::Coach/Onboarding/Index', [
            'initialStep' => $initialStep,
            'coach' => $state['coach'],
            'completedSteps' => $state['completed_steps'],
            'specializations' => $state['specializations'],
            'packages' => $coach->activePackages()->get(['id', 'name', 'sessions_count', 'price_cents', 'sort_order']),
            'availabilitySlots' => $coach->availabilities()->get(['id', 'day_of_week', 'start_time', 'end_time', 'duration_minutes']),
            'certifications' => $coach->certificationRecords()->get(['id', 'name', 'issuer', 'issued_at', 'file_url']),
        ])->withoutSSR();
    }

    public function updateStep1(OnboardingStep1Request $request): RedirectResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep1($coach, $request->validated(), $request->user());

        return redirect()->route('coach.onboarding.index', ['step' => 2])
            ->with('success', 'Profilo aggiornato.');
    }

    public function updateStep2(OnboardingStep2Request $request): RedirectResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep2($coach, $request->validated());

        return redirect()->route('coach.onboarding.index', ['step' => 3])
            ->with('success', 'Specializzazioni aggiornate.');
    }

    public function updateStep3(OnboardingStep3Request $request): RedirectResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep3($coach, $request->validated());

        return redirect()->route('coach.onboarding.index', ['step' => 4])
            ->with('success', 'Certificazioni aggiornate.');
    }

    public function updateStep4(OnboardingStep4Request $request): RedirectResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep4($coach, $request->validated());

        return redirect()->route('coach.onboarding.index', ['step' => 5])
            ->with('success', 'Disponibilità aggiornata.');
    }

    public function updateStep5(OnboardingStep5Request $request): RedirectResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep5($coach, $request->validated());

        return redirect()->route('coach.onboarding.index', ['step' => 6])
            ->with('success', 'Sede aggiornata.');
    }

    public function updateStep6(OnboardingStep6Request $request): RedirectResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep6($coach, $request->validated());

        return redirect()->route('coach.onboarding.index', ['step' => 7])
            ->with('success', 'Tariffe aggiornate.');
    }

    public function updateStep7(OnboardingStep7Request $request): RedirectResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep7($coach, $request->validated());

        return redirect()->route('coach.onboarding.index', ['step' => 8])
            ->with('success', 'Gallery aggiornata.');
    }

    public function updateStep8(OnboardingStep8Request $request): RedirectResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $this->persistStep8($coach, $request->validated());

        return redirect()->route('coach.onboarding.index', ['step' => 9])
            ->with('success', 'Policy aggiornate.');
    }

    public function publish(OnboardingPublishRequest $request): RedirectResponse
    {
        $coach = $this->getOrCreateCoach($request);
        $result = $this->publishCoach($coach);

        if (! $result['ok']) {
            return back()->withErrors(['completeness' => 'Completa tutte le sezioni prima di pubblicare.'])
                ->with('missing_sections', $result['missing']);
        }

        return redirect()->route('coach.dashboard')
            ->with('success', 'Profilo pubblicato! Benvenuto su MioCoach.');
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
        $request->validate([
            'photo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $coach = $this->getOrCreateCoach($request);

        if ($coach->media()->where('collection_name', 'gallery')->count() >= 6) {
            return response()->json(['error' => 'Limite massimo di 6 foto raggiunto.'], 422);
        }

        $result = $this->storeGalleryPhoto($coach, $request->file('photo'));

        return response()->json($result);
    }

    public function uploadVideo(Request $request): JsonResponse
    {
        $request->validate([
            'video' => [
                'required',
                'file',
                'max:102400',
                'mimes:mp4,mov',
                new MaxVideoDuration(30),
            ],
        ]);

        $coach = $this->getOrCreateCoach($request);
        $url = $this->storeIntroVideo($coach, $request->file('video'));

        return response()->json(['url' => $url]);
    }

    public function uploadCertification(Request $request, ObjectStorageService $storage): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'certification_index' => ['required', 'integer', 'min:0'],
        ]);

        $coach = $this->getOrCreateCoach($request);
        $index = $request->integer('certification_index');

        $url = $this->storeCertificationFile($coach, $request->file('file'), $index, $storage);
        if ($url === null) {
            return response()->json(['error' => 'Certificazione non trovata.'], 404);
        }

        return response()->json(['url' => $url]);
    }

    public function uploadInsurance(Request $request, ObjectStorageService $storage): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf'],
        ]);

        $coach = $this->getOrCreateCoach($request);
        $url = $this->storeInsuranceFile($coach, $request->file('file'), $storage);

        return response()->json(['url' => $url]);
    }

    public function deleteGalleryPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
        ]);

        $coach = $this->getOrCreateCoach($request);
        $result = $this->removeGalleryPhoto($coach, $request->integer('media_id'));

        if ($result === null) {
            return response()->json(['error' => 'Foto non trovata.'], 404);
        }

        return response()->json($result);
    }
}
