<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstructorApplication extends Model
{
    use SoftDeletes;

    public const EDUCATIONAL_BACKGROUND_LABELS = [
        'high_school' => 'High School Graduate',
        'college_undergrad' => 'College Undergraduate',
        'college_graduate' => 'College Graduate',
        'masters' => "Master's Degree",
        'doctorate' => 'Doctorate Degree',
        'other' => 'Other',
    ];

    protected $fillable = [
        'user_id',
        'status',
        'educational_background',
        'government_id_path',
        'clearance_path',
        'cv_resume_path',
        'bio',
        'teaching_credential_path',
        'sexed_certificate_path',
        'professional_license_path',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'rejection_reason_code',
        'rejection_reason_note',
        'review_message',
        'application_metadata',
    ];

    protected function casts(): array
    {
        return [
            'application_metadata' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(InstructorApplicationReview::class, 'instructor_application_id');
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(InstructorApplicationReview::class, 'instructor_application_id')
            ->latestOfMany('reviewed_at');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function resolveDocumentUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        $normalized = ltrim($path, '/');

        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = substr($normalized, 8);
        }

        if (!str_contains($normalized, '/')) {
            $normalized = 'instructor-applications/' . $normalized;
        }

        return Storage::url($normalized);
    }
}
