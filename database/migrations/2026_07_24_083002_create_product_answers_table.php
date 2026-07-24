<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('product_questions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->boolean('is_staff')->default(false);
            $table->text('body');
            $table->enum('status', ['pending', 'approved', 'spam'])->default('pending');
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();

            $table->index(['question_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_answers');
    }
};
