<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('session_id');
            $table->text('url');
            $table->text('referrer')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('screen_resolution')->nullable();
            $table->integer('time_on_page')->nullable();
            $table->boolean('is_bounce')->default(false);
            $table->timestamps();

            // Simple indexes for non-TEXT columns
            $table->index(['site_id', 'created_at']); // Main aggregation index
            $table->index(['site_id', 'session_id', 'created_at']); // Unique visitors
            $table->index(['created_at']); // Global time-based queries
            $table->index(['session_id']); // Session-based queries
        });

        // Add indexes for TEXT columns with key lengths using raw SQL
        DB::statement('CREATE INDEX page_views_site_url_created_idx ON page_views (site_id, url(255), created_at)');
        DB::statement('CREATE INDEX page_views_site_referrer_created_idx ON page_views (site_id, referrer(255), created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
