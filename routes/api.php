<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhook\MaxChatWebhookController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/webhooks/contact', [MaxChatWebhookController::class, 'contact']);
Route::post('/webhooks/chat', [MaxChatWebhookController::class, 'chat']);