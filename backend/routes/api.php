<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Organizer\EventController;
use App\Http\Controllers\Api\V1\Participant\EventController as ParticipantEventController;
use App\Http\Controllers\Api\V1\Participant\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::prefix('organizer')->middleware('can:access-organizer-api')->group(function () {
            Route::get('/events', [EventController::class, 'index']);
            Route::post('/events', [EventController::class, 'store']);
            Route::get('/events/{event}/registrations', [EventController::class, 'registrations'])
                ->whereNumber('event');
            Route::get('/events/{event}', [EventController::class, 'show'])->whereNumber('event');
            Route::put('/events/{event}', [EventController::class, 'update'])->whereNumber('event');
            Route::patch('/events/{event}', [EventController::class, 'update'])->whereNumber('event');
            Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->whereNumber('event');
        });

        Route::prefix('participant')->middleware('can:access-participant-api')->group(function () {
            Route::get('/events', [ParticipantEventController::class, 'index']);
            Route::get('/events/{event}', [ParticipantEventController::class, 'show'])->whereNumber('event');
            Route::post('/events/{event}/registrations', [RegistrationController::class, 'store'])->whereNumber('event');
            Route::delete('/events/{event}/registration', [RegistrationController::class, 'destroy'])->whereNumber('event');
            Route::get('/my-events', [RegistrationController::class, 'index']);
        });
    });
});
