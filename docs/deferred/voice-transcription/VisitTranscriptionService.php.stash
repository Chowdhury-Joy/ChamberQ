<?php

namespace App\Services\Transcription;

use App\Models\User;
use App\Services\MedicineService;
use App\Services\VisitMediaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VisitTranscriptionService
{
    public function __construct(
        private readonly VisitMediaService $visitMediaService,
        private readonly MedicineService $medicineService,
    ) {}

    /**
     * @return array{
     *     transcript: ?string,
     *     diagnosis_free_text: ?string,
     *     advice: ?string,
     *     tests_advised: ?string,
     *     reports_seen: ?string,
     *     prescription_items: list<array{medicine_name: string, generic_name: ?string, dose: ?string, frequency: ?string, duration: ?string, uncertain: bool}>,
     *     machine_filled: list<string>
     * }
     */
    public function transcribeFromPath(string $path, User $doctor): array
    {
        $absolute = $this->visitMediaService->absolutePath($path);

        if (! $absolute) {
            abort(404, __('Voice recording not found.'));
        }

        $driver = config('transcription.driver', 'log');

        if ($driver === 'array') {
            return $this->arrayDriverDraft();
        }

        if ($driver === 'log') {
            return $this->emptyDraft();
        }

        if ($driver === 'openai') {
            return $this->openAiDraft($absolute, $doctor);
        }

        return $this->emptyDraft();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDraft(): array
    {
        return [
            'transcript' => null,
            'diagnosis_free_text' => null,
            'advice' => null,
            'tests_advised' => null,
            'reports_seen' => null,
            'prescription_items' => [],
            'machine_filled' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayDriverDraft(): array
    {
        return [
            'transcript' => 'Patient has gastritis. Give NAPA one plus one plus one for five days. Come back in two weeks.',
            'diagnosis_free_text' => 'Gastritis',
            'advice' => 'Avoid spicy food',
            'tests_advised' => null,
            'reports_seen' => null,
            'prescription_items' => [
                [
                    'medicine_name' => 'NAPA',
                    'generic_name' => 'Paracetamol',
                    'dose' => '500 mg',
                    'frequency' => '1+1+1',
                    'duration' => '5 days',
                    'uncertain' => false,
                ],
            ],
            'machine_filled' => ['diagnosis_free_text', 'advice', 'prescription_items'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function openAiDraft(string $absolutePath, User $doctor): array
    {
        $apiKey = config('transcription.openai.api_key');

        if (blank($apiKey)) {
            return $this->emptyDraft();
        }

        $whisperResponse = Http::withToken($apiKey)
            ->attach('file', fopen($absolutePath, 'r'), basename($absolutePath))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => config('transcription.openai.whisper_model'),
                'response_format' => 'text',
            ]);

        if (! $whisperResponse->successful()) {
            abort(502, __('Transcription service unavailable.'));
        }

        $transcript = trim($whisperResponse->body());

        if ($transcript === '') {
            return $this->emptyDraft();
        }

        $vocabulary = $this->medicineService->vocabularyHints($doctor);
        $structurePrompt = 'Extract visit notes from this doctor dictation. Return JSON only with keys: diagnosis (string), advice (string), tests_advised (string), reports_seen (string), medicines (array of {brand_name, generic_name, dose, frequency, duration}). Use only medicines you are confident about.';

        $structureResponse = Http::withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('transcription.openai.structure_model'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $structurePrompt.' Known brands: '.implode(', ', $vocabulary)],
                    ['role' => 'user', 'content' => $transcript],
                ],
            ]);

        if (! $structureResponse->successful()) {
            return [
                'transcript' => $transcript,
                'diagnosis_free_text' => null,
                'advice' => null,
                'tests_advised' => null,
                'reports_seen' => null,
                'prescription_items' => [],
                'machine_filled' => ['voice_transcript'],
            ];
        }

        $content = $structureResponse->json('choices.0.message.content');
        $parsed = json_decode((string) $content, true);

        if (! is_array($parsed)) {
            return [
                'transcript' => $transcript,
                'diagnosis_free_text' => null,
                'advice' => null,
                'tests_advised' => null,
                'reports_seen' => null,
                'prescription_items' => [],
                'machine_filled' => ['voice_transcript'],
            ];
        }

        $items = collect($parsed['medicines'] ?? [])
            ->map(function (array $row): array {
                $brand = $this->medicineService->normalizeMedicineName((string) ($row['brand_name'] ?? ''));

                if ($brand === '') {
                    return null;
                }

                $match = $this->medicineService->search($brand, auth()->user())->first();

                return [
                    'medicine_name' => $brand,
                    'generic_name' => filled($row['generic_name'] ?? null) ? trim((string) $row['generic_name']) : ($match['generic_name'] ?? null),
                    'dose' => filled($row['dose'] ?? null) ? trim((string) $row['dose']) : ($match['dose'] ?? null),
                    'frequency' => filled($row['frequency'] ?? null) ? trim((string) $row['frequency']) : ($match['frequency'] ?? null),
                    'duration' => filled($row['duration'] ?? null) ? trim((string) $row['duration']) : ($match['duration'] ?? null),
                    'uncertain' => $match === null,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $machineFilled = ['voice_transcript'];

        if (filled($parsed['diagnosis'] ?? null)) {
            $machineFilled[] = 'diagnosis_free_text';
        }

        if (filled($parsed['advice'] ?? null)) {
            $machineFilled[] = 'advice';
        }

        if (filled($parsed['tests_advised'] ?? null)) {
            $machineFilled[] = 'tests_advised';
        }

        if (filled($parsed['reports_seen'] ?? null)) {
            $machineFilled[] = 'reports_seen';
        }

        if ($items !== []) {
            $machineFilled[] = 'prescription_items';
        }

        return [
            'transcript' => $transcript,
            'diagnosis_free_text' => filled($parsed['diagnosis'] ?? null) ? trim((string) $parsed['diagnosis']) : null,
            'advice' => filled($parsed['advice'] ?? null) ? trim((string) $parsed['advice']) : null,
            'tests_advised' => filled($parsed['tests_advised'] ?? null) ? trim((string) $parsed['tests_advised']) : null,
            'reports_seen' => filled($parsed['reports_seen'] ?? null) ? trim((string) $parsed['reports_seen']) : null,
            'prescription_items' => $items,
            'machine_filled' => $machineFilled,
        ];
    }
}
