<?php

use App\Domain\Auth\Http\Controllers\AuthController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\PresenceController;
use App\Http\Controllers\Api\V1\SectorController;
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

        Route::get('sectors', [SectorController::class, 'index']);

        Route::get('tickets',                    [TicketController::class, 'index']);
        Route::get('tickets/{ticket}',           [TicketController::class, 'show']);
        Route::get('tickets/{ticket}/messages',  [TicketController::class, 'messages']);
        Route::post('tickets/{ticket}/messages', [TicketController::class, 'send']);
        Route::post('tickets/{ticket}/transfer/sector', [TicketController::class, 'transferToSector']);
        Route::post('tickets/{ticket}/transfer/user',   [TicketController::class, 'transferToUser']);
        Route::post('tickets/{ticket}/close',           [TicketController::class, 'close']);

        Route::get('analytics/dashboard',        [AnalyticsController::class, 'dashboard']);

        Route::get('users', [UserController::class, 'index']);

        // WhatsApp sessions — read available to all authenticated
        Route::get('whatsapp/sessions',              [WhatsappSessionController::class, 'index']);
        Route::get('whatsapp/sessions/{session}/qr', [WhatsappSessionController::class, 'qr']);

        // Create + reconnect: attendants and above
        Route::middleware(['role:admin|supervisor|attendant'])->group(function () {
            Route::post('whatsapp/sessions',                     [WhatsappSessionController::class, 'store']);
            Route::post('whatsapp/sessions/{session}/reconnect', [WhatsappSessionController::class, 'reconnect']);
        });

        Route::middleware(['role:admin'])->group(function () {
            Route::delete('whatsapp/sessions/{session}', [WhatsappSessionController::class, 'destroy']);

            Route::apiResource('users', UserController::class)->only(['store','update','destroy']);

            Route::post('sectors',                          [SectorController::class, 'store']);
            Route::put('sectors/{sector}',                [SectorController::class, 'update']);
            Route::delete('sectors/{sector}',             [SectorController::class, 'destroy']);
            Route::put('sectors/{sector}/ai-settings',    [SectorController::class, 'aiSettings']);
            Route::post('sectors/{sector}/n8n-action',    [SectorController::class, 'n8nAction']);

            Route::get('tenant', [TenantController::class, 'show']);
            Route::put('tenant', [TenantController::class, 'update']);
            Route::put('tenant/gateway', [TenantController::class, 'updateGateway']);
            Route::post('tenant/logo', [TenantController::class, 'uploadLogo']);
        });
    });
});
