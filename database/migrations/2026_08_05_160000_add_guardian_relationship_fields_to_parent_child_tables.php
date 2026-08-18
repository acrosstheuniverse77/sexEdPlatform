<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_child_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('parent_child_accounts', 'relationship_type')) {
                $table->string('relationship_type', 64)->default('parent')->after('child_user_id')->index();
            }
            if (! Schema::hasColumn('parent_child_accounts', 'relationship_custom')) {
                $table->string('relationship_custom', 120)->nullable()->after('relationship_type');
            }
            if (! Schema::hasColumn('parent_child_accounts', 'relationship_status')) {
                $table->string('relationship_status', 32)->default('active')->after('relationship_custom')->index();
            }
            if (! Schema::hasColumn('parent_child_accounts', 'relationship_verified_status')) {
                $table->string('relationship_verified_status', 32)->default('reserved')->after('relationship_status');
            }
            if (! Schema::hasColumn('parent_child_accounts', 'relationship_notes')) {
                $table->text('relationship_notes')->nullable()->after('relationship_verified_status');
            }
            if (! Schema::hasColumn('parent_child_accounts', 'is_legacy_relationship')) {
                $table->boolean('is_legacy_relationship')->default(false)->after('relationship_notes');
            }
        });

        Schema::table('parent_child_invitations', function (Blueprint $table): void {
            if (! Schema::hasColumn('parent_child_invitations', 'relationship_type')) {
                $table->string('relationship_type', 64)->default('parent')->after('child_user_id')->index();
            }
            if (! Schema::hasColumn('parent_child_invitations', 'relationship_custom')) {
                $table->string('relationship_custom', 120)->nullable()->after('relationship_type');
            }
        });

        DB::table('parent_child_accounts')
            ->update([
                'relationship_type' => 'parent',
                'relationship_status' => 'active',
                'relationship_verified_status' => 'reserved',
                'is_legacy_relationship' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('parent_child_invitations', function (Blueprint $table): void {
            foreach (['relationship_custom', 'relationship_type'] as $column) {
                if (Schema::hasColumn('parent_child_invitations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('parent_child_accounts', function (Blueprint $table): void {
            foreach ([
                'is_legacy_relationship',
                'relationship_notes',
                'relationship_verified_status',
                'relationship_status',
                'relationship_custom',
                'relationship_type',
            ] as $column) {
                if (Schema::hasColumn('parent_child_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
