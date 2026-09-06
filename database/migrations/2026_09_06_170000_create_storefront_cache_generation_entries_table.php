<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_cache_generation_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('domain', 32);
            $table->string('backend', 32);
            $table->unsignedInteger('generation');
            $table->string('cache_key', 255);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['backend', 'cache_key'], 'scge_backend_key_unique');
            $table->index(['domain', 'backend', 'generation', 'created_at'], 'scge_prune_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_cache_generation_entries');
    }
};
