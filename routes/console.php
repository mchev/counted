<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Configuration des tâches planifiées pour l'agrégation analytics
Schedule::command('analytics:aggregate hourly')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('analytics:aggregate daily')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('analytics:aggregate monthly')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping()
    ->runInBackground();

// Nettoyage des anciennes données temps réel (plus de 48h)
Schedule::call(function () {
    \DB::table('analytics_realtime')
        ->where('created_at', '<', now()->subDays(2))
        ->delete();
})->dailyAt('04:00');
