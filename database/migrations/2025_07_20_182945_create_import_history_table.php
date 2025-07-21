<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('site_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('site_name');
            $table->enum('status', ['processing', 'processing_chunks', 'completed', 'failed'])->default('processing');
            $table->string('type'); // 'umami', 'google_analytics', etc.
            $table->string('file_name');
            $table->text('summary')->nullable();
            $table->json('details')->nullable(); // Statistiques détaillées de l'import
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_history');
    }
};
