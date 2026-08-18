<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('guardian_onboarding_status', 32)->nullable()->after('parent_verification_approved_at');
            $table->timestamp('guardian_onboarding_started_at')->nullable()->after('guardian_onboarding_status');
            $table->timestamp('guardian_onboarding_completed_at')->nullable()->after('guardian_onboarding_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'guardian_onboarding_status',
                'guardian_onboarding_started_at',
                'guardian_onboarding_completed_at',
            ]);
        });
    }
};
