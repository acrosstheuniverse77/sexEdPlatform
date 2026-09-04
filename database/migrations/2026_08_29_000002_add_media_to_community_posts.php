<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table): void {
            $table->string('media_path')->nullable()->after('body');
            $table->string('media_type', 16)->nullable()->after('media_path');
            $table->string('media_mime_type', 100)->nullable()->after('media_type');
            $table->string('media_original_name')->nullable()->after('media_mime_type');
        });

        Schema::table('community_post_versions', function (Blueprint $table): void {
            $table->string('media_path')->nullable()->after('body');
            $table->string('media_type', 16)->nullable()->after('media_path');
            $table->string('media_mime_type', 100)->nullable()->after('media_type');
            $table->string('media_original_name')->nullable()->after('media_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('community_post_versions', function (Blueprint $table): void {
            $table->dropColumn(['media_path', 'media_type', 'media_mime_type', 'media_original_name']);
        });

        Schema::table('community_posts', function (Blueprint $table): void {
            $table->dropColumn(['media_path', 'media_type', 'media_mime_type', 'media_original_name']);
        });
    }
};
