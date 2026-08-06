<?php

namespace App\Http\Controllers;

use App\Services\ConditionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConditionController extends Controller
{
    public function search(Request $request, ConditionService $conditionService): JsonResponse
    {
        $user = $request->user();

        if (! $user?->canViewConsultScreen()) {
            abort(403);
        }

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:120'],
        ]);

        $results = $conditionService->search($validated['q'], $user);

        return response()->json([
            'query' => $validated['q'],
            'results' => $results,
            'allow_free_text' => true,
        ]);
    }
}
