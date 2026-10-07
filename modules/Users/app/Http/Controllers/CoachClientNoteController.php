<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\AddClientNoteAction;
use App\Modules\Users\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CoachClientNoteController extends Controller
{
    public function store(Request $request, Client $client, AddClientNoteAction $action): RedirectResponse
    {
        $coach = $request->user()->coach;

        abort_if(! $coach, 403);
        abort_if(! $coach->athletes()->where('athlete_id', $client->id)->exists(), 403);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $action->handle($client, $coach, $validated['content']);

        return back();
    }
}
