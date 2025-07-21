<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table pour les agrégations horaires
        Schema::create('analytics_hourly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->dateTime('hour_start');
            $table->integer('page_views')->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->json('top_pages')->nullable(); // Top 10 pages de l'heure
            $table->json('referrers')->nullable(); // Top 10 referrers
            $table->json('devices')->nullable(); // Répartition devices
            $table->json('browsers')->nullable(); // Répartition browsers
            $table->json('os')->nullable(); // Répartition OS
            $table->json('screen_sizes')->nullable(); // Répartition écrans
            $table->timestamps();

            $table->unique(['site_id', 'hour_start']);
            $table->index(['site_id', 'hour_start']);
        });

        // Table pour les agrégations quotidiennes
        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('page_views')->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->json('top_pages')->nullable(); // Top 20 pages du jour
            $table->json('referrers')->nullable(); // Top 20 referrers
            $table->json('devices')->nullable();
            $table->json('browsers')->nullable();
            $table->json('os')->nullable();
            $table->json('screen_sizes')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'date']);
            $table->index(['site_id', 'date']);
        });

        // Table pour les agrégations mensuelles
        Schema::create('analytics_monthly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('year_month', 7); // Format: 2024-01
            $table->integer('page_views')->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->json('top_pages')->nullable(); // Top 50 pages du mois
            $table->json('referrers')->nullable(); // Top 50 referrers
            $table->json('devices')->nullable();
            $table->json('browsers')->nullable();
            $table->json('os')->nullable();
            $table->json('screen_sizes')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'year_month']);
            $table->index(['site_id', 'year_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_monthly');
        Schema::dropIfExists('analytics_daily');
        Schema::dropIfExists('analytics_hourly');
    }
};
