<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('parent_id_type')->nullable()->after('parent_verification_status');
            $table->string('parent_id_type_other')->nullable()->after('parent_id_type');
            $table->string('parent_id_document_back_path')->nullable()->after('parent_id_document_path');
            $table->timestamp('parent_verification_submitted_at')->nullable()->after('parent_verification_rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'parent_id_type',
                'parent_id_type_other',
                'parent_id_document_back_path',
                'parent_verification_submitted_at',
            ]);
        });
    }
};
