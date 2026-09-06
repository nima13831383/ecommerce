<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cache_rebuild_runs')) {
            Schema::create('cache_rebuild_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('domain', 32)->index();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('requested_at')->index();
                $table->string('status', 32)->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable()->index();
                $table->string('error_summary', 1000)->nullable();
                $table->string('target_backend', 32);
                $table->unsignedInteger('target_generation');
                $table->timestamps();
            });
        }

        Schema::table('cache_rebuild_runs', function (Blueprint $table): void {
            $table->index(['domain', 'target_backend', 'status', 'finished_at'], 'cache_rebuild_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_rebuild_runs');
    }
};
