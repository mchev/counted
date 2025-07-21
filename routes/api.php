<?php

use App\Http\Controllers\Api\TrackingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Analytics tracking endpoints
Route::post('/track', [TrackingController::class, 'track']);
Route::post('/event', [TrackingController::class, 'event']);
