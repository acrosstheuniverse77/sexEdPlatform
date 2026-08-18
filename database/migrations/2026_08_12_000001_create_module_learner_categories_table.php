<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_learner_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('category', 16);
            $table->timestamps();

            $table->unique(['module_id', 'category'], 'module_learner_categories_unique');
            $table->index(['category', 'module_id'], 'module_learner_categories_category_idx');
        });

        $now = now();
        DB::table('modules')
            ->select(['id', 'min_age', 'max_age'])
            ->orderBy('id')
            ->chunkById(200, function ($modules) use ($now): void {
                foreach ($modules as $module) {
                    $minAge = min((int) $module->min_age, (int) $module->max_age);
                    $maxAge = max((int) $module->min_age, (int) $module->max_age);

                    $categories = [];
                    foreach ([
                        'kids' => [5, 12],
                        'teens' => [13, 17],
                        'adults' => [18, 100],
                    ] as $category => [$categoryMin, $categoryMax]) {
                        if ($minAge <= $categoryMax && $maxAge >= $categoryMin) {
                            $categories[] = $category;
                        }
                    }

                    if ($categories === []) {
                        $categories[] = 'teens';
                    }

                    foreach (array_unique($categories) as $category) {
                        DB::table('module_learner_categories')->insertOrIgnore([
                            'module_id' => $module->id,
                            'category' => $category,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_learner_categories');
    }
};
