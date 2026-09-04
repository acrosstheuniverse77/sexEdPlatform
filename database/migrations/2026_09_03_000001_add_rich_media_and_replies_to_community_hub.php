<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_post_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('media_type', 16);
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['community_post_id', 'removed_at', 'display_order'],
                'community_post_media_active_order_idx'
            );
        });

        $this->backfillLegacyMedia();

        Schema::table('community_comments', function (Blueprint $table): void {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('community_post_id')
                ->constrained('community_comments')
                ->cascadeOnDelete();
            $table->index(
                ['community_post_id', 'parent_id', 'status', 'created_at'],
                'community_comments_thread_status_created_idx'
            );
        });
    }

    public function backfillLegacyMedia(): void
    {
        DB::table('community_posts')
            ->select([
                'id',
                'author_id',
                'media_path',
                'media_type',
                'media_mime_type',
                'media_original_name',
                'created_at',
                'updated_at',
            ])
            ->whereNotNull('media_path')
            ->orderBy('id')
            ->each(function (object $post): void {
                DB::table('community_post_media')->updateOrInsert([
                    'community_post_id' => $post->id,
                    'path' => $post->media_path,
                ], [
                    'uploaded_by' => $post->author_id,
                    'media_type' => $post->media_type ?: 'image',
                    'mime_type' => $post->media_mime_type,
                    'original_name' => $post->media_original_name,
                    'size_bytes' => null,
                    'display_order' => 0,
                    'removed_at' => null,
                    'removed_by' => null,
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('community_comments', function (Blueprint $table): void {
            $table->dropIndex('community_comments_thread_status_created_idx');
            $table->dropConstrainedForeignId('parent_id');
        });

        Schema::dropIfExists('community_post_media');
    }
};
