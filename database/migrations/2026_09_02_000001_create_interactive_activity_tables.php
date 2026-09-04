<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactive_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_topic_id')->constrained('lesson_topics')->cascadeOnDelete();
            $table->string('placement', 32);
            $table->uuid('block_uuid')->nullable()->unique();
            $table->string('activity_type', 32);
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->text('explanation')->nullable();
            $table->json('configuration');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['lesson_topic_id', 'placement']);
        });

        Schema::create('interactive_activity_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interactive_activity_id')->constrained('interactive_activities')->cascadeOnDelete();
            $table->unsignedInteger('activity_revision');
            $table->string('status', 32)->default('in_progress');
            $table->json('working_state')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['user_id', 'interactive_activity_id', 'activity_revision'],
                'activity_progress_user_activity_revision_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactive_activity_progress');
        Schema::dropIfExists('interactive_activities');
    }
};
