<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_feedback', function (Blueprint $table): void {
            $table->longText('review_html')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('module_feedback', function (Blueprint $table): void {
            $table->longText('review_html')->nullable(false)->change();
        });
    }
};
