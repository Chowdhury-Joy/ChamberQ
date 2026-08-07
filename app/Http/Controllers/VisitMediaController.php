<?php

namespace App\Http\Controllers;

use App\Models\VisitRecord;
use App\Services\VisitMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VisitMediaController extends Controller
{
    public function uploadVoice(Request $request, VisitMediaService $visitMediaService): JsonResponse
    {
        $user = $request->user();

        if (! $user?->canRecordVisitNotes()) {
            abort(403);
        }

        $request->validate([
            'voice' => ['required', 'file', 'max:5120'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('voice');

        if (! in_array($file->getMimeType(), VisitMediaService::allowedVoiceMimeTypes(), true)) {
            return response()->json([
                'message' => __('Unsupported audio format.'),
            ], 422);
        }

        $path = $visitMediaService->storeVoiceUpload($file);

        return response()->json([
            'path' => $path,
        ]);
    }

    public function transcribe(Request $request, \App\Services\Transcription\VisitTranscriptionService $transcriptionService): JsonResponse
    {
        $user = $request->user();

        if (! $user?->canRecordVisitNotes()) {
            abort(403);
        }

        if (! tenant()?->hasFeature('voice_transcription')) {
            abort(403, __('Voice transcription is not enabled for this practice.'));
        }

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($transcriptionService->transcribeFromPath($validated['path'], $user));
    }

    public function voice(Request $request, VisitRecord $visitRecord): BinaryFileResponse
    {
        if (! $request->user()?->canViewVisitNotes()) {
            abort(403);
        }

        $absolute = app(VisitMediaService::class)->absolutePath($visitRecord->voice_path);

        if (! $absolute) {
            abort(404);
        }

        return response()->file($absolute);
    }

    public function photo(Request $request, VisitRecord $visitRecord): BinaryFileResponse
    {
        if (! $request->user()?->canViewVisitNotes()) {
            abort(403);
        }

        $absolute = app(VisitMediaService::class)->absolutePath($visitRecord->photo_path);

        if (! $absolute) {
            abort(404);
        }

        return response()->file($absolute);
    }
}
