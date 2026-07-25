<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/payment', [\App\Http\Controllers\WebhookController::class, 'payment']);
