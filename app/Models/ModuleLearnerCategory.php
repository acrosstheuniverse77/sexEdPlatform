<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleLearnerCategory extends Model
{
    protected $fillable = [
        'module_id',
        'category',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
