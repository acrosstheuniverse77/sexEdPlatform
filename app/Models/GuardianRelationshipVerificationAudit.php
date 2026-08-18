<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardianRelationshipVerificationAudit extends Model
{
    protected $fillable = [
        'parent_child_account_id',
        'actor_user_id',
        'action',
        'previous_status',
        'new_status',
        'reason_code',
        'notes',
    ];

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(ParentChildAccount::class, 'parent_child_account_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
