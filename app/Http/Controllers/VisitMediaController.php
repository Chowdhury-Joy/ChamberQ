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

    public function voice(Request $request, VisitRecord $visitRecord): BinaryFileResponse
    {
        $this->authorizeClinicalRead($request);

        $absolute = app(VisitMediaService::class)->absolutePath($visitRecord->voice_path);

        if (! $absolute) {
            abort(404);
        }

        return response()->file($absolute);
    }

    public function photo(Request $request, VisitRecord $visitRecord): BinaryFileResponse
    {
        $this->authorizeClinicalRead($request);

        $absolute = app(VisitMediaService::class)->absolutePath($visitRecord->photo_path);

        if (! $absolute) {
            abort(404);
        }

        return response()->file($absolute);
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
