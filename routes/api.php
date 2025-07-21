<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TrackingController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Analytics tracking endpoints
Route::post('/track', [TrackingController::class, 'track']);
Route::post('/event', [TrackingController::class, 'event']); 