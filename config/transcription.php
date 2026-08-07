<?php

return [
    'driver' => env('TRANSCRIPTION_DRIVER', 'log'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'whisper_model' => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
        'structure_model' => env('OPENAI_STRUCTURE_MODEL', 'gpt-4o-mini'),
    ],
];
