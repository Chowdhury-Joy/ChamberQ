<?php

namespace App\Http\Controllers;

use App\Models\VisitRecord;
use App\Services\VisitMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitMediaController extends Controller
{
    public function uploadVoice(Request $request, VisitMediaService $visitMediaService): JsonResponse
    {
        $user = $request->user();

        // Role AND practice. These routes carry no Filament panel guard, and
        // every panel shares one session cookie, so "is a doctor" alone would
        // let a doctor of another tenant write into this tenant's media
        // directory. See User::belongsToCurrentTenant().
        if (! $user?->canRecordVisitNotes() || ! $user->belongsToCurrentTenant()) {
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

    public function uploadReportPhoto(Request $request, VisitMediaService $visitMediaService): JsonResponse
    {
        $user = $request->user();

        if (! $user?->canRecordVisitNotes() || ! $user->belongsToCurrentTenant()) {
            abort(403);
        }

        $request->validate([
            'photo' => ['required', 'file', 'max:5120'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('photo');

        if (! in_array($file->getMimeType(), VisitMediaService::allowedPhotoMimeTypes(), true)) {
            return response()->json([
                'message' => __('Unsupported image format.'),
            ], 422);
        }

        $path = $visitMediaService->storeReportPhotoUpload($file);

        return response()->json([
            'path' => $path,
        ]);
    }

    public function voice(Request $request, VisitRecord $visitRecord): StreamedResponse
    {
        $this->authorizeClinicalRead($request);

        $media = app(VisitMediaService::class);

        // Same rule the report photos already enforce: the stored path is
        // client-written, so it must sit under this practice's own directory
        // before anything is streamed from it.
        if (! $media->isOwnedVoicePath($visitRecord->voice_path)) {
            abort(404);
        }

        return $media->streamResponse($visitRecord->voice_path) ?? abort(404);
    }

    public function photo(Request $request, VisitRecord $visitRecord): StreamedResponse
    {
        $this->authorizeClinicalRead($request);

        $media = app(VisitMediaService::class);

        if (! $media->isOwnedPhotoPath($visitRecord->photo_path)) {
            abort(404);
        }

        return $media->streamResponse($visitRecord->photo_path) ?? abort(404);
    }

    public function reportPhoto(Request $request, VisitRecord $visitRecord, int $index): StreamedResponse
    {
        $this->authorizeClinicalRead($request);

        $paths = $visitRecord->report_photo_paths ?? [];
        $path = $paths[$index] ?? null;

        if (! is_string($path) || ! app(VisitMediaService::class)->isOwnedReportPhotoPath($path)) {
            abort(404);
        }

        return app(VisitMediaService::class)->streamResponse($path)
            ?? abort(404);
    }

    /**
     * A doctor **of this practice**.
     *
     * Route-model binding scopes the record to the current tenant, but the
     * signed-in user is not scoped to anything — the session is shared across
     * every panel on the host. Without the tenant half of this check, a doctor
     * at one clinic holding a record URL for another could stream that
     * clinic's patient voice notes and prescription photos.
     */
    private function authorizeClinicalRead(Request $request): void
    {
        $user = $request->user();

        if (! $user?->canViewVisitNotes() || ! $user->belongsToCurrentTenant()) {
            abort(403);
        }
    }
}
