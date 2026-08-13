<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_child_invitations', function (Blueprint $table): void {
            if (! Schema::hasColumn('parent_child_invitations', 'relationship_verification_documents')) {
                $table->json('relationship_verification_documents')->nullable()->after('relationship_custom');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parent_child_invitations', function (Blueprint $table): void {
            if (Schema::hasColumn('parent_child_invitations', 'relationship_verification_documents')) {
                $table->dropColumn('relationship_verification_documents');
            }
        });
    }
};
