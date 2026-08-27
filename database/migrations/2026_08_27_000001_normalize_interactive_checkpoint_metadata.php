<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lesson_topics')
            ->where('type', 'interactive_checkpoint')
            ->update([
                'duration' => 0,
                'is_prerequisite' => false,
            ]);
    }

    public function down(): void
    {
        // Historical values cannot be reconstructed safely.
    }
};
