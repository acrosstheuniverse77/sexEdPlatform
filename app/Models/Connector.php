<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Connector extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'organization_email',
        'contact_number',
        'description',
        'website_url',
        'verification_notes',
        'city_code',
        'barangay_code',
        'address_line',
        'status',
        'created_by',
        'primary_representative_user_id',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function primaryRepresentative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_representative_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(ConnectorRole::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ConnectorMembership::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ConnectorInvitation::class);
    }

    public function membershipRequests(): HasMany
    {
        return $this->hasMany(ConnectorMembershipRequest::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ConnectorReview::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function seminars(): HasMany
    {
        return $this->hasMany(Seminar::class);
    }

    public function communitySpaces(): HasMany
    {
        return $this->hasMany(CommunitySpace::class);
    }

    public function communityPosts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now())
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('ends_at')
                            ->where(function ($legacy) {
                                $legacy->whereNull('end_date')
                                    ->orWhere('end_date', '>', now());
                            });
                    });
            })
            ->latestOfMany();
    }

    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }
}
