<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('community_posts', 'topic')) {
                $table->string('topic', 100)->nullable()->after('post_type')->index();
            }
        });

        Schema::table('community_post_versions', function (Blueprint $table): void {
            if (! Schema::hasColumn('community_post_versions', 'topic')) {
                $table->string('topic', 100)->nullable()->after('post_type');
            }
        });

        Schema::create('community_post_upvotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['community_post_id', 'user_id'], 'community_post_upvotes_post_user_unique');
        });

        Schema::create('community_comment_upvotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_comment_id')->constrained('community_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['community_comment_id', 'user_id'], 'community_comment_upvotes_comment_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_comment_upvotes');
        Schema::dropIfExists('community_post_upvotes');

        Schema::table('community_post_versions', function (Blueprint $table): void {
            if (Schema::hasColumn('community_post_versions', 'topic')) {
                $table->dropColumn('topic');
            }
        });

        Schema::table('community_posts', function (Blueprint $table): void {
            if (Schema::hasColumn('community_posts', 'topic')) {
                $table->dropColumn('topic');
            }
        });
    }
};