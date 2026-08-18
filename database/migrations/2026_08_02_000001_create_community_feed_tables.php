<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_spaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connector_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 32)->default('active');
            $table->json('settings')->nullable();
            $table->timestamp('frozen_at')->nullable();
            $table->foreignId('frozen_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('freeze_reason')->nullable();
            $table->timestamps();

            $table->unique('connector_id');
            $table->index(['status', 'frozen_at']);
        });

        Schema::create('community_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_space_id')->constrained('community_spaces')->cascadeOnDelete();
            $table->foreignId('connector_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('post_type', 32);
            $table->string('status', 32)->default('draft');
            $table->string('title', 160);
            $table->text('body');
            $table->string('resource_url', 2048)->nullable();
            $table->string('prescreen_decision', 32)->nullable();
            $table->json('prescreen_flags')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('lock_reason')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('hidden_reason')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('removed_reason')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('escalated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('moderation_case_id')->nullable()->constrained('moderation_cases')->nullOnDelete();
            $table->timestamps();

            $table->index(['connector_id', 'status', 'created_at'], 'community_posts_connector_status_created_idx');
            $table->index(['community_space_id', 'status', 'created_at'], 'community_posts_space_status_created_idx');
            $table->index(['author_id', 'created_at'], 'community_posts_author_created_idx');
        });

        Schema::create('community_post_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title', 160);
            $table->text('body');
            $table->string('resource_url', 2048)->nullable();
            $table->string('post_type', 32);
            $table->string('prescreen_decision', 32)->nullable();
            $table->json('prescreen_flags')->nullable();
            $table->timestamps();

            $table->unique(['community_post_id', 'version_number'], 'community_post_versions_post_version_unique');
        });

        Schema::create('community_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 32)->default('visible');
            $table->string('prescreen_decision', 32)->nullable();
            $table->json('prescreen_flags')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('hidden_reason')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('removed_reason')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('escalated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['community_post_id', 'status', 'created_at'], 'community_comments_post_status_created_idx');
            $table->index(['author_id', 'created_at'], 'community_comments_author_created_idx');
        });

        Schema::create('community_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction_type', 32);
            $table->timestamps();

            $table->unique(['community_post_id', 'user_id', 'reaction_type'], 'community_reactions_post_user_type_unique');
        });

        Schema::create('community_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_post_id')->nullable()->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('community_comment_id')->nullable()->constrained('community_comments')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason_code', 64);
            $table->text('details')->nullable();
            $table->string('status', 32)->default('open');
            $table->foreignId('moderation_case_id')->nullable()->constrained('moderation_cases')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'community_reports_status_created_idx');
            $table->index(['reported_user_id', 'created_at'], 'community_reports_user_created_idx');
        });

        Schema::create('community_moderation_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connector_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('community_space_id')->nullable()->constrained('community_spaces')->nullOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('action_type', 32);
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id'], 'community_moderation_target_idx');
            $table->index(['connector_id', 'created_at'], 'community_moderation_connector_created_idx');
        });

        Schema::create('community_feed_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_type', 32);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->json('settings')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id'], 'community_feed_settings_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_feed_settings');
        Schema::dropIfExists('community_moderation_actions');
        Schema::dropIfExists('community_reports');
        Schema::dropIfExists('community_reactions');
        Schema::dropIfExists('community_comments');
        Schema::dropIfExists('community_post_versions');
        Schema::dropIfExists('community_posts');
        Schema::dropIfExists('community_spaces');
    }
};
