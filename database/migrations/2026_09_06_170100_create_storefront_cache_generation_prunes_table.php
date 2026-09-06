<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_cache_generation_prunes', function (Blueprint $table): void {
            $table->id();
            $table->string('backend', 32)->index();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 32)->index();
            $table->unsignedInteger('deleted_entries')->default(0);
            $table->unsignedInteger('deleted_runs')->default(0);
            $table->string('error_summary', 1000)->nullable();
            $table->timestamps();
        });

        Schema::table('cache_rebuild_runs', function (Blueprint $table): void {
            $table->index(['status', 'finished_at'], 'cache_rebuild_retention_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cache_rebuild_runs', function (Blueprint $table): void {
            $table->dropIndex('cache_rebuild_retention_idx');
        });

        Schema::dropIfExists('storefront_cache_generation_prunes');
    }
};
