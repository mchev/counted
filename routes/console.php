<?php

use Illuminate\Support\Facades\Schedule;

// Aggregation hourly
Schedule::command('analytics:aggregate hourly')
    ->hourly()
    ->withoutOverlapping();

// Aggregation daily (based on hourly aggregation)
Schedule::command('analytics:aggregate daily')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Aggregation monthly (based on daily aggregation)
Schedule::command('analytics:aggregate monthly')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping();
