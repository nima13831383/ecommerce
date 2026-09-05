<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_otp_challenges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('mobile', 15)->index();
            $table->string('purpose', 20);
            $table->string('code_hash');
            $table->dateTime('expires_at')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts');
            $table->dateTime('sent_at');
            $table->dateTime('consumed_at')->nullable()->index();
            $table->dateTime('invalidated_at')->nullable()->index();
            $table->timestamps();

            $table->index(['mobile', 'purpose', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_otp_challenges');
    }
};
