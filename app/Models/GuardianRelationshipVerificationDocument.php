<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardianRelationshipVerificationDocument extends Model
{
    protected $fillable = [
        'parent_child_account_id',
        'uploaded_by_user_id',
        'document_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(ParentChildAccount::class, 'parent_child_account_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
