<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Site management routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\AnalyticsController::class, 'dashboard'])->name('dashboard');

    Route::resource('sites', App\Http\Controllers\SiteController::class);
    
    // Import routes
    Route::get('/import', [App\Http\Controllers\ImportController::class, 'index'])->name('import.index');
    Route::post('/import/umami', [App\Http\Controllers\ImportController::class, 'importUmami'])->name('import.umami');
    Route::get('/import/ftp-files', [App\Http\Controllers\ImportController::class, 'getFtpFiles'])->name('import.ftp-files');
    Route::get('/import/history', [App\Http\Controllers\ImportController::class, 'history'])->name('import.history');

    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
