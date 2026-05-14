<?php

use App\Domain\Auth\Http\Controllers\AuthController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\PresenceController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\WhatsappSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public webhooks (no auth — secured via shared secret / network)
    Route::post('webhooks/evolution', [WebhookController::class, 'evolution'])
        ->middleware('throttle:webhooks');
    Route::post('webhooks/n8n', [WebhookController::class, 'n8n'])
        ->middleware('throttle:webhooks');

    // Auth
    Route::post('auth/login',   [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // Authenticated
    Route::middleware(['jwt.auth','tenant'])->group(function () {
        Route::get('auth/me',     [AuthController::class, 'me']);
        Route::post('auth/logout',[AuthController::class, 'logout']);
        Route::put('auth/profile',[AuthController::class, 'updateProfile']);

        Route::get('presence/ping',     [PresenceController::class, 'ping']);
        Route::post('presence/typing',  [PresenceController::class, 'typing']);

        Route::get('sectors', function (\Illuminate\Http\Request $request) {
            return ['data' => \App\Domain\Sector\Models\Sector::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->whereNull('parent_id')
                ->where('active', true)
                ->orderBy('order')
                ->get(['id','name','slug','color','menu_key'])];
        });

        Route::get('tickets',                    [TicketController::class, 'index']);
        Route::get('tickets/{ticket}',           [TicketController::class, 'show']);
        Route::get('tickets/{ticket}/messages',  [TicketController::class, 'messages']);
        Route::post('tickets/{ticket}/messages', [TicketController::class, 'send']);
        Route::post('tickets/{ticket}/transfer/sector', [TicketController::class, 'transferToSector']);
        Route::post('tickets/{ticket}/transfer/user',   [TicketController::class, 'transferToUser']);
        Route::post('tickets/{ticket}/close',           [TicketController::class, 'close']);

        Route::get('analytics/dashboard',        [AnalyticsController::class, 'dashboard']);

        Route::middleware(['role:admin'])->group(function () {
            Route::apiResource('whatsapp/sessions', WhatsappSessionController::class)
                ->only(['index','store','destroy']);
            Route::post('whatsapp/sessions/{session}/reconnect', [WhatsappSessionController::class, 'reconnect']);
            Route::get('whatsapp/sessions/{session}/qr',         [WhatsappSessionController::class, 'qr']);

            Route::apiResource('users', UserController::class)->only(['index','store','update','destroy']);

            Route::get('tenant', [TenantController::class, 'show']);
            Route::put('tenant', [TenantController::class, 'update']);
        });
    });
});
