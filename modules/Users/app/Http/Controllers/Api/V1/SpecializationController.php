<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Specialization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecializationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Specialization::query()->orderBy('name');

        if ($request->filled('q')) {
            $safe = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $request->input('q'));
            $query->where('name', 'ILIKE', '%'.$safe.'%');
        }

        $specializations = $query->limit(20)->get(['id', 'name', 'slug']);

        return response()->json($specializations);
    }
}
