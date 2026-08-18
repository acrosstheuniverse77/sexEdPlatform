<?php

namespace App\Models;

use App\Enums\CommunityReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityReport extends Model
{
    private const ALLOWED_DETAILS_TAGS = '<p><br><strong><b><em><i><ul><ol><li><a>';

    protected $fillable = [
        'community_post_id',
        'community_comment_id',
        'reporter_id',
        'reported_user_id',
        'reason_code',
        'details',
        'status',
        'moderation_case_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommunityReportStatus::class,
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(CommunityComment::class, 'community_comment_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function moderationCase(): BelongsTo
    {
        return $this->belongsTo(ModerationCase::class);
    }

    public function getReasonLabelAttribute(): string
    {
        return config('community_feed.report_reasons.'.$this->reason_code, str_replace('_', ' ', (string) $this->reason_code));
    }

    public function getDetailsHtmlAttribute(): string
    {
        $details = trim(strip_tags((string) $this->details, self::ALLOWED_DETAILS_TAGS));

        return $details !== '' ? $details : 'No details provided.';
    }
}
