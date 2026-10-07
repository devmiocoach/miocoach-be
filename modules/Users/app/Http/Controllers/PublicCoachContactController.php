<?php

namespace App\Modules\Users\Http\Controllers;

use App\Helpers\Toast;
use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\SendContactRequestAction;
use App\Modules\Users\Http\Requests\PublicCoachContactRequest;
use App\Modules\Users\Models\Coach;
use Illuminate\Http\RedirectResponse;

class PublicCoachContactController extends Controller
{
    public function __invoke(PublicCoachContactRequest $request, Coach $coach, SendContactRequestAction $action): RedirectResponse
    {
        $action->handle($coach, $request->user()->client, $request->validated('message'), $request->validated('type'));

        Toast::success('Richiesta inviata! Il coach ti risponderà a breve.');

        return back();
    }
}
