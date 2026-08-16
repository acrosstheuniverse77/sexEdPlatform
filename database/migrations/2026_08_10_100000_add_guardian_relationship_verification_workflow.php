<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_child_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('parent_child_accounts', 'relationship_verification_submitted_at')) {
                $table->timestamp('relationship_verification_submitted_at')->nullable()->after('relationship_verified_status');
            }
            if (! Schema::hasColumn('parent_child_accounts', 'relationship_verification_reviewed_by')) {
                $table->unsignedBigInteger('relationship_verification_reviewed_by')->nullable()->after('relationship_verification_submitted_at');
                $table->foreign('relationship_verification_reviewed_by', 'pc_rel_ver_reviewed_by_fk')->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('parent_child_accounts', 'relationship_verification_reviewed_at')) {
                $table->timestamp('relationship_verification_reviewed_at')->nullable()->after('relationship_verification_reviewed_by');
            }
            if (! Schema::hasColumn('parent_child_accounts', 'relationship_verification_rejection_reason')) {
                $table->string('relationship_verification_rejection_reason', 64)->nullable()->after('relationship_verification_reviewed_at');
            }
            if (! Schema::hasColumn('parent_child_accounts', 'relationship_verification_rejection_note')) {
                $table->text('relationship_verification_rejection_note')->nullable()->after('relationship_verification_rejection_reason');
            }
            if (! Schema::hasColumn('parent_child_accounts', 'relationship_verification_revoked_at')) {
                $table->timestamp('relationship_verification_revoked_at')->nullable()->after('relationship_verification_rejection_note');
            }
        });

        Schema::create('guardian_relationship_verification_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_child_account_id');
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->string('document_type', 64);
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->foreign('parent_child_account_id', 'grvd_relationship_fk')->references('id')->on('parent_child_accounts')->cascadeOnDelete();
            $table->foreign('uploaded_by_user_id', 'grvd_uploaded_by_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['parent_child_account_id', 'document_type'], 'grvd_relationship_doc_type_idx');
        });

        Schema::create('guardian_relationship_verification_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_child_account_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 64);
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->string('reason_code', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('parent_child_account_id', 'grva_relationship_fk')->references('id')->on('parent_child_accounts')->cascadeOnDelete();
            $table->foreign('actor_user_id', 'grva_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['parent_child_account_id', 'action'], 'grva_relationship_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_relationship_verification_audits');
        Schema::dropIfExists('guardian_relationship_verification_documents');

        Schema::table('parent_child_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('parent_child_accounts', 'relationship_verification_reviewed_by')) {
                $table->dropForeign('pc_rel_ver_reviewed_by_fk');
            }

            foreach ([
                'relationship_verification_revoked_at',
                'relationship_verification_rejection_note',
                'relationship_verification_rejection_reason',
                'relationship_verification_reviewed_at',
                'relationship_verification_reviewed_by',
                'relationship_verification_submitted_at',
            ] as $column) {
                if (Schema::hasColumn('parent_child_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
