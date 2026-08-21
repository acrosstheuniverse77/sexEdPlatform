<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_topics', function (Blueprint $table): void {
            $table->json('content_blocks')->nullable()->after('text_content');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE lesson_topics MODIFY COLUMN type ENUM('video', 'text', 'worksheet', 'quiz', 'interactive', 'interactive_checkpoint') DEFAULT 'text'");
        }

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('quiz_questions', function (Blueprint $table): void {
                $table->dropForeign(['quiz_id']);
            });
        }

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->foreignId('quiz_id')->nullable()->change();
            $table->foreignId('checkpoint_topic_id')
                ->nullable()
                ->after('quiz_id')
                ->constrained('lesson_topics')
                ->cascadeOnDelete();
            $table->string('checkpoint_block_uuid')->nullable()->after('checkpoint_topic_id');
            $table->text('explanation')->nullable()->after('image_path');
            $table->index(['checkpoint_topic_id', 'checkpoint_block_uuid'], 'quiz_questions_checkpoint_owner_index');
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('quiz_questions', function (Blueprint $table): void {
                $table->foreign('quiz_id')->references('id')->on('quizzes')->cascadeOnDelete();
            });
        }

        Schema::create('interactive_checkpoint_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_topic_id')->constrained('lesson_topics')->cascadeOnDelete();
            $table->foreignId('quiz_question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->string('checkpoint_block_uuid')->nullable();
            $table->string('status', 32)->default('not_attempted');
            $table->json('latest_answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'quiz_question_id'], 'checkpoint_progress_user_question_unique');
            $table->index(['user_id', 'lesson_topic_id'], 'checkpoint_progress_user_topic_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactive_checkpoint_progress');

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('quiz_questions', function (Blueprint $table): void {
                $table->dropForeign(['quiz_id']);
            });
        }

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->dropIndex('quiz_questions_checkpoint_owner_index');
            $table->dropForeign(['checkpoint_topic_id']);
            $table->dropColumn(['checkpoint_topic_id', 'checkpoint_block_uuid', 'explanation']);
            $table->foreignId('quiz_id')->nullable(false)->change();
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('quiz_questions', function (Blueprint $table): void {
                $table->foreign('quiz_id')->references('id')->on('quizzes')->cascadeOnDelete();
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE lesson_topics MODIFY COLUMN type ENUM('video', 'text', 'worksheet', 'quiz', 'interactive') DEFAULT 'text'");
        }

        Schema::table('lesson_topics', function (Blueprint $table): void {
            $table->dropColumn('content_blocks');
        });
    }
};
