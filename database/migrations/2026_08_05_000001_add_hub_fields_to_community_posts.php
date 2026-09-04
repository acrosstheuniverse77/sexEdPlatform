<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('community_posts', 'seminar_id')) {
                $table->foreignId('seminar_id')->nullable()->after('post_type')->constrained('seminars')->nullOnDelete();
            }

            if (! Schema::hasColumn('community_posts', 'featured_at')) {
                $table->timestamp('featured_at')->nullable()->after('published_by');
            }

            if (! Schema::hasColumn('community_posts', 'featured_by')) {
                $table->foreignId('featured_by')->nullable()->after('featured_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('community_posts', 'official_answer_comment_id')) {
                $table->foreignId('official_answer_comment_id')->nullable()->after('moderation_case_id')->constrained('community_comments')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table): void {
            if (Schema::hasColumn('community_posts', 'official_answer_comment_id')) {
                $table->dropConstrainedForeignId('official_answer_comment_id');
            }

            if (Schema::hasColumn('community_posts', 'featured_by')) {
                $table->dropConstrainedForeignId('featured_by');
            }

            if (Schema::hasColumn('community_posts', 'featured_at')) {
                $table->dropColumn('featured_at');
            }

            if (Schema::hasColumn('community_posts', 'seminar_id')) {
                $table->dropConstrainedForeignId('seminar_id');
            }
        });
    }
};
