<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->longText('review_html')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['instructor_id', 'learner_id'], 'instructor_feedback_unique_reviewer');
            $table->index(['instructor_id', 'rating'], 'instructor_feedback_instructor_rating_idx');
            $table->index(['learner_id', 'created_at'], 'instructor_feedback_learner_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_feedback');
    }
};
