# Community Feed V1 Baseline Implementation Plan

> Current status: superseded as the active implementation plan by
> `docs/superpowers/plans/2026-08-05-community-hub-ui-engagement-implementation.md`.
> Keep this document only for baseline backend safety contracts. New work should
> implement the current Connector Community Hub/feed experience: Community Hub
> copy, Featured/Announcements/Events/Resources/Q&A/Discussions tabs, event and
> discussion prompt post types, featured posts, seminar-aware event posts,
> connector/admin moderation UI, and the same adult-facing safety constraints.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Preserve the original connector-scoped, adult-facing Community Feed V1
safety implementation plan as a backend baseline. For current product work, use
the Community Hub plan instead.

**Architecture:** Add or maintain a focused Community domain that reuses existing
Laravel MVC, Eloquent, connector-local permissions, Spatie global permissions,
notification, suspension middleware, and `ModerationCaseIntakeService` patterns.
Keep safety rules server-side through dedicated services and policies;
controllers only validate requests, authorize access, and call services. The
current Hub plan layers product taxonomy and UI polish on top of these contracts.

**Tech Stack:** Laravel 12, PHP 8.2, Eloquent, Blade, Alpine.js, Tailwind CSS, Spatie Laravel Permission, Laravel notifications, PHPUnit/Laravel feature and unit tests.

## Global Constraints

- The current Community Hub/feed is connector-scoped and adult-facing.
- Approved connectors can publish announcements, seminar/event posts, educational resources, moderated Q&A, and connector-authored discussion prompts.
- Minors cannot create posts, comment, react, or receive replies in V1.
- Guardian notifications are not required in V1 and must not be sent by feed workflows.
- Connector moderators handle first-level moderation.
- Platform admins keep final authority, escalation control, and emergency freeze powers.
- No private messaging, direct replies, nested replies, DM-like feature, viral sharing, follower metrics, or global feed promotion.
- Reports must feed the existing centralized moderation system through a community source adapter.
- Hidden, removed, and escalated content remains available to authorized moderators and admins for audit.
- Suspended users and suspended connectors cannot create, edit, comment, react, or moderate feed content.
- Avoid touching unrelated dirty files such as existing `public/build` artifacts and `package-lock.json` unless the implementation task explicitly changes frontend assets.

## Current Plan Pointer

Use the Community Hub implementation plan for active work:

- `docs/superpowers/plans/2026-08-05-community-hub-ui-engagement-implementation.md`

This older plan remains useful for schema, service, moderation adapter, audit,
freeze, suspension, and minor-exclusion details. If the two documents conflict,
the current Hub plan wins for product taxonomy, connector/admin UI, visible copy,
tabs, featured posts, event/seminar behavior, and discussion prompts.

---

## File Structure

Create:

- `config/community_feed.php`: post types, statuses, reactions, prescreen terms, link allowlist, rate-limit values, and default freeze behavior.
- `app/Enums/CommunityPostType.php`: `announcement`, `resource`, `moderated_question`.
- `app/Enums/CommunityPostStatus.php`: `draft`, `pending_review`, `published`, `hidden`, `locked`, `removed`, `escalated`, `archived`.
- `app/Enums/CommunityCommentStatus.php`: `visible`, `pending_review`, `hidden`, `removed`, `escalated`.
- `app/Enums/CommunityReactionType.php`: `learned`, `helpful`, `question`, `support`.
- `app/Enums/CommunityReportStatus.php`: `open`, `under_review`, `resolved`, `dismissed`.
- `app/Enums/CommunityModerationActionType.php`: `approve`, `reject`, `hide`, `lock`, `unlock`, `restore`, `remove`, `escalate`, `freeze`, `unfreeze`.
- `app/Enums/CommunityPreScreenDecision.php`: `allow`, `pending_review`, `block_with_feedback`, `auto_hide_and_escalate`.
- `app/Data/Community/CommunityPreScreenResult.php`: immutable result object returned by prescreening.
- `app/Models/CommunitySpace.php`: connector-owned feed space.
- `app/Models/CommunityPost.php`: feed post model.
- `app/Models/CommunityPostVersion.php`: immutable post version snapshots.
- `app/Models/CommunityComment.php`: flat post comments.
- `app/Models/CommunityReaction.php`: low-risk post reactions.
- `app/Models/CommunityReport.php`: community report source record.
- `app/Models/CommunityModerationAction.php`: audit log for local moderation actions.
- `app/Models/CommunityFeedSetting.php`: platform and optional connector-space freeze settings.
- `app/Policies/CommunityPostPolicy.php`: object-level post authorization.
- `app/Policies/CommunityCommentPolicy.php`: object-level comment authorization.
- `app/Services/Community/CommunityAccessService.php`: adult/minor, connector, suspension, permission, and freeze gates.
- `app/Services/Community/CommunityFeedSettingsService.php`: global and space-level freeze reads/writes.
- `app/Services/Community/CommunityContentPreScreeningService.php`: heuristic safety pre-screening.
- `app/Services/Community/CommunitySpaceService.php`: creates and resolves connector spaces.
- `app/Services/Community/CommunityPostService.php`: create, update, publish-status, and versioning workflow.
- `app/Services/Community/CommunityInteractionService.php`: flat comments and reactions.
- `app/Services/Community/CommunityModerationService.php`: approve, reject, hide, lock, restore, remove, escalate, and audit actions.
- `app/Services/Community/CommunityReportService.php`: report create/update and adapter dispatch.
- `app/Services/Moderation/SourceAdapters/CommunityFeedModerationAdapter.php`: central moderation case adapter.
- `app/Http/Requests/Community/StoreCommunityPostRequest.php`
- `app/Http/Requests/Community/UpdateCommunityPostRequest.php`
- `app/Http/Requests/Community/StoreCommunityCommentRequest.php`
- `app/Http/Requests/Community/StoreCommunityReportRequest.php`
- `app/Http/Requests/Community/ModerateCommunityContentRequest.php`
- `app/Http/Requests/Admin/UpdateCommunityFeedSettingsRequest.php`
- `app/Http/Controllers/Connector/CommunityFeedController.php`
- `app/Http/Controllers/Connector/CommunityCommentController.php`
- `app/Http/Controllers/Connector/CommunityReactionController.php`
- `app/Http/Controllers/Connector/CommunityReportController.php`
- `app/Http/Controllers/Connector/CommunityModerationController.php`
- `app/Http/Controllers/Admin/CommunityFeedController.php`
- `app/Http/Controllers/Admin/CommunityModerationController.php`
- `app/Http/Controllers/Admin/CommunityFeedSettingsController.php`
- `app/Notifications/Community/CommunityPostPendingReviewNotification.php`
- `app/Notifications/Community/CommunityPostDecisionNotification.php`
- `app/Notifications/Community/CommunityPostEscalatedNotification.php`
- `app/Notifications/Community/CommunitySafetyEventNotification.php`
- `resources/views/connectors/community/index.blade.php`
- `resources/views/connectors/community/create.blade.php`
- `resources/views/connectors/community/edit.blade.php`
- `resources/views/connectors/community/show.blade.php`
- `resources/views/admin/community/index.blade.php`
- `resources/views/admin/community/show.blade.php`
- `resources/views/admin/community/settings.blade.php`
- `tests/Feature/Community/CommunitySchemaTest.php`
- `tests/Unit/Services/Community/CommunityContentPreScreeningServiceTest.php`
- `tests/Unit/Services/Community/CommunityAccessServiceTest.php`
- `tests/Feature/Community/ConnectorCommunityPostFlowTest.php`
- `tests/Feature/Community/CommunityInteractionSafetyTest.php`
- `tests/Feature/Community/CommunityModerationFlowTest.php`
- `tests/Feature/Community/CommunityReportModerationAdapterTest.php`
- `tests/Feature/Community/CommunityNotificationTest.php`
- `tests/Feature/Community/AdminCommunityFeedControlTest.php`
- `tests/Feature/Community/CommunityUiSmokeTest.php`

Modify:

- `app/Models/Connector.php`: add `communitySpaces()` and `communityPosts()` relationships.
- `app/Models/User.php`: add `communityPosts()`, `communityComments()`, `communityReactions()`, `communityReports()`, `communityModerationActions()`, and `isMinorForCommunityFeed()` helper.
- `app/Enums/ModerationCaseSource.php`: add `CommunityFeed`.
- `app/Providers/AppServiceProvider.php`: register community policies and add community pending/escalation counts to admin view composer.
- `config/connector_permissions.php`: add connector-local community permissions.
- `database/seeders/PermissionSeeder.php`: add global community admin permissions.
- `database/seeders/RoleSeeder.php`: assign global community permissions to admin only.
- `app/Services/Connectors/ConnectorRoleService.php`: validate connector-local permissions by catalog membership instead of requiring `connector.` prefix.
- `routes/connector.php`: add connector community feed routes.
- `routes/admin.php`: add admin community moderation/settings routes.
- `resources/views/layouts/connector-app.blade.php`: add Community navigation item gated by `community.view_space`.
- `resources/views/layouts/admin.blade.php`: add Community Feed moderation/settings navigation under moderation.
- `tests/Feature/Connectors/ConnectorTestHelpers.php`: add helper methods for adult connector members, minor learners, and connector roles with community permissions.
- `tests/Unit/Services/Connectors/ConnectorRoleServiceTest.php`: cover community connector-local permission validation.
- `tests/Feature/Rbac/RbacPermissionCatalogSeederTest.php`: cover global community permissions.
- `tests/Feature/Rbac/RbacRoleCapabilityMatrixSeederTest.php`: verify admin receives global community permissions and non-admin roles do not.
- `tests/Feature/Moderation/ModerationDualWriteParityTest.php`: add community report central-case coverage or keep coverage in the dedicated community adapter test.

---

### Task 1: Add Community Config, Enums, Schema, And Model Relationships

**Files:**
- Create: `config/community_feed.php`
- Create: `app/Enums/CommunityPostType.php`
- Create: `app/Enums/CommunityPostStatus.php`
- Create: `app/Enums/CommunityCommentStatus.php`
- Create: `app/Enums/CommunityReactionType.php`
- Create: `app/Enums/CommunityReportStatus.php`
- Create: `app/Enums/CommunityModerationActionType.php`
- Create: `app/Enums/CommunityPreScreenDecision.php`
- Create: `app/Models/CommunitySpace.php`
- Create: `app/Models/CommunityPost.php`
- Create: `app/Models/CommunityPostVersion.php`
- Create: `app/Models/CommunityComment.php`
- Create: `app/Models/CommunityReaction.php`
- Create: `app/Models/CommunityReport.php`
- Create: `app/Models/CommunityModerationAction.php`
- Create: `app/Models/CommunityFeedSetting.php`
- Create: `database/migrations/2026_08_02_000001_create_community_feed_tables.php`
- Modify: `app/Models/Connector.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Community/CommunitySchemaTest.php`

**Interfaces:**
- Produces: `CommunitySpace::connector(): BelongsTo`, `CommunitySpace::posts(): HasMany`
- Produces: `CommunityPost::space(): BelongsTo`, `CommunityPost::connector(): BelongsTo`, `CommunityPost::author(): BelongsTo`, `CommunityPost::comments(): HasMany`, `CommunityPost::reactions(): HasMany`, `CommunityPost::versions(): HasMany`, `CommunityPost::reports(): HasMany`
- Produces: `CommunityPost::isPublished(): bool`, `CommunityPost::isLocked(): bool`, `CommunityPost::isVisibleToMembers(): bool`
- Produces: `User::isMinorForCommunityFeed(): bool`
- Produces: `Connector::communitySpaces(): HasMany`, `Connector::communityPosts(): HasMany`

- [ ] **Step 1: Write the schema test**

Create `tests/Feature/Community/CommunitySchemaTest.php`:

```php
<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityPostStatus;
use App\Enums\CommunityPostType;
use App\Enums\CommunityReactionType;
use App\Models\CommunityComment;
use App\Models\CommunityModerationAction;
use App\Models\CommunityPost;
use App\Models\CommunityPostVersion;
use App\Models\CommunityReaction;
use App\Models\CommunityReport;
use App\Models\CommunitySpace;
use App\Models\Connector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunitySchemaTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_community_feed_tables_exist_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('community_spaces', [
            'connector_id', 'name', 'status', 'settings', 'frozen_at', 'frozen_by', 'freeze_reason',
        ]));

        $this->assertTrue(Schema::hasColumns('community_posts', [
            'community_space_id', 'connector_id', 'author_id', 'post_type', 'status', 'title', 'body',
            'resource_url', 'prescreen_decision', 'prescreen_flags', 'published_at', 'published_by',
            'locked_at', 'locked_by', 'hidden_at', 'hidden_by', 'removed_at', 'removed_by',
            'escalated_at', 'escalated_by', 'moderation_case_id',
        ]));

        $this->assertTrue(Schema::hasColumns('community_comments', [
            'community_post_id', 'author_id', 'body', 'status', 'prescreen_decision', 'prescreen_flags',
            'hidden_at', 'hidden_by', 'removed_at', 'removed_by', 'escalated_at', 'escalated_by',
        ]));

        $this->assertTrue(Schema::hasColumns('community_reactions', [
            'community_post_id', 'user_id', 'reaction_type',
        ]));

        $this->assertTrue(Schema::hasColumns('community_reports', [
            'community_post_id', 'community_comment_id', 'reporter_id', 'reported_user_id',
            'reason_code', 'details', 'status', 'moderation_case_id',
        ]));

        $this->assertTrue(Schema::hasColumns('community_post_versions', [
            'community_post_id', 'edited_by', 'version_number', 'title', 'body', 'resource_url',
            'post_type', 'prescreen_decision', 'prescreen_flags',
        ]));

        $this->assertTrue(Schema::hasColumns('community_moderation_actions', [
            'connector_id', 'community_space_id', 'actor_id', 'target_type', 'target_id',
            'action_type', 'previous_status', 'new_status', 'reason', 'metadata',
        ]));

        $this->assertTrue(Schema::hasColumns('community_feed_settings', [
            'scope_type', 'scope_id', 'settings', 'updated_by',
        ]));
    }

    public function test_relationships_connect_space_post_comments_reactions_reports_and_versions(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $space = CommunitySpace::query()->create([
            'connector_id' => $connector->id,
            'name' => $connector->name.' Community',
            'status' => 'active',
        ]);

        $post = CommunityPost::query()->create([
            'community_space_id' => $space->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => CommunityPostType::Announcement,
            'status' => CommunityPostStatus::Published,
            'title' => 'Clinic schedule',
            'body' => 'A resource announcement for adults.',
            'prescreen_decision' => 'allow',
            'published_at' => now(),
            'published_by' => $owner->id,
        ]);

        $comment = CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'author_id' => $owner->id,
            'body' => 'Helpful.',
            'status' => 'visible',
            'prescreen_decision' => 'allow',
        ]);

        $reaction = CommunityReaction::query()->create([
            'community_post_id' => $post->id,
            'user_id' => $owner->id,
            'reaction_type' => CommunityReactionType::Helpful,
        ]);

        $report = CommunityReport::query()->create([
            'community_post_id' => $post->id,
            'reporter_id' => $owner->id,
            'reported_user_id' => $owner->id,
            'reason_code' => 'safety_concern',
            'details' => 'Needs a second look.',
            'status' => 'open',
        ]);

        $version = CommunityPostVersion::query()->create([
            'community_post_id' => $post->id,
            'edited_by' => $owner->id,
            'version_number' => 1,
            'title' => $post->title,
            'body' => $post->body,
            'post_type' => $post->post_type,
            'prescreen_decision' => 'allow',
        ]);

        CommunityModerationAction::query()->create([
            'connector_id' => $connector->id,
            'community_space_id' => $space->id,
            'actor_id' => $owner->id,
            'target_type' => CommunityPost::class,
            'target_id' => $post->id,
            'action_type' => 'approve',
            'previous_status' => 'pending_review',
            'new_status' => 'published',
            'reason' => 'Approved for publication.',
        ]);

        $this->assertTrue($connector->communitySpaces()->whereKey($space)->exists());
        $this->assertSame($space->id, $post->space->id);
        $this->assertSame($connector->id, $post->connector->id);
        $this->assertSame($owner->id, $post->author->id);
        $this->assertSame($comment->id, $post->comments->first()->id);
        $this->assertSame($reaction->id, $post->reactions->first()->id);
        $this->assertSame($report->id, $post->reports->first()->id);
        $this->assertSame($version->id, $post->versions->first()->id);
        $this->assertTrue($post->isPublished());
        $this->assertFalse($post->isLocked());
        $this->assertTrue($post->isVisibleToMembers());
    }
}
```

- [ ] **Step 2: Run the schema test to verify failure**

Run:

```bash
php artisan test tests/Feature/Community/CommunitySchemaTest.php
```

Expected: FAIL because the tables, enums, and models do not exist.

- [ ] **Step 3: Add `config/community_feed.php`**

Use this exact structure:

```php
<?php

return [
    'post_types' => [
        'announcement' => 'Announcement',
        'resource' => 'Educational Resource',
        'moderated_question' => 'Moderated Question',
    ],
    'post_statuses' => [
        'draft' => 'Draft',
        'pending_review' => 'Pending Review',
        'published' => 'Published',
        'hidden' => 'Hidden',
        'locked' => 'Locked',
        'removed' => 'Removed',
        'escalated' => 'Escalated',
        'archived' => 'Archived',
    ],
    'comment_statuses' => [
        'visible' => 'Visible',
        'pending_review' => 'Pending Review',
        'hidden' => 'Hidden',
        'removed' => 'Removed',
        'escalated' => 'Escalated',
    ],
    'reactions' => [
        'learned' => 'Learned',
        'helpful' => 'Helpful',
        'question' => 'Question',
        'support' => 'Support',
    ],
    'report_reasons' => [
        'contact_solicitation' => 'Contact solicitation',
        'unsafe_advice' => 'Unsafe advice',
        'harassment' => 'Harassment',
        'spam' => 'Spam',
        'sensitive_disclosure' => 'Sensitive disclosure',
        'other' => 'Other',
    ],
    'link_allowlist_hosts' => [
        'doh.gov.ph',
        'who.int',
        'unicef.org',
    ],
    'rate_limits' => [
        'posts_per_minute' => 3,
        'comments_per_minute' => 6,
        'reports_per_minute' => 6,
    ],
    'default_suspended_connector_visibility' => 'read_only',
];
```

- [ ] **Step 4: Add community enums**

Each enum should include `label(): string` and `values(): array`. Example pattern:

```php
<?php

namespace App\Enums;

enum CommunityPostType: string
{
    case Announcement = 'announcement';
    case Resource = 'resource';
    case ModeratedQuestion = 'moderated_question';

    public function label(): string
    {
        return config('community_feed.post_types.'.$this->value, str($this->value)->headline()->toString());
    }

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
```

Use the same pattern for:

```php
enum CommunityPostStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Hidden = 'hidden';
    case Locked = 'locked';
    case Removed = 'removed';
    case Escalated = 'escalated';
    case Archived = 'archived';
}
```

```php
enum CommunityCommentStatus: string
{
    case Visible = 'visible';
    case PendingReview = 'pending_review';
    case Hidden = 'hidden';
    case Removed = 'removed';
    case Escalated = 'escalated';
}
```

```php
enum CommunityReactionType: string
{
    case Learned = 'learned';
    case Helpful = 'helpful';
    case Question = 'question';
    case Support = 'support';
}
```

```php
enum CommunityReportStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
```

```php
enum CommunityModerationActionType: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case Hide = 'hide';
    case Lock = 'lock';
    case Unlock = 'unlock';
    case Restore = 'restore';
    case Remove = 'remove';
    case Escalate = 'escalate';
    case Freeze = 'freeze';
    case Unfreeze = 'unfreeze';
}
```

```php
enum CommunityPreScreenDecision: string
{
    case Allow = 'allow';
    case PendingReview = 'pending_review';
    case BlockWithFeedback = 'block_with_feedback';
    case AutoHideAndEscalate = 'auto_hide_and_escalate';
}
```

- [ ] **Step 5: Add the migration**

Create `database/migrations/2026_08_02_000001_create_community_feed_tables.php` with:

```php
Schema::create('community_spaces', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('connector_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('status', 32)->default('active');
    $table->json('settings')->nullable();
    $table->timestamp('frozen_at')->nullable();
    $table->foreignId('frozen_by')->nullable()->constrained('users')->nullOnDelete();
    $table->text('freeze_reason')->nullable();
    $table->timestamps();
    $table->unique('connector_id');
    $table->index(['status', 'frozen_at']);
});

Schema::create('community_posts', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('community_space_id')->constrained('community_spaces')->cascadeOnDelete();
    $table->foreignId('connector_id')->constrained()->cascadeOnDelete();
    $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
    $table->string('post_type', 32);
    $table->string('status', 32)->default('draft');
    $table->string('title', 160);
    $table->text('body');
    $table->string('resource_url', 2048)->nullable();
    $table->string('prescreen_decision', 32)->nullable();
    $table->json('prescreen_flags')->nullable();
    $table->timestamp('submitted_at')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('locked_at')->nullable();
    $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
    $table->text('lock_reason')->nullable();
    $table->timestamp('hidden_at')->nullable();
    $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
    $table->text('hidden_reason')->nullable();
    $table->timestamp('removed_at')->nullable();
    $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->text('removed_reason')->nullable();
    $table->timestamp('escalated_at')->nullable();
    $table->foreignId('escalated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('moderation_case_id')->nullable()->constrained('moderation_cases')->nullOnDelete();
    $table->timestamps();
    $table->index(['connector_id', 'status', 'created_at'], 'community_posts_connector_status_created_idx');
    $table->index(['community_space_id', 'status', 'created_at'], 'community_posts_space_status_created_idx');
    $table->index(['author_id', 'created_at'], 'community_posts_author_created_idx');
});
```

Add the remaining tables in the same migration:

```php
Schema::create('community_post_versions', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('community_post_id')->constrained('community_posts')->cascadeOnDelete();
    $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
    $table->unsignedInteger('version_number');
    $table->string('title', 160);
    $table->text('body');
    $table->string('resource_url', 2048)->nullable();
    $table->string('post_type', 32);
    $table->string('prescreen_decision', 32)->nullable();
    $table->json('prescreen_flags')->nullable();
    $table->timestamps();
    $table->unique(['community_post_id', 'version_number'], 'community_post_versions_post_version_unique');
});

Schema::create('community_comments', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('community_post_id')->constrained('community_posts')->cascadeOnDelete();
    $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
    $table->text('body');
    $table->string('status', 32)->default('visible');
    $table->string('prescreen_decision', 32)->nullable();
    $table->json('prescreen_flags')->nullable();
    $table->timestamp('hidden_at')->nullable();
    $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
    $table->text('hidden_reason')->nullable();
    $table->timestamp('removed_at')->nullable();
    $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->text('removed_reason')->nullable();
    $table->timestamp('escalated_at')->nullable();
    $table->foreignId('escalated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index(['community_post_id', 'status', 'created_at'], 'community_comments_post_status_created_idx');
    $table->index(['author_id', 'created_at'], 'community_comments_author_created_idx');
});

Schema::create('community_reactions', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('community_post_id')->constrained('community_posts')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('reaction_type', 32);
    $table->timestamps();
    $table->unique(['community_post_id', 'user_id', 'reaction_type'], 'community_reactions_post_user_type_unique');
});

Schema::create('community_reports', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('community_post_id')->nullable()->constrained('community_posts')->cascadeOnDelete();
    $table->foreignId('community_comment_id')->nullable()->constrained('community_comments')->cascadeOnDelete();
    $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();
    $table->string('reason_code', 64);
    $table->text('details')->nullable();
    $table->string('status', 32)->default('open');
    $table->foreignId('moderation_case_id')->nullable()->constrained('moderation_cases')->nullOnDelete();
    $table->timestamps();
    $table->index(['status', 'created_at'], 'community_reports_status_created_idx');
    $table->index(['reported_user_id', 'created_at'], 'community_reports_user_created_idx');
});

Schema::create('community_moderation_actions', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('connector_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('community_space_id')->nullable()->constrained('community_spaces')->nullOnDelete();
    $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
    $table->string('target_type');
    $table->unsignedBigInteger('target_id');
    $table->string('action_type', 32);
    $table->string('previous_status', 32)->nullable();
    $table->string('new_status', 32)->nullable();
    $table->text('reason')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->index(['target_type', 'target_id'], 'community_moderation_target_idx');
    $table->index(['connector_id', 'created_at'], 'community_moderation_connector_created_idx');
});

Schema::create('community_feed_settings', function (Blueprint $table): void {
    $table->id();
    $table->string('scope_type', 32);
    $table->unsignedBigInteger('scope_id')->nullable();
    $table->json('settings')->nullable();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->unique(['scope_type', 'scope_id'], 'community_feed_settings_scope_unique');
});
```

The migration `down()` must drop tables in reverse dependency order.

- [ ] **Step 6: Add models and relationships**

Model casts should use the enums where the app already follows enum-cast style:

```php
protected function casts(): array
{
    return [
        'post_type' => CommunityPostType::class,
        'status' => CommunityPostStatus::class,
        'prescreen_flags' => 'array',
        'submitted_at' => 'datetime',
        'published_at' => 'datetime',
        'locked_at' => 'datetime',
        'hidden_at' => 'datetime',
        'removed_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];
}
```

Add post helpers:

```php
public function isPublished(): bool
{
    return $this->status === CommunityPostStatus::Published;
}

public function isLocked(): bool
{
    return $this->status === CommunityPostStatus::Locked || $this->locked_at !== null;
}

public function isVisibleToMembers(): bool
{
    return in_array($this->status?->value ?? $this->status, [
        CommunityPostStatus::Published->value,
        CommunityPostStatus::Locked->value,
    ], true);
}
```

Add `User::isMinorForCommunityFeed()`:

```php
public function isMinorForCommunityFeed(): bool
{
    if (in_array($this->account_type, [
        self::ACCOUNT_TYPE_LEARNER_CHILD,
        self::ACCOUNT_TYPE_LEARNER_TEEN,
    ], true)) {
        return true;
    }

    $age = $this->calculateAge();

    if ($age !== null) {
        return $age < 18;
    }

    $profileBirthdate = $this->learnerProfile?->birthdate;

    if ($profileBirthdate) {
        return \Carbon\Carbon::parse($profileBirthdate)->age < 18;
    }

    return $this->isLearner() && $this->deriveAgeBracketCache() !== 'adults';
}
```

- [ ] **Step 7: Run the schema test to verify pass**

Run:

```bash
php artisan test tests/Feature/Community/CommunitySchemaTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add config/community_feed.php app/Enums app/Models/Community*.php app/Models/Connector.php app/Models/User.php database/migrations/2026_08_02_000001_create_community_feed_tables.php tests/Feature/Community/CommunitySchemaTest.php
git commit -m "feat: add community feed schema"
```

---

### Task 2: Add Global And Connector-Local Community Permissions

**Files:**
- Modify: `config/connector_permissions.php`
- Modify: `app/Services/Connectors/ConnectorRoleService.php`
- Modify: `database/seeders/PermissionSeeder.php`
- Modify: `database/seeders/RoleSeeder.php`
- Modify: `tests/Feature/Connectors/ConnectorTestHelpers.php`
- Modify: `tests/Unit/Services/Connectors/ConnectorRoleServiceTest.php`
- Modify: `tests/Feature/Rbac/RbacPermissionCatalogSeederTest.php`
- Modify: `tests/Feature/Rbac/RbacRoleCapabilityMatrixSeederTest.php`

**Interfaces:**
- Produces: connector-local permissions `community.view_space`, `community.create_post`, `community.edit_own_post`, `community.manage_posts`, `community.approve_posts`, `community.lock_threads`, `community.manage_comments`, `community.escalate_to_platform`
- Produces: global permissions `community.view_any`, `community.moderate_any`, `community.freeze`, `community.manage_settings`
- Modifies: `ConnectorRoleService::validatePermissionKeys(array $keys): array` accepts any key present in `config('connector_permissions.permissions')`

- [ ] **Step 1: Write connector permission tests**

Append to `tests/Unit/Services/Connectors/ConnectorRoleServiceTest.php`:

```php
public function test_community_permissions_are_valid_connector_local_permissions(): void
{
    $service = app(\App\Services\Connectors\ConnectorRoleService::class);

    $keys = $service->validatePermissionKeys([
        'community.view_space',
        'community.create_post',
        'community.approve_posts',
        'community.escalate_to_platform',
    ]);

    $this->assertSame([
        'community.view_space',
        'community.create_post',
        'community.approve_posts',
        'community.escalate_to_platform',
    ], $keys);
}

public function test_permission_validation_rejects_unknown_community_permission(): void
{
    $service = app(\App\Services\Connectors\ConnectorRoleService::class);

    $this->expectException(\Illuminate\Validation\ValidationException::class);

    $service->validatePermissionKeys(['community.promote_to_global']);
}
```

- [ ] **Step 2: Write RBAC seeder tests**

Append to `tests/Feature/Rbac/RbacPermissionCatalogSeederTest.php`:

```php
public function test_global_community_permissions_are_seeded(): void
{
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    foreach (['community.view_any', 'community.moderate_any', 'community.freeze', 'community.manage_settings'] as $permission) {
        $this->assertDatabaseHas('permissions', [
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }
}
```

Append to `tests/Feature/Rbac/RbacRoleCapabilityMatrixSeederTest.php`:

```php
public function test_admin_gets_global_community_permissions_and_standard_roles_do_not(): void
{
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $admin = \Spatie\Permission\Models\Role::findByName('admin');
    $instructor = \Spatie\Permission\Models\Role::findByName('instructor');
    $learner = \Spatie\Permission\Models\Role::findByName('learner');
    $parent = \Spatie\Permission\Models\Role::findByName('parent');

    $this->assertTrue($admin->hasPermissionTo('community.moderate_any'));
    $this->assertTrue($admin->hasPermissionTo('community.freeze'));
    $this->assertFalse($instructor->hasPermissionTo('community.moderate_any'));
    $this->assertFalse($learner->hasPermissionTo('community.view_any'));
    $this->assertFalse($parent->hasPermissionTo('community.manage_settings'));
}
```

- [ ] **Step 3: Run tests to verify failure**

Run:

```bash
php artisan test tests/Unit/Services/Connectors/ConnectorRoleServiceTest.php tests/Feature/Rbac/RbacPermissionCatalogSeederTest.php tests/Feature/Rbac/RbacRoleCapabilityMatrixSeederTest.php
```

Expected: FAIL because community permissions are not configured or seeded.

- [ ] **Step 4: Add connector-local community permissions**

In `config/connector_permissions.php`, add a `community` group under `permissions`:

```php
'community' => [
    'community.view_space' => 'View community space',
    'community.create_post' => 'Create community posts',
    'community.edit_own_post' => 'Edit own community posts',
    'community.manage_posts' => 'Manage community posts',
    'community.approve_posts' => 'Approve moderated community posts',
    'community.lock_threads' => 'Lock community threads',
    'community.manage_comments' => 'Manage community comments',
    'community.escalate_to_platform' => 'Escalate community content to platform admins',
],
```

- [ ] **Step 5: Relax connector permission validation to catalog membership**

In `ConnectorRoleService::validatePermissionKeys`, replace the prefix check:

```php
if (! str_starts_with((string) $key, 'connector.') || ! in_array($key, $allowed, true)) {
```

with:

```php
if (! in_array($key, $allowed, true)) {
```

Keep the exception message:

```php
'permissions' => 'Invalid connector permission: '.$key,
```

- [ ] **Step 6: Add global community permissions to `PermissionSeeder`**

Add these strings to `$canonical`:

```php
'community.view_any',
'community.moderate_any',
'community.freeze',
'community.manage_settings',
```

- [ ] **Step 7: Keep global community permissions admin-only**

No new permissions should be added to instructor, learner, parent, counselor, clinic, or organization role permission arrays in `RoleSeeder`. Admin already receives all permissions through:

```php
$admin->syncPermissions(Permission::query()->pluck('name')->all());
```

- [ ] **Step 8: Add test helpers for community roles**

In `tests/Feature/Connectors/ConnectorTestHelpers.php`, add:

```php
private function createAdultConnectorMember(\App\Models\Connector $connector, array $permissions = []): \App\Models\User
{
    $member = \App\Models\User::factory()->create([
        'role' => 'learner',
        'birthdate' => now()->subYears(28)->toDateString(),
        'account_type' => \App\Models\User::ACCOUNT_TYPE_LEARNER_ADULT,
        'age_bracket_cached' => 'adults',
    ]);
    $member->assignRole('learner');

    $role = $this->createCustomRole($connector, $permissions);

    $connector->memberships()->create([
        'user_id' => $member->id,
        'connector_role_id' => $role->id,
        'status' => 'active',
        'accepted_at' => now(),
    ]);

    return $member;
}

private function createMinorLearner(int $age = 15): \App\Models\User
{
    $minor = \App\Models\User::factory()->create([
        'role' => 'learner',
        'birthdate' => now()->subYears($age)->toDateString(),
        'account_type' => $age <= 12
            ? \App\Models\User::ACCOUNT_TYPE_LEARNER_CHILD
            : \App\Models\User::ACCOUNT_TYPE_LEARNER_TEEN,
        'age_bracket_cached' => $age <= 12 ? 'kids' : 'teens',
    ]);
    $minor->assignRole('learner');

    return $minor;
}
```

- [ ] **Step 9: Run tests to verify pass**

Run:

```bash
php artisan test tests/Unit/Services/Connectors/ConnectorRoleServiceTest.php tests/Feature/Rbac/RbacPermissionCatalogSeederTest.php tests/Feature/Rbac/RbacRoleCapabilityMatrixSeederTest.php
```

Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add config/connector_permissions.php app/Services/Connectors/ConnectorRoleService.php database/seeders/PermissionSeeder.php database/seeders/RoleSeeder.php tests/Feature/Connectors/ConnectorTestHelpers.php tests/Unit/Services/Connectors/ConnectorRoleServiceTest.php tests/Feature/Rbac/RbacPermissionCatalogSeederTest.php tests/Feature/Rbac/RbacRoleCapabilityMatrixSeederTest.php
git commit -m "feat: add community feed permissions"
```

---

### Task 3: Add Community Access Gates And Freeze Settings Service

**Files:**
- Create: `app/Services/Community/CommunityAccessService.php`
- Create: `app/Services/Community/CommunityFeedSettingsService.php`
- Create: `app/Policies/CommunityPostPolicy.php`
- Create: `app/Policies/CommunityCommentPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Services/Community/CommunityAccessServiceTest.php`

**Interfaces:**
- Consumes: `ConnectorAccessService::hasPermission(User $user, Connector $connector, string $permissionKey): bool`
- Produces: `CommunityAccessService::canUseCommunity(User $user): bool`
- Produces: `CommunityAccessService::canViewSpace(User $user, Connector $connector): bool`
- Produces: `CommunityAccessService::canCreatePost(User $user, Connector $connector): bool`
- Produces: `CommunityAccessService::canEditPost(User $user, CommunityPost $post): bool`
- Produces: `CommunityAccessService::canModerateSpace(User $user, Connector $connector): bool`
- Produces: `CommunityAccessService::canManageComments(User $user, Connector $connector): bool`
- Produces: `CommunityAccessService::canReact(User $user, CommunityPost $post): bool`
- Produces: `CommunityAccessService::abortUnlessCanViewSpace(User $user, Connector $connector): void`
- Produces: `CommunityAccessService::abortUnlessCanCreatePost(User $user, Connector $connector): void`
- Produces: `CommunityAccessService::abortUnlessCanModerateSpace(User $user, Connector $connector): void`
- Produces: `CommunityFeedSettingsService::isGloballyFrozen(): bool`
- Produces: `CommunityFeedSettingsService::freezeGlobal(User $actor, string $reason): CommunityFeedSetting`
- Produces: `CommunityFeedSettingsService::unfreezeGlobal(User $actor): CommunityFeedSetting`
- Produces: `CommunityFeedSettingsService::isSpaceFrozen(CommunitySpace $space): bool`

- [ ] **Step 1: Write access service tests**

Create `tests/Unit/Services/Community/CommunityAccessServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services\Community;

use App\Models\CommunityFeedSetting;
use App\Models\CommunityPost;
use App\Models\CommunitySpace;
use App\Models\User;
use App\Services\Community\CommunityAccessService;
use App\Services\Community\CommunityFeedSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityAccessServiceTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_minor_learners_are_excluded_even_with_connector_membership(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $minor = $this->createMinorLearner(15);
        $role = $this->createCustomRole($connector, ['community.view_space', 'community.create_post']);
        $connector->memberships()->create([
            'user_id' => $minor->id,
            'connector_role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $service = app(CommunityAccessService::class);

        $this->assertFalse($service->canUseCommunity($minor));
        $this->assertFalse($service->canViewSpace($minor, $connector));
        $this->assertFalse($service->canCreatePost($minor, $connector));
    }

    public function test_adult_connector_member_requires_connector_local_permission(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $poster = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.create_post']);

        $service = app(CommunityAccessService::class);

        $this->assertTrue($service->canViewSpace($viewer, $connector));
        $this->assertFalse($service->canCreatePost($viewer, $connector));
        $this->assertTrue($service->canCreatePost($poster, $connector));
    }

    public function test_suspended_connector_and_global_freeze_block_writes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $service = app(CommunityAccessService::class);

        $this->assertTrue($service->canCreatePost($owner, $connector));

        $connector->update(['status' => 'suspended']);
        $this->assertFalse($service->canCreatePost($owner, $connector->fresh()));

        $connector->update(['status' => 'verified']);
        app(CommunityFeedSettingsService::class)->freezeGlobal($admin, 'Safety incident.');
        $this->assertFalse($service->canCreatePost($owner, $connector->fresh()));
    }

    public function test_removed_membership_and_active_user_suspension_block_access(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $member = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.create_post']);

        $this->assertTrue(app(CommunityAccessService::class)->canCreatePost($member, $connector));

        $connector->memberships()->where('user_id', $member->id)->update([
            'status' => 'removed',
            'removed_at' => now(),
        ]);

        $this->assertFalse(app(CommunityAccessService::class)->canCreatePost($member, $connector->fresh()));

        $connector->memberships()->where('user_id', $member->id)->update([
            'status' => 'active',
            'removed_at' => null,
        ]);

        \App\Models\UserSuspension::query()->create([
            'user_id' => $member->id,
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
            'appeal_status' => 'none',
        ]);

        $this->assertFalse(app(CommunityAccessService::class)->canCreatePost($member->fresh(), $connector->fresh()));
    }
}
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Unit/Services/Community/CommunityAccessServiceTest.php
```

Expected: FAIL because the services do not exist.

- [ ] **Step 3: Implement `CommunityFeedSettingsService`**

Core methods:

```php
public function isGloballyFrozen(): bool
{
    $settings = CommunityFeedSetting::query()
        ->where('scope_type', 'global')
        ->whereNull('scope_id')
        ->first();

    return (bool) data_get($settings?->settings, 'frozen', false);
}

public function freezeGlobal(User $actor, string $reason): CommunityFeedSetting
{
    return CommunityFeedSetting::query()->updateOrCreate(
        ['scope_type' => 'global', 'scope_id' => null],
        [
            'settings' => [
                'frozen' => true,
                'reason' => $reason,
                'frozen_at' => now()->toDateTimeString(),
            ],
            'updated_by' => $actor->id,
        ],
    );
}

public function unfreezeGlobal(User $actor): CommunityFeedSetting
{
    return CommunityFeedSetting::query()->updateOrCreate(
        ['scope_type' => 'global', 'scope_id' => null],
        [
            'settings' => [
                'frozen' => false,
                'unfrozen_at' => now()->toDateTimeString(),
            ],
            'updated_by' => $actor->id,
        ],
    );
}

public function isSpaceFrozen(CommunitySpace $space): bool
{
    return $this->isGloballyFrozen() || $space->frozen_at !== null;
}
```

- [ ] **Step 4: Implement `CommunityAccessService`**

Access checks must deny minors and frozen/suspended states:

```php
public function canUseCommunity(User $user): bool
{
    return ! $user->isMinorForCommunityFeed()
        && $user->status !== User::STATUS_SUSPENDED
        && ! $this->hasActiveSuspension($user);
}

public function canViewSpace(User $user, Connector $connector): bool
{
    if ($user->hasRole('admin') && $user->can('community.view_any')) {
        return true;
    }

    return $this->canUseCommunity($user)
        && $connector->status === 'verified'
        && $this->connectorAccess->hasPermission($user, $connector, 'community.view_space');
}

public function canCreatePost(User $user, Connector $connector): bool
{
    return $this->canUseCommunity($user)
        && ! $this->settings->isGloballyFrozen()
        && $connector->status === 'verified'
        && $this->connectorAccess->hasPermission($user, $connector, 'community.create_post');
}

public function canModerateSpace(User $user, Connector $connector): bool
{
    if ($user->hasRole('admin') && $user->can('community.moderate_any')) {
        return true;
    }

    return $this->canUseCommunity($user)
        && $connector->status === 'verified'
        && (
            $this->connectorAccess->hasPermission($user, $connector, 'community.manage_posts')
            || $this->connectorAccess->hasPermission($user, $connector, 'community.approve_posts')
        );
}
```

Add active suspension lookup:

```php
private function hasActiveSuspension(User $user): bool
{
    return \App\Models\UserSuspension::query()
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->where(function ($query): void {
            $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
        })
        ->exists();
}
```

Use `abort_unless(..., 403)` in `abortUnless...` wrappers and return 404 for cross-connector object ownership checks.

- [ ] **Step 5: Add policies and register them**

Register in `AppServiceProvider::boot()`:

```php
Gate::policy(\App\Models\CommunityPost::class, \App\Policies\CommunityPostPolicy::class);
Gate::policy(\App\Models\CommunityComment::class, \App\Policies\CommunityCommentPolicy::class);
```

Policy methods should delegate to `CommunityAccessService`:

```php
public function view(User $user, CommunityPost $post): bool
{
    return app(CommunityAccessService::class)->canViewSpace($user, $post->connector);
}

public function update(User $user, CommunityPost $post): bool
{
    return app(CommunityAccessService::class)->canEditPost($user, $post);
}

public function moderate(User $user, CommunityPost $post): bool
{
    return app(CommunityAccessService::class)->canModerateSpace($user, $post->connector);
}
```

- [ ] **Step 6: Run test to verify pass**

Run:

```bash
php artisan test tests/Unit/Services/Community/CommunityAccessServiceTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Community/CommunityAccessService.php app/Services/Community/CommunityFeedSettingsService.php app/Policies/CommunityPostPolicy.php app/Policies/CommunityCommentPolicy.php app/Providers/AppServiceProvider.php tests/Unit/Services/Community/CommunityAccessServiceTest.php
git commit -m "feat: add community access gates"
```

---

### Task 4: Add Community Content Pre-Screening

**Files:**
- Create: `app/Data/Community/CommunityPreScreenResult.php`
- Create: `app/Services/Community/CommunityContentPreScreeningService.php`
- Test: `tests/Unit/Services/Community/CommunityContentPreScreeningServiceTest.php`

**Interfaces:**
- Produces: `CommunityPreScreenResult::__construct(CommunityPreScreenDecision $decision, array $flags = [], ?string $message = null)`
- Produces: `CommunityPreScreenResult::allowsPublication(): bool`
- Produces: `CommunityContentPreScreeningService::screenPost(array $payload): CommunityPreScreenResult`
- Produces: `CommunityContentPreScreeningService::screenComment(string $body): CommunityPreScreenResult`

- [ ] **Step 1: Write pre-screening tests**

Create `tests/Unit/Services/Community/CommunityContentPreScreeningServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services\Community;

use App\Enums\CommunityPreScreenDecision;
use App\Services\Community\CommunityContentPreScreeningService;
use Tests\TestCase;

class CommunityContentPreScreeningServiceTest extends TestCase
{
    public function test_allows_low_risk_announcement(): void
    {
        $result = app(CommunityContentPreScreeningService::class)->screenPost([
            'post_type' => 'announcement',
            'title' => 'Clinic schedule update',
            'body' => 'The community session starts at 9 AM in the connector webinar room.',
            'resource_url' => null,
        ]);

        $this->assertSame(CommunityPreScreenDecision::Allow, $result->decision);
        $this->assertTrue($result->allowsPublication());
    }

    public function test_moderated_question_goes_to_pending_review(): void
    {
        $result = app(CommunityContentPreScreeningService::class)->screenPost([
            'post_type' => 'moderated_question',
            'title' => 'Question for educators',
            'body' => 'How should families discuss consent in a values-based way?',
            'resource_url' => null,
        ]);

        $this->assertSame(CommunityPreScreenDecision::PendingReview, $result->decision);
        $this->assertContains('moderated_question', $result->flags);
    }

    public function test_contact_information_is_blocked_with_feedback(): void
    {
        $result = app(CommunityContentPreScreeningService::class)->screenPost([
            'post_type' => 'announcement',
            'title' => 'Message me',
            'body' => 'Email me at adult@example.com or text 09171234567.',
            'resource_url' => null,
        ]);

        $this->assertSame(CommunityPreScreenDecision::BlockWithFeedback, $result->decision);
        $this->assertContains('contact_information', $result->flags);
    }

    public function test_dm_and_meetup_language_auto_hides_and_escalates(): void
    {
        $result = app(CommunityContentPreScreeningService::class)->screenComment('DM me privately and meet me near your school.');

        $this->assertSame(CommunityPreScreenDecision::AutoHideAndEscalate, $result->decision);
        $this->assertContains('off_platform_contact', $result->flags);
        $this->assertContains('meetup_or_location_targeting', $result->flags);
    }

    public function test_external_link_not_on_allowlist_goes_to_pending_review(): void
    {
        $result = app(CommunityContentPreScreeningService::class)->screenPost([
            'post_type' => 'resource',
            'title' => 'External resource',
            'body' => 'Please read this resource.',
            'resource_url' => 'https://example.com/article',
        ]);

        $this->assertSame(CommunityPreScreenDecision::PendingReview, $result->decision);
        $this->assertContains('external_link_review', $result->flags);
    }
}
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Unit/Services/Community/CommunityContentPreScreeningServiceTest.php
```

Expected: FAIL because pre-screening classes do not exist.

- [ ] **Step 3: Add `CommunityPreScreenResult`**

```php
<?php

namespace App\Data\Community;

use App\Enums\CommunityPreScreenDecision;

final readonly class CommunityPreScreenResult
{
    public function __construct(
        public CommunityPreScreenDecision $decision,
        public array $flags = [],
        public ?string $message = null,
    ) {
    }

    public function allowsPublication(): bool
    {
        return $this->decision === CommunityPreScreenDecision::Allow;
    }
}
```

- [ ] **Step 4: Add the pre-screening service**

Implement deterministic regex checks:

```php
private const EMAIL_PATTERN = '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i';
private const PHONE_PATTERN = '/(?:\+?63|0)\s?9\d{2}[\s.-]?\d{3}[\s.-]?\d{4}/';
private const SOCIAL_HANDLE_PATTERN = '/(?:^|\s)@[A-Z0-9._-]{3,}/i';
private const DM_PATTERN = '/\b(dm|pm|private message|message me privately|chat me|telegram|viber|whatsapp|messenger)\b/i';
private const MEETUP_PATTERN = '/\b(meet me|meetup|meet up|near your school|after class|send your location|where do you live)\b/i';
private const THREAT_PATTERN = '/\b(threaten|hurt you|kill|blackmail|expose you)\b/i';
private const SEXUAL_SOLICITATION_PATTERN = '/\b(send pics|nudes|hook up|sexual favor|sext|sexy photo)\b/i';
```

Decision order:

```php
if ($this->matches($text, self::DM_PATTERN) || $this->matches($text, self::MEETUP_PATTERN) || $this->matches($text, self::THREAT_PATTERN) || $this->matches($text, self::SEXUAL_SOLICITATION_PATTERN)) {
    return new CommunityPreScreenResult(CommunityPreScreenDecision::AutoHideAndEscalate, $flags, 'This content requires platform safety review.');
}

if ($this->matches($text, self::EMAIL_PATTERN) || $this->matches($text, self::PHONE_PATTERN) || $this->matches($text, self::SOCIAL_HANDLE_PATTERN)) {
    return new CommunityPreScreenResult(CommunityPreScreenDecision::BlockWithFeedback, $flags, 'Remove personal contact information before posting.');
}

if (($payload['post_type'] ?? null) === CommunityPostType::ModeratedQuestion->value) {
    return new CommunityPreScreenResult(CommunityPreScreenDecision::PendingReview, ['moderated_question'], 'Questions are reviewed before publication.');
}

if ($this->requiresLinkReview((string) ($payload['resource_url'] ?? ''))) {
    return new CommunityPreScreenResult(CommunityPreScreenDecision::PendingReview, ['external_link_review'], 'External links require moderator review.');
}

return new CommunityPreScreenResult(CommunityPreScreenDecision::Allow);
```

Ensure flags are accumulated before return so tests can assert exact reasons.

- [ ] **Step 5: Run test to verify pass**

Run:

```bash
php artisan test tests/Unit/Services/Community/CommunityContentPreScreeningServiceTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Data/Community/CommunityPreScreenResult.php app/Services/Community/CommunityContentPreScreeningService.php tests/Unit/Services/Community/CommunityContentPreScreeningServiceTest.php
git commit -m "feat: add community content prescreening"
```

---

### Task 5: Add Space Resolution And Post Creation/Editing Workflow

**Files:**
- Create: `app/Services/Community/CommunitySpaceService.php`
- Create: `app/Services/Community/CommunityPostService.php`
- Test: `tests/Feature/Community/ConnectorCommunityPostFlowTest.php`

**Interfaces:**
- Consumes: `CommunityAccessService::abortUnlessCanCreatePost(User $user, Connector $connector): void`
- Consumes: `CommunityContentPreScreeningService::screenPost(array $payload): CommunityPreScreenResult`
- Produces: `CommunitySpaceService::spaceForConnector(Connector $connector): CommunitySpace`
- Produces: `CommunityPostService::create(User $author, Connector $connector, array $payload): CommunityPost`
- Produces: `CommunityPostService::update(User $actor, CommunityPost $post, array $payload): CommunityPost`
- Produces: `CommunityPostService::recordVersion(CommunityPost $post, ?User $editor): CommunityPostVersion`

- [ ] **Step 1: Write post workflow tests**

Create `tests/Feature/Community/ConnectorCommunityPostFlowTest.php`:

```php
<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityPostStatus;
use App\Models\CommunityPost;
use App\Models\User;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class ConnectorCommunityPostFlowTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_low_risk_announcement_publishes_immediately(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
            'account_type' => User::ACCOUNT_TYPE_LEARNER_ADULT,
            'age_bracket_cached' => 'adults',
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'title' => 'Health session schedule',
            'body' => 'Adults can attend the connector webinar this Friday.',
            'resource_url' => null,
        ]);

        $this->assertSame(CommunityPostStatus::Published, $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertSame($owner->id, $post->published_by);
        $this->assertDatabaseHas('community_post_versions', [
            'community_post_id' => $post->id,
            'version_number' => 1,
        ]);
    }

    public function test_moderated_question_enters_pending_review(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'moderated_question',
            'title' => 'Question for moderators',
            'body' => 'How should adults discuss consent education with families?',
            'resource_url' => null,
        ]);

        $this->assertSame(CommunityPostStatus::PendingReview, $post->status);
        $this->assertNull($post->published_at);
    }

    public function test_contact_information_blocks_post_with_validation_error(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $this->expectException(ValidationException::class);

        app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'title' => 'Contact me',
            'body' => 'Email adult@example.com for private details.',
            'resource_url' => null,
        ]);
    }

    public function test_editing_published_post_reruns_prescreening_and_versions_content(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'title' => 'First title',
            'body' => 'Safe announcement.',
            'resource_url' => null,
        ]);

        $updated = app(CommunityPostService::class)->update($owner, $post, [
            'post_type' => 'moderated_question',
            'title' => 'Question title',
            'body' => 'Could moderators review this question?',
            'resource_url' => null,
        ]);

        $this->assertSame(CommunityPostStatus::PendingReview, $updated->status);
        $this->assertSame(2, $updated->versions()->count());
    }

    public function test_minor_cannot_create_post(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $minor = $this->createMinorLearner(15);
        $role = $this->createCustomRole($connector, ['community.view_space', 'community.create_post']);
        $connector->memberships()->create([
            'user_id' => $minor->id,
            'connector_role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(CommunityPostService::class)->create($minor, $connector, [
            'post_type' => 'announcement',
            'title' => 'Minor post',
            'body' => 'This must not publish.',
            'resource_url' => null,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Feature/Community/ConnectorCommunityPostFlowTest.php
```

Expected: FAIL because post services do not exist.

- [ ] **Step 3: Implement `CommunitySpaceService`**

```php
public function spaceForConnector(Connector $connector): CommunitySpace
{
    return CommunitySpace::query()->firstOrCreate(
        ['connector_id' => $connector->id],
        [
            'name' => $connector->name.' Community',
            'status' => 'active',
        ],
    );
}
```

- [ ] **Step 4: Implement `CommunityPostService::create`**

Workflow:

```php
$this->access->abortUnlessCanCreatePost($author, $connector);
$space = $this->spaces->spaceForConnector($connector);
$result = $this->preScreening->screenPost($payload);

if ($result->decision === CommunityPreScreenDecision::BlockWithFeedback) {
    throw ValidationException::withMessages(['body' => $result->message ?? 'Revise the content before posting.']);
}

$status = match ($result->decision) {
    CommunityPreScreenDecision::Allow => CommunityPostStatus::Published,
    CommunityPreScreenDecision::PendingReview => CommunityPostStatus::PendingReview,
    CommunityPreScreenDecision::AutoHideAndEscalate => CommunityPostStatus::Escalated,
    CommunityPreScreenDecision::BlockWithFeedback => CommunityPostStatus::Draft,
};

$post = CommunityPost::query()->create([
    'community_space_id' => $space->id,
    'connector_id' => $connector->id,
    'author_id' => $author->id,
    'post_type' => $payload['post_type'],
    'status' => $status,
    'title' => $payload['title'],
    'body' => $payload['body'],
    'resource_url' => $payload['resource_url'] ?? null,
    'prescreen_decision' => $result->decision->value,
    'prescreen_flags' => $result->flags,
    'submitted_at' => now(),
    'published_at' => $status === CommunityPostStatus::Published ? now() : null,
    'published_by' => $status === CommunityPostStatus::Published ? $author->id : null,
    'escalated_at' => $status === CommunityPostStatus::Escalated ? now() : null,
    'escalated_by' => $status === CommunityPostStatus::Escalated ? $author->id : null,
]);

$this->recordVersion($post, $author);
```

- [ ] **Step 5: Implement `CommunityPostService::update`**

Rules:

- Actor must be the author with `community.edit_own_post`, a connector moderator for that connector, or an admin with `community.moderate_any`.
- Rerun pre-screening on the new payload.
- Create a new `CommunityPostVersion` after saving.
- If a published post becomes `pending_review` or `escalated`, clear `published_at` and `published_by`.

Use:

```php
$nextVersion = ((int) $post->versions()->max('version_number')) + 1;
```

- [ ] **Step 6: Run test to verify pass**

Run:

```bash
php artisan test tests/Feature/Community/ConnectorCommunityPostFlowTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Community/CommunitySpaceService.php app/Services/Community/CommunityPostService.php tests/Feature/Community/ConnectorCommunityPostFlowTest.php
git commit -m "feat: add community post workflow"
```

---

### Task 6: Add Flat Comments And Safe Reactions

**Files:**
- Create: `app/Services/Community/CommunityInteractionService.php`
- Test: `tests/Feature/Community/CommunityInteractionSafetyTest.php`

**Interfaces:**
- Consumes: `CommunityAccessService::canManageComments(User $user, Connector $connector): bool`
- Consumes: `CommunityContentPreScreeningService::screenComment(string $body): CommunityPreScreenResult`
- Produces: `CommunityInteractionService::comment(User $author, CommunityPost $post, string $body): CommunityComment`
- Produces: `CommunityInteractionService::react(User $user, CommunityPost $post, CommunityReactionType|string $reactionType): CommunityReaction`
- Produces: `CommunityInteractionService::removeReaction(User $user, CommunityPost $post, CommunityReactionType|string $reactionType): void`

- [ ] **Step 1: Write interaction safety tests**

Create `tests/Feature/Community/CommunityInteractionSafetyTest.php`:

```php
<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityCommentStatus;
use App\Enums\CommunityPostStatus;
use App\Models\CommunityPost;
use App\Models\User;
use App\Services\Community\CommunityInteractionService;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityInteractionSafetyTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_adult_connector_member_can_comment_flat_on_published_unlocked_post(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();
        $commenter = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.manage_comments']);

        $comment = app(CommunityInteractionService::class)->comment($commenter, $post, 'This is helpful for our adult facilitators.');

        $this->assertSame(CommunityCommentStatus::Visible, $comment->status);
        $this->assertSame($post->id, $comment->community_post_id);
        $this->assertFalse(Schema::hasColumn('community_comments', 'parent_comment_id'));
    }

    public function test_minor_cannot_comment(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();
        $minor = $this->createMinorLearner(14);
        $role = $this->createCustomRole($connector, ['community.view_space', 'community.manage_comments']);
        $connector->memberships()->create([
            'user_id' => $minor->id,
            'connector_role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $this->expectException(HttpException::class);

        app(CommunityInteractionService::class)->comment($minor, $post, 'Minor comment');
    }

    public function test_minor_cannot_react(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();
        $minor = $this->createMinorLearner(14);
        $role = $this->createCustomRole($connector, ['community.view_space']);
        $connector->memberships()->create([
            'user_id' => $minor->id,
            'connector_role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $this->expectException(HttpException::class);

        app(CommunityInteractionService::class)->react($minor, $post, 'helpful');
    }

    public function test_contact_seeking_comment_is_auto_escalated_or_blocked(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();

        $comment = app(CommunityInteractionService::class)->comment($author, $post, 'DM me privately and meet me near your school.');

        $this->assertSame(CommunityCommentStatus::Escalated, $comment->status);
        $this->assertContains('off_platform_contact', $comment->prescreen_flags);
    }

    public function test_invalid_reaction_type_is_rejected(): void
    {
        [$connector, $author, $post] = $this->publishedPostFixture();

        $this->expectException(ValidationException::class);

        app(CommunityInteractionService::class)->react($author, $post, 'love');
    }

    private function publishedPostFixture(): array
    {
        $author = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $author->assignRole('learner');
        $connector = $this->createVerifiedConnector($author);
        $post = app(CommunityPostService::class)->create($author, $connector, [
            'post_type' => 'announcement',
            'title' => 'Schedule',
            'body' => 'Safe announcement.',
            'resource_url' => null,
        ]);
        $post->update(['status' => CommunityPostStatus::Published->value, 'published_at' => now()]);

        return [$connector, $author, $post->fresh()];
    }
}
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Feature/Community/CommunityInteractionSafetyTest.php
```

Expected: FAIL because interaction service and routes do not exist.

- [ ] **Step 3: Implement `CommunityInteractionService::comment`**

Rules:

- Deny minors through `CommunityAccessService`.
- Deny when global or space freeze is active.
- Deny when connector status is not `verified`.
- Deny when post is not `published` or `locked`.
- Deny comments when post is locked.
- Pre-screen the comment body.
- `block_with_feedback` throws `ValidationException`.
- `auto_hide_and_escalate` creates the comment with `status = escalated`.
- Low-risk comments create with `status = visible`.

- [ ] **Step 4: Implement `CommunityInteractionService::react` and `removeReaction`**

Rules:

- Accepted reaction types are exactly `CommunityReactionType::values()`.
- Deny minors.
- Deny locked posts.
- Deny hidden, removed, archived, pending, or escalated posts.
- Use `updateOrCreate` to keep reactions idempotent.

```php
CommunityReaction::query()->updateOrCreate(
    [
        'community_post_id' => $post->id,
        'user_id' => $user->id,
        'reaction_type' => $reactionType,
    ],
    []
);
```

- [ ] **Step 5: Run test to verify pass**

Run:

```bash
php artisan test tests/Feature/Community/CommunityInteractionSafetyTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Community/CommunityInteractionService.php tests/Feature/Community/CommunityInteractionSafetyTest.php
git commit -m "feat: add community interactions"
```

---

### Task 7: Add Connector And Platform Moderation Actions

**Files:**
- Create: `app/Services/Community/CommunityModerationService.php`
- Test: `tests/Feature/Community/CommunityModerationFlowTest.php`

**Interfaces:**
- Produces: `CommunityModerationService::approvePost(User $actor, CommunityPost $post, string $reason): CommunityPost`
- Produces: `CommunityModerationService::rejectPost(User $actor, CommunityPost $post, string $reason): CommunityPost`
- Produces: `CommunityModerationService::hidePost(User $actor, CommunityPost $post, string $reason): CommunityPost`
- Produces: `CommunityModerationService::lockPost(User $actor, CommunityPost $post, string $reason): CommunityPost`
- Produces: `CommunityModerationService::unlockPost(User $actor, CommunityPost $post, string $reason): CommunityPost`
- Produces: `CommunityModerationService::restorePost(User $actor, CommunityPost $post, string $reason): CommunityPost`
- Produces: `CommunityModerationService::removePost(User $actor, CommunityPost $post, string $reason): CommunityPost`
- Produces: `CommunityModerationService::escalatePost(User $actor, CommunityPost $post, string $reason): CommunityPost`
- Produces: `CommunityModerationService::hideComment(User $actor, CommunityComment $comment, string $reason): CommunityComment`
- Produces: `CommunityModerationService::removeComment(User $actor, CommunityComment $comment, string $reason): CommunityComment`

- [ ] **Step 1: Write moderation tests**

Create `tests/Feature/Community/CommunityModerationFlowTest.php`:

```php
<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityPostStatus;
use App\Models\CommunityModerationAction;
use App\Models\CommunityPost;
use App\Models\User;
use App\Services\Community\CommunityModerationService;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityModerationFlowTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_connector_moderator_can_approve_hide_lock_and_escalate_own_connector_post(): void
    {
        [$connector, $author, $post] = $this->pendingPostFixture();
        $moderator = $this->createAdultConnectorMember($connector, [
            'community.view_space',
            'community.manage_posts',
            'community.approve_posts',
            'community.lock_threads',
            'community.escalate_to_platform',
        ]);

        $service = app(CommunityModerationService::class);

        $approved = $service->approvePost($moderator, $post, 'Reviewed by connector moderator.');
        $this->assertSame(CommunityPostStatus::Published, $approved->status);

        $locked = $service->lockPost($moderator, $approved, 'Conversation complete.');
        $this->assertSame(CommunityPostStatus::Locked, $locked->status);

        $hidden = $service->hidePost($moderator, $locked, 'Safety concern.');
        $this->assertSame(CommunityPostStatus::Hidden, $hidden->status);

        $escalated = $service->escalatePost($moderator, $hidden, 'Needs platform decision.');
        $this->assertSame(CommunityPostStatus::Escalated, $escalated->status);

        $this->assertGreaterThanOrEqual(4, CommunityModerationAction::query()->where('target_id', $post->id)->count());
    }

    public function test_connector_moderator_cannot_moderate_other_connector_post(): void
    {
        [$connector, $author, $post] = $this->pendingPostFixture();
        $otherOwner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(35)->toDateString()]);
        $otherOwner->assignRole('learner');
        $otherConnector = $this->createVerifiedConnector($otherOwner);
        $otherModerator = $this->createAdultConnectorMember($otherConnector, ['community.manage_posts', 'community.approve_posts']);

        $this->expectException(HttpException::class);

        app(CommunityModerationService::class)->approvePost($otherModerator, $post, 'Cross-connector attempt.');
    }

    public function test_platform_admin_can_moderate_any_connector_post(): void
    {
        [$connector, $author, $post] = $this->pendingPostFixture();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $approved = app(CommunityModerationService::class)->approvePost($admin, $post, 'Platform reviewed.');

        $this->assertSame(CommunityPostStatus::Published, $approved->status);
    }

    private function pendingPostFixture(): array
    {
        $author = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $author->assignRole('learner');
        $connector = $this->createVerifiedConnector($author);
        $post = app(CommunityPostService::class)->create($author, $connector, [
            'post_type' => 'moderated_question',
            'title' => 'Question',
            'body' => 'How should adults discuss consent education?',
            'resource_url' => null,
        ]);

        return [$connector, $author, $post];
    }
}
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Feature/Community/CommunityModerationFlowTest.php
```

Expected: FAIL because moderation service does not exist.

- [ ] **Step 3: Implement status-changing moderation methods**

Use one private method for status transitions:

```php
private function transitionPost(
    User $actor,
    CommunityPost $post,
    CommunityModerationActionType $action,
    CommunityPostStatus $nextStatus,
    string $reason,
): CommunityPost {
    $this->authorizePostModeration($actor, $post, $action);

    $previousStatus = $post->status?->value ?? (string) $post->status;

    $post->forceFill([
        'status' => $nextStatus,
        'published_at' => $nextStatus === CommunityPostStatus::Published ? now() : $post->published_at,
        'published_by' => $nextStatus === CommunityPostStatus::Published ? $actor->id : $post->published_by,
        'locked_at' => $nextStatus === CommunityPostStatus::Locked ? now() : ($action === CommunityModerationActionType::Unlock ? null : $post->locked_at),
        'locked_by' => $nextStatus === CommunityPostStatus::Locked ? $actor->id : ($action === CommunityModerationActionType::Unlock ? null : $post->locked_by),
        'lock_reason' => $nextStatus === CommunityPostStatus::Locked ? $reason : ($action === CommunityModerationActionType::Unlock ? null : $post->lock_reason),
        'hidden_at' => $nextStatus === CommunityPostStatus::Hidden ? now() : $post->hidden_at,
        'hidden_by' => $nextStatus === CommunityPostStatus::Hidden ? $actor->id : $post->hidden_by,
        'hidden_reason' => $nextStatus === CommunityPostStatus::Hidden ? $reason : $post->hidden_reason,
        'removed_at' => $nextStatus === CommunityPostStatus::Removed ? now() : $post->removed_at,
        'removed_by' => $nextStatus === CommunityPostStatus::Removed ? $actor->id : $post->removed_by,
        'removed_reason' => $nextStatus === CommunityPostStatus::Removed ? $reason : $post->removed_reason,
        'escalated_at' => $nextStatus === CommunityPostStatus::Escalated ? now() : $post->escalated_at,
        'escalated_by' => $nextStatus === CommunityPostStatus::Escalated ? $actor->id : $post->escalated_by,
    ])->save();

    $this->logAction($actor, $post, $action, $previousStatus, $nextStatus->value, $reason);

    return $post->fresh();
}
```

- [ ] **Step 4: Implement connector/admin authorization**

Rules:

- Admin with `community.moderate_any` can moderate any post.
- Connector moderator must belong to the same connector and have the required connector-local permission.
- `approve` requires `community.approve_posts`.
- `hide`, `restore`, `remove`, and `reject` require `community.manage_posts`.
- `lock` and `unlock` require `community.lock_threads`.
- `escalate` requires `community.escalate_to_platform`.

- [ ] **Step 5: Implement audit logging**

```php
CommunityModerationAction::query()->create([
    'connector_id' => $post->connector_id,
    'community_space_id' => $post->community_space_id,
    'actor_id' => $actor->id,
    'target_type' => $post::class,
    'target_id' => $post->id,
    'action_type' => $action->value,
    'previous_status' => $previousStatus,
    'new_status' => $nextStatus,
    'reason' => $reason,
    'metadata' => [
        'actor_role' => $actor->role,
        'platform_admin' => $actor->hasRole('admin'),
    ],
]);
```

- [ ] **Step 6: Run test to verify pass**

Run:

```bash
php artisan test tests/Feature/Community/CommunityModerationFlowTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Community/CommunityModerationService.php tests/Feature/Community/CommunityModerationFlowTest.php
git commit -m "feat: add community moderation actions"
```

---

### Task 8: Add Community Reports And Central Moderation Adapter

**Files:**
- Modify: `app/Enums/ModerationCaseSource.php`
- Create: `app/Services/Moderation/SourceAdapters/CommunityFeedModerationAdapter.php`
- Create: `app/Services/Community/CommunityReportService.php`
- Test: `tests/Feature/Community/CommunityReportModerationAdapterTest.php`

**Interfaces:**
- Produces: `ModerationCaseSource::CommunityFeed`
- Produces: `CommunityReportService::reportPost(User $reporter, CommunityPost $post, string $reasonCode, ?string $details): CommunityReport`
- Produces: `CommunityReportService::reportComment(User $reporter, CommunityComment $comment, string $reasonCode, ?string $details): CommunityReport`
- Produces: `CommunityFeedModerationAdapter::syncReport(CommunityReport $report): void`

- [ ] **Step 1: Write report adapter test**

Create `tests/Feature/Community/CommunityReportModerationAdapterTest.php`:

```php
<?php

namespace Tests\Feature\Community;

use App\Enums\ModerationCaseSource;
use App\Models\ModerationCase;
use App\Models\User;
use App\Services\Community\CommunityPostService;
use App\Services\Community\CommunityReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityReportModerationAdapterTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_post_report_creates_central_moderation_case_with_trace_metadata(): void
    {
        $author = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $author->assignRole('learner');
        $connector = $this->createVerifiedConnector($author);
        $reporter = $this->createAdultConnectorMember($connector, ['community.view_space']);
        $post = app(CommunityPostService::class)->create($author, $connector, [
            'post_type' => 'announcement',
            'title' => 'Resource',
            'body' => 'A reportable adult-facing resource.',
            'resource_url' => null,
        ]);

        $report = app(CommunityReportService::class)->reportPost($reporter, $post, 'unsafe_advice', 'This may need review.');

        $case = ModerationCase::query()
            ->where('case_source', ModerationCaseSource::CommunityFeed->value)
            ->where('content_type', 'community_report')
            ->where('content_id', $report->id)
            ->first();

        $this->assertNotNull($case);
        $this->assertSame($reporter->id, $case->reporter_id);
        $this->assertSame($author->id, $case->reported_user_id);
        $this->assertSame($post->id, data_get($case->metadata, 'source_trace.community_post_id'));
        $this->assertSame($connector->id, data_get($case->metadata, 'source_trace.connector_id'));
        $this->assertSame('unsafe_advice', data_get($case->metadata, 'source_trace.reason_code'));
    }
}
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Feature/Community/CommunityReportModerationAdapterTest.php
```

Expected: FAIL because `ModerationCaseSource::CommunityFeed` and report services do not exist.

- [ ] **Step 3: Add moderation source enum case**

In `ModerationCaseSource` add:

```php
case CommunityFeed = 'community_feed';
```

Add label:

```php
self::CommunityFeed => 'Community Feed',
```

- [ ] **Step 4: Implement `CommunityFeedModerationAdapter`**

Pattern after `ChatReportModerationAdapter`:

```php
public function syncReport(CommunityReport $report): void
{
    $report->loadMissing([
        'post:id,connector_id,community_space_id,author_id,title,post_type,status',
        'post.author:id,name,email,role',
        'post.connector:id,name,status',
        'comment:id,community_post_id,author_id,status,body',
        'comment.author:id,name,email,role',
        'reporter:id,name,email,role',
    ]);

    $reportedUserId = (int) ($report->comment?->author_id ?? $report->post?->author_id ?? $report->reported_user_id);

    $case = $this->moderationCaseIntakeService->upsertFromSource(
        source: ModerationCaseSource::CommunityFeed,
        contentType: 'community_report',
        contentId: (int) $report->id,
        reportedUserId: $reportedUserId,
        reporterId: (int) $report->reporter_id,
        metadata: [
            'source_trace' => [
                'source_record_id' => (int) $report->id,
                'community_post_id' => $report->community_post_id,
                'community_comment_id' => $report->community_comment_id,
                'connector_id' => $report->post?->connector_id,
                'community_space_id' => $report->post?->community_space_id,
                'reason_code' => $report->reason_code,
                'details' => $report->details,
                'report_status' => $report->status,
                'post_title' => $report->post?->title,
                'post_type' => $report->post?->post_type?->value ?? $report->post?->post_type,
                'post_status' => $report->post?->status?->value ?? $report->post?->status,
                'connector_name' => $report->post?->connector?->name,
                'reported_at' => optional($report->created_at)?->toDateTimeString(),
            ],
        ],
    );

    $report->forceFill(['moderation_case_id' => $case->id])->save();
}
```

- [ ] **Step 5: Implement `CommunityReportService`**

Rules:

- Reporter must be an adult who can view the connector space.
- Reports are idempotent per reporter and target while status is `open` or `under_review`.
- After saving, call `CommunityFeedModerationAdapter::syncReport($report)`.

Use:

```php
$report = CommunityReport::query()->updateOrCreate(
    [
        'community_post_id' => $post->id,
        'community_comment_id' => null,
        'reporter_id' => $reporter->id,
        'status' => CommunityReportStatus::Open->value,
    ],
    [
        'reported_user_id' => $post->author_id,
        'reason_code' => $reasonCode,
        'details' => $details,
    ],
);
```

- [ ] **Step 6: Run test to verify pass**

Run:

```bash
php artisan test tests/Feature/Community/CommunityReportModerationAdapterTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Enums/ModerationCaseSource.php app/Services/Moderation/SourceAdapters/CommunityFeedModerationAdapter.php app/Services/Community/CommunityReportService.php tests/Feature/Community/CommunityReportModerationAdapterTest.php
git commit -m "feat: connect community reports to moderation"
```

---

### Task 9: Add Community Notifications Without Guardian Alerts

**Files:**
- Create: `app/Notifications/Community/CommunityPostPendingReviewNotification.php`
- Create: `app/Notifications/Community/CommunityPostDecisionNotification.php`
- Create: `app/Notifications/Community/CommunityPostEscalatedNotification.php`
- Create: `app/Notifications/Community/CommunitySafetyEventNotification.php`
- Modify: `app/Services/Community/CommunityPostService.php`
- Modify: `app/Services/Community/CommunityModerationService.php`
- Modify: `app/Services/Community/CommunityFeedSettingsService.php`
- Test: `tests/Feature/Community/CommunityNotificationTest.php`

**Interfaces:**
- Produces: database notification types `community_post_pending_review`, `community_post_decision`, `community_post_escalated`, `community_safety_event`
- Produces: no notification type starting with `guardian_`, `child_community_`, or `minor_community_`

- [ ] **Step 1: Write notification tests**

Create `tests/Feature/Community/CommunityNotificationTest.php`:

```php
<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Notifications\Community\CommunityPostDecisionNotification;
use App\Notifications\Community\CommunityPostEscalatedNotification;
use App\Notifications\Community\CommunityPostPendingReviewNotification;
use App\Services\Community\CommunityModerationService;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityNotificationTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_pending_question_notifies_connector_moderators(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $moderator = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.approve_posts']);

        app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'moderated_question',
            'title' => 'Question',
            'body' => 'How can adults discuss consent education?',
            'resource_url' => null,
        ]);

        Notification::assertSentTo($moderator, CommunityPostPendingReviewNotification::class);
    }

    public function test_author_gets_decision_notification_and_guardians_do_not(): void
    {
        Notification::fake();
        $author = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $author->assignRole('learner');
        $parent = User::factory()->create(['role' => 'parent', 'birthdate' => now()->subYears(40)->toDateString()]);
        $parent->assignRole('parent');
        $connector = $this->createVerifiedConnector($author);
        $moderator = $this->createAdultConnectorMember($connector, ['community.view_space', 'community.approve_posts']);
        $post = app(CommunityPostService::class)->create($author, $connector, [
            'post_type' => 'moderated_question',
            'title' => 'Question',
            'body' => 'Review this adult-facing question.',
            'resource_url' => null,
        ]);

        app(CommunityModerationService::class)->approvePost($moderator, $post, 'Approved.');

        Notification::assertSentTo($author, CommunityPostDecisionNotification::class);
        Notification::assertNotSentTo($parent, CommunityPostDecisionNotification::class);
    }

    public function test_escalation_notifies_platform_admins(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $author = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $author->assignRole('learner');
        $connector = $this->createVerifiedConnector($author);
        $moderator = $this->createAdultConnectorMember($connector, ['community.escalate_to_platform']);
        $post = app(CommunityPostService::class)->create($author, $connector, [
            'post_type' => 'announcement',
            'title' => 'Post',
            'body' => 'Safe content.',
            'resource_url' => null,
        ]);

        app(CommunityModerationService::class)->escalatePost($moderator, $post, 'Platform review requested.');

        Notification::assertSentTo($admin, CommunityPostEscalatedNotification::class);
    }
}
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Feature/Community/CommunityNotificationTest.php
```

Expected: FAIL because notification classes and dispatches do not exist.

- [ ] **Step 3: Create notification classes**

Use database channel only:

```php
public function via(object $notifiable): array
{
    return ['database'];
}
```

Payload pattern:

```php
return [
    'type' => 'community_post_decision',
    'title' => 'Community post '.$this->decision,
    'message' => '"'.$post->title.'" was '.$this->decision.'.',
    'community_post_id' => $post->id,
    'connector_id' => $post->connector_id,
    'connector_name' => $post->connector?->name,
    'status' => $post->status?->value ?? $post->status,
    'action_url' => route('connector.community.show', [$post->connector, $post]),
    'severity' => $this->decision === 'approved' ? 'success' : 'info',
];
```

Admin escalation payload should point to:

```php
route('admin.community.show', $post)
```

- [ ] **Step 4: Notify connector moderators for pending review**

In `CommunityPostService::create`, when status is `pending_review`, notify active connector members whose role has `community.approve_posts` or `community.manage_posts`.

Query pattern:

```php
$connector->memberships()
    ->where('status', 'active')
    ->whereHas('role.permissions', fn ($query) => $query->whereIn('permission_key', ['community.approve_posts', 'community.manage_posts']))
    ->with('user')
    ->get()
    ->pluck('user')
    ->filter()
    ->each(fn (User $moderator) => $moderator->notify(new CommunityPostPendingReviewNotification($post)));
```

- [ ] **Step 5: Notify authors and admins from moderation service**

Rules:

- Approve/reject/hide/lock/restore/remove notifies the post author.
- Escalate notifies platform admins and the post author.
- Freeze/unfreeze in settings service notifies platform admins through `CommunitySafetyEventNotification`.
- Do not notify guardians in any Community Feed V1 service.

- [ ] **Step 6: Run test to verify pass**

Run:

```bash
php artisan test tests/Feature/Community/CommunityNotificationTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Notifications/Community app/Services/Community/CommunityPostService.php app/Services/Community/CommunityModerationService.php app/Services/Community/CommunityFeedSettingsService.php tests/Feature/Community/CommunityNotificationTest.php
git commit -m "feat: add community notifications"
```

---

### Task 10: Add HTTP Requests, Routes, And Controllers

**Files:**
- Create: `app/Http/Requests/Community/StoreCommunityPostRequest.php`
- Create: `app/Http/Requests/Community/UpdateCommunityPostRequest.php`
- Create: `app/Http/Requests/Community/StoreCommunityCommentRequest.php`
- Create: `app/Http/Requests/Community/StoreCommunityReportRequest.php`
- Create: `app/Http/Requests/Community/ModerateCommunityContentRequest.php`
- Create: `app/Http/Requests/Admin/UpdateCommunityFeedSettingsRequest.php`
- Create: `app/Http/Controllers/Connector/CommunityFeedController.php`
- Create: `app/Http/Controllers/Connector/CommunityCommentController.php`
- Create: `app/Http/Controllers/Connector/CommunityReactionController.php`
- Create: `app/Http/Controllers/Connector/CommunityReportController.php`
- Create: `app/Http/Controllers/Connector/CommunityModerationController.php`
- Create: `app/Http/Controllers/Admin/CommunityFeedController.php`
- Create: `app/Http/Controllers/Admin/CommunityModerationController.php`
- Create: `app/Http/Controllers/Admin/CommunityFeedSettingsController.php`
- Modify: `routes/connector.php`
- Modify: `routes/admin.php`
- Test: expand `tests/Feature/Community/ConnectorCommunityPostFlowTest.php`
- Test: expand `tests/Feature/Community/CommunityInteractionSafetyTest.php`
- Test: expand `tests/Feature/Community/AdminCommunityFeedControlTest.php`

**Interfaces:**
- Produces connector routes:
  - `connector.community.index`
  - `connector.community.create`
  - `connector.community.store`
  - `connector.community.show`
  - `connector.community.edit`
  - `connector.community.update`
  - `connector.community.comments.store`
  - `connector.community.reactions.store`
  - `connector.community.reactions.destroy`
  - `connector.community.reports.store`
  - `connector.community.moderation.approve`
  - `connector.community.moderation.reject`
  - `connector.community.moderation.hide`
  - `connector.community.moderation.lock`
  - `connector.community.moderation.unlock`
  - `connector.community.moderation.restore`
  - `connector.community.moderation.remove`
  - `connector.community.moderation.escalate`
- Produces admin routes:
  - `admin.community.index`
  - `admin.community.show`
  - `admin.community.settings`
  - `admin.community.settings.update`
  - `admin.community.freeze`
  - `admin.community.unfreeze`
  - `admin.community.moderation.approve`
  - `admin.community.moderation.hide`
  - `admin.community.moderation.lock`
  - `admin.community.moderation.unlock`
  - `admin.community.moderation.restore`
  - `admin.community.moderation.remove`
  - `admin.community.moderation.escalate`

- [ ] **Step 1: Write route/controller tests for connector routes**

Add to `ConnectorCommunityPostFlowTest`:

```php
public function test_connector_post_routes_enforce_permissions_and_minor_exclusion(): void
{
    $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
    $owner->assignRole('learner');
    $connector = $this->createVerifiedConnector($owner);
    $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);
    $minor = $this->createMinorLearner(15);

    $this->actingAs($viewer)
        ->get(route('connector.community.create', $connector))
        ->assertForbidden();

    $this->actingAs($minor)
        ->post(route('connector.community.store', $connector), [
            'post_type' => 'announcement',
            'title' => 'Minor post',
            'body' => 'Denied.',
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('connector.community.store', $connector), [
            'post_type' => 'announcement',
            'title' => 'Adult announcement',
            'body' => 'A safe adult-facing announcement.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('community_posts', [
        'connector_id' => $connector->id,
        'title' => 'Adult announcement',
        'status' => 'published',
    ]);
}
```

- [ ] **Step 2: Write admin control tests**

Create `tests/Feature/Community/AdminCommunityFeedControlTest.php`:

```php
<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Services\Community\CommunityFeedSettingsService;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class AdminCommunityFeedControlTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_admin_can_view_any_post_and_freeze_feed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'title' => 'Admin-visible post',
            'body' => 'Safe content.',
            'resource_url' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.show', $post))
            ->assertOk()
            ->assertSee('Admin-visible post');

        $this->actingAs($admin)
            ->post(route('admin.community.freeze'), ['reason' => 'Safety incident.'])
            ->assertRedirect();

        $this->assertTrue(app(CommunityFeedSettingsService::class)->isGloballyFrozen());
    }
}
```

- [ ] **Step 3: Run route/controller tests to verify failure**

Run:

```bash
php artisan test tests/Feature/Community/ConnectorCommunityPostFlowTest.php tests/Feature/Community/CommunityInteractionSafetyTest.php tests/Feature/Community/AdminCommunityFeedControlTest.php
```

Expected: FAIL because routes and controllers do not exist.

- [ ] **Step 4: Add request validation**

`StoreCommunityPostRequest` and `UpdateCommunityPostRequest` rules:

```php
return [
    'post_type' => ['required', 'string', Rule::in(\App\Enums\CommunityPostType::values())],
    'title' => ['required', 'string', 'max:160'],
    'body' => ['required', 'string', 'max:8000'],
    'resource_url' => ['nullable', 'url', 'max:2048'],
];
```

`StoreCommunityCommentRequest`:

```php
return ['body' => ['required', 'string', 'max:2000']];
```

`StoreCommunityReportRequest`:

```php
return [
    'reason_code' => ['required', 'string', Rule::in(array_keys(config('community_feed.report_reasons')))],
    'details' => ['nullable', 'string', 'max:2000'],
    'target_type' => ['required', 'string', Rule::in(['post', 'comment'])],
    'target_id' => ['required', 'integer', 'min:1'],
];
```

`ModerateCommunityContentRequest`:

```php
return ['reason' => ['required', 'string', 'max:1000']];
```

`UpdateCommunityFeedSettingsRequest`:

```php
return [
    'reason' => ['required', 'string', 'max:1000'],
    'suspended_connector_visibility' => ['nullable', 'string', Rule::in(['read_only', 'hidden'])],
];
```

- [ ] **Step 5: Add connector routes**

In `routes/connector.php`, import new controllers and add inside the existing `auth`, `verified` group:

```php
Route::get('/connector/{connector}/community', [CommunityFeedController::class, 'index'])->name('connector.community.index');
Route::get('/connector/{connector}/community/create', [CommunityFeedController::class, 'create'])->name('connector.community.create');
Route::post('/connector/{connector}/community', [CommunityFeedController::class, 'store'])->name('connector.community.store');
Route::get('/connector/{connector}/community/{post}', [CommunityFeedController::class, 'show'])->name('connector.community.show');
Route::get('/connector/{connector}/community/{post}/edit', [CommunityFeedController::class, 'edit'])->name('connector.community.edit');
Route::put('/connector/{connector}/community/{post}', [CommunityFeedController::class, 'update'])->name('connector.community.update');
Route::post('/connector/{connector}/community/{post}/comments', [CommunityCommentController::class, 'store'])->name('connector.community.comments.store');
Route::post('/connector/{connector}/community/{post}/reactions', [CommunityReactionController::class, 'store'])->name('connector.community.reactions.store');
Route::delete('/connector/{connector}/community/{post}/reactions', [CommunityReactionController::class, 'destroy'])->name('connector.community.reactions.destroy');
Route::post('/connector/{connector}/community/{post}/reports', [CommunityReportController::class, 'store'])->name('connector.community.reports.store');
Route::post('/connector/{connector}/community/{post}/moderation/approve', [CommunityModerationController::class, 'approve'])->name('connector.community.moderation.approve');
Route::post('/connector/{connector}/community/{post}/moderation/reject', [CommunityModerationController::class, 'reject'])->name('connector.community.moderation.reject');
Route::post('/connector/{connector}/community/{post}/moderation/hide', [CommunityModerationController::class, 'hide'])->name('connector.community.moderation.hide');
Route::post('/connector/{connector}/community/{post}/moderation/lock', [CommunityModerationController::class, 'lock'])->name('connector.community.moderation.lock');
Route::post('/connector/{connector}/community/{post}/moderation/unlock', [CommunityModerationController::class, 'unlock'])->name('connector.community.moderation.unlock');
Route::post('/connector/{connector}/community/{post}/moderation/restore', [CommunityModerationController::class, 'restore'])->name('connector.community.moderation.restore');
Route::post('/connector/{connector}/community/{post}/moderation/remove', [CommunityModerationController::class, 'remove'])->name('connector.community.moderation.remove');
Route::post('/connector/{connector}/community/{post}/moderation/escalate', [CommunityModerationController::class, 'escalate'])->name('connector.community.moderation.escalate');
```

- [ ] **Step 6: Add admin routes**

In `routes/admin.php`, import controllers through the existing `Admin` namespace import and add:

```php
Route::prefix('community')->name('community.')->group(function () {
    Route::get('/', [Admin\CommunityFeedController::class, 'index'])->name('index');
    Route::get('/settings', [Admin\CommunityFeedSettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [Admin\CommunityFeedSettingsController::class, 'update'])->name('settings.update');
    Route::post('/freeze', [Admin\CommunityFeedSettingsController::class, 'freeze'])->name('freeze');
    Route::post('/unfreeze', [Admin\CommunityFeedSettingsController::class, 'unfreeze'])->name('unfreeze');
    Route::get('/{post}', [Admin\CommunityFeedController::class, 'show'])->name('show');
    Route::post('/{post}/moderation/approve', [Admin\CommunityModerationController::class, 'approve'])->name('moderation.approve');
    Route::post('/{post}/moderation/hide', [Admin\CommunityModerationController::class, 'hide'])->name('moderation.hide');
    Route::post('/{post}/moderation/lock', [Admin\CommunityModerationController::class, 'lock'])->name('moderation.lock');
    Route::post('/{post}/moderation/unlock', [Admin\CommunityModerationController::class, 'unlock'])->name('moderation.unlock');
    Route::post('/{post}/moderation/restore', [Admin\CommunityModerationController::class, 'restore'])->name('moderation.restore');
    Route::post('/{post}/moderation/remove', [Admin\CommunityModerationController::class, 'remove'])->name('moderation.remove');
    Route::post('/{post}/moderation/escalate', [Admin\CommunityModerationController::class, 'escalate'])->name('moderation.escalate');
});
```

- [ ] **Step 7: Implement connector controllers**

Controller methods should follow existing connector seminar style:

```php
public function index(Request $request, Connector $connector): View
{
    $this->access->abortUnlessCanViewSpace($request->user(), $connector);

    return view('connectors.community.index', [
        'connector' => $connector,
        'posts' => CommunityPost::query()
            ->where('connector_id', $connector->id)
            ->whereIn('status', ['published', 'locked', 'pending_review', 'hidden', 'escalated'])
            ->with(['author', 'comments', 'reactions'])
            ->latest()
            ->paginate(15),
        'canCreate' => $this->access->canCreatePost($request->user(), $connector),
        'canModerate' => $this->access->canModerateSpace($request->user(), $connector),
    ]);
}
```

Every route containing `{connector}` and `{post}` must verify:

```php
abort_unless((int) $post->connector_id === (int) $connector->id, 404);
```

- [ ] **Step 8: Implement admin controllers**

`Admin\CommunityFeedController::index` should filter by status, connector, and search:

```php
$posts = CommunityPost::query()
    ->with(['connector', 'author'])
    ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
    ->when($request->filled('connector_id'), fn ($query) => $query->where('connector_id', $request->integer('connector_id')))
    ->when($request->filled('search'), function ($query) use ($request): void {
        $search = $request->string('search')->toString();
        $query->where(fn ($inner) => $inner
            ->where('title', 'like', "%{$search}%")
            ->orWhere('body', 'like', "%{$search}%"));
    })
    ->latest()
    ->paginate(20)
    ->withQueryString();
```

- [ ] **Step 9: Run route/controller tests to verify pass**

Run:

```bash
php artisan test tests/Feature/Community/ConnectorCommunityPostFlowTest.php tests/Feature/Community/CommunityInteractionSafetyTest.php tests/Feature/Community/AdminCommunityFeedControlTest.php
```

Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Requests/Community app/Http/Requests/Admin/UpdateCommunityFeedSettingsRequest.php app/Http/Controllers/Connector/Community*.php app/Http/Controllers/Admin/Community*.php routes/connector.php routes/admin.php tests/Feature/Community/ConnectorCommunityPostFlowTest.php tests/Feature/Community/CommunityInteractionSafetyTest.php tests/Feature/Community/AdminCommunityFeedControlTest.php
git commit -m "feat: add community feed routes"
```

---

### Task 11: Add Connector And Admin Blade UI

**Files:**
- Create: `resources/views/connectors/community/index.blade.php`
- Create: `resources/views/connectors/community/create.blade.php`
- Create: `resources/views/connectors/community/edit.blade.php`
- Create: `resources/views/connectors/community/show.blade.php`
- Create: `resources/views/admin/community/index.blade.php`
- Create: `resources/views/admin/community/show.blade.php`
- Create: `resources/views/admin/community/settings.blade.php`
- Modify: `resources/views/layouts/connector-app.blade.php`
- Modify: `resources/views/layouts/admin.blade.php`
- Test: `tests/Feature/Community/CommunityUiSmokeTest.php`

**Interfaces:**
- Consumes: connector route names from Task 10
- Consumes: admin route names from Task 10
- Produces: connector sidebar item labeled `Community`
- Produces: admin moderation sidebar item labeled `Community Feed`

- [ ] **Step 1: Write UI smoke tests**

Create `tests/Feature/Community/CommunityUiSmokeTest.php`:

```php
<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityUiSmokeTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_connector_community_pages_render_without_minor_controls(): void
    {
        $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'announcement',
            'title' => 'Adult resource',
            'body' => 'Safe content for adults.',
            'resource_url' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('connector.community.index', $connector))
            ->assertOk()
            ->assertSee('Community')
            ->assertSee('Adult resource')
            ->assertDontSee('Guardian alert');

        $this->actingAs($owner)
            ->get(route('connector.community.show', [$connector, $post]))
            ->assertOk()
            ->assertSee('Adult resource')
            ->assertDontSee('Reply privately');
    }

    public function test_admin_community_pages_render_freeze_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.community.index'))
            ->assertOk()
            ->assertSee('Community Feed');

        $this->actingAs($admin)
            ->get(route('admin.community.settings'))
            ->assertOk()
            ->assertSee('Emergency Freeze');
    }
}
```

- [ ] **Step 2: Run UI smoke test to verify failure**

Run:

```bash
php artisan test tests/Feature/Community/CommunityUiSmokeTest.php
```

Expected: FAIL because views and nav entries do not exist.

- [ ] **Step 3: Add connector index/create/edit/show views**

Use `@extends('layouts.connector-app')`, `@section('title', 'Community')`, and `@section('page-title', 'Community')`.

Connector index must show:

- Page title `Community`
- Create button only when `$canCreate`
- Status badges for `pending_review`, `published`, `hidden`, `locked`, `escalated`
- Empty state text `No community posts yet.`
- No text that describes keyboard shortcuts or hidden features

Connector show must show:

- Post title, type, status, author, connector
- Body rendered as escaped text with line breaks unless a sanitizer is introduced
- Resource URL only when present
- Flat comments list
- Reaction buttons for `learned`, `helpful`, `question`, `support`
- Report form
- Moderation buttons only when `$canModerate`
- No nested reply controls
- No DM/contact prompt

- [ ] **Step 4: Add admin index/show/settings views**

Use `@extends('layouts.admin')`.

Admin index must show:

- Filters for status, connector, and search
- Pending/escalated counts
- Table with post title, connector, author, type, status, and action link
- Emergency freeze link or form

Admin show must show:

- Full post
- Versions
- Reports
- Moderation action history
- Hidden/removed content for authorized admin review
- Approve, hide, lock, restore, remove, escalate actions

Admin settings must show:

- Global freeze form requiring `reason`
- Global unfreeze form
- Suspended connector visibility selector with `read_only` and `hidden`

- [ ] **Step 5: Add connector navigation item**

In `resources/views/layouts/connector-app.blade.php`, add an item after Seminars:

```php
['Community', 'connector.community.index', 'community.view_space', 'M7.5 8.25h9m-9 3.75h6m-8.25 7.5 2.25-2.25H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v9A2.25 2.25 0 0 0 6 17.25h.75v2.25Z'],
```

- [ ] **Step 6: Add admin navigation item**

In `resources/views/layouts/admin.blade.php`, under the Moderation section, add a link to `route('admin.community.index')` labeled `Community Feed`. Use the same active-state pattern as `admin.seminars.*`, with `request()->routeIs('admin.community.*')`.

- [ ] **Step 7: Run UI smoke test to verify pass**

Run:

```bash
php artisan test tests/Feature/Community/CommunityUiSmokeTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/views/connectors/community resources/views/admin/community resources/views/layouts/connector-app.blade.php resources/views/layouts/admin.blade.php tests/Feature/Community/CommunityUiSmokeTest.php
git commit -m "feat: add community feed ui"
```

---

### Task 12: Add Admin Counts, Rate Limits, And Final Regression Coverage

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `bootstrap/app.php` or route middleware definitions if named rate limit middleware is required
- Modify: `routes/connector.php`
- Modify: `routes/admin.php`
- Modify: `tests/Feature/Community/AdminCommunityFeedControlTest.php`
- Modify: `tests/Feature/Community/CommunityInteractionSafetyTest.php`
- Modify: `tests/Feature/Community/CommunityNotificationTest.php`

**Interfaces:**
- Produces admin view-composer counts:
  - `pending_community_posts`
  - `escalated_community_posts`
  - `open_community_reports`
- Produces route rate limits for community posts, comments, and reports
- Produces final regression command set

- [ ] **Step 1: Write final safety regression tests**

Add to `AdminCommunityFeedControlTest`:

```php
public function test_global_freeze_blocks_connector_post_route_but_keeps_admin_moderation_available(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');
    $owner = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
    $owner->assignRole('learner');
    $connector = $this->createVerifiedConnector($owner);
    $post = app(CommunityPostService::class)->create($owner, $connector, [
        'post_type' => 'announcement',
        'title' => 'Before freeze',
        'body' => 'Safe content.',
        'resource_url' => null,
    ]);

    $this->actingAs($admin)->post(route('admin.community.freeze'), ['reason' => 'Safety incident.']);

    $this->actingAs($owner)
        ->post(route('connector.community.store', $connector), [
            'post_type' => 'announcement',
            'title' => 'After freeze',
            'body' => 'This should be blocked.',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.community.moderation.hide', $post), ['reason' => 'Admin can still moderate.'])
        ->assertRedirect();
}
```

Add to `CommunityNotificationTest`:

```php
public function test_v1_does_not_send_guardian_or_child_feed_notifications(): void
{
    Notification::fake();
    $author = User::factory()->create(['role' => 'learner', 'birthdate' => now()->subYears(30)->toDateString()]);
    $author->assignRole('learner');
    $connector = $this->createVerifiedConnector($author);

    app(CommunityPostService::class)->create($author, $connector, [
        'post_type' => 'announcement',
        'title' => 'Adult-only post',
        'body' => 'No child workflow notification should be created.',
        'resource_url' => null,
    ]);

    Notification::assertNothingSentTo($this->createMinorLearner(12));
}
```

- [ ] **Step 2: Run final safety tests to verify current failure or partial failure**

Run:

```bash
php artisan test tests/Feature/Community/AdminCommunityFeedControlTest.php tests/Feature/Community/CommunityNotificationTest.php
```

Expected: FAIL if counts, rate limits, freeze route behavior, or notification absence is incomplete.

- [ ] **Step 3: Add admin moderation counts**

In `AppServiceProvider` admin view composer, add:

```php
'pending_community_posts' => \App\Models\CommunityPost::query()->where('status', 'pending_review')->count(),
'escalated_community_posts' => \App\Models\CommunityPost::query()->where('status', 'escalated')->count(),
'open_community_reports' => \App\Models\CommunityReport::query()->whereIn('status', ['open', 'under_review'])->count(),
```

Use fully qualified class names inside the array to avoid import churn if the file already has many imports.

- [ ] **Step 4: Add rate limiters**

In `AppServiceProvider::boot()`, add:

```php
RateLimiter::for('community-posts', function (Request $request) {
    return Limit::perMinute((int) config('community_feed.rate_limits.posts_per_minute', 3))
        ->by((string) ($request->user()?->id ?? $request->ip()));
});

RateLimiter::for('community-comments', function (Request $request) {
    return Limit::perMinute((int) config('community_feed.rate_limits.comments_per_minute', 6))
        ->by((string) ($request->user()?->id ?? $request->ip()));
});

RateLimiter::for('community-reports', function (Request $request) {
    return Limit::perMinute((int) config('community_feed.rate_limits.reports_per_minute', 6))
        ->by((string) ($request->user()?->id ?? $request->ip()));
});
```

- [ ] **Step 5: Attach rate limits to write routes**

In `routes/connector.php`, attach:

- `->middleware('throttle:community-posts')` to `connector.community.store` and `connector.community.update`
- `->middleware('throttle:community-comments')` to `connector.community.comments.store`
- `->middleware('throttle:community-reports')` to `connector.community.reports.store`

- [ ] **Step 6: Run full community test suite**

Run:

```bash
php artisan test tests/Feature/Community tests/Unit/Services/Community
```

Expected: PASS.

- [ ] **Step 7: Run connector, RBAC, moderation, and notification regression tests**

Run:

```bash
php artisan test tests/Feature/Connectors tests/Unit/Services/Connectors tests/Feature/Rbac tests/Feature/Moderation tests/Feature/Admin/Moderation tests/Feature/Notifications
```

Expected: PASS.

- [ ] **Step 8: Run build**

Run:

```bash
npm.cmd run build
```

Expected: PASS. If build artifacts change, include only files produced by this build and mention any pre-existing build dirt in the final implementation summary.

- [ ] **Step 9: Commit**

```bash
git add app/Providers/AppServiceProvider.php routes/connector.php routes/admin.php tests/Feature/Community/AdminCommunityFeedControlTest.php tests/Feature/Community/CommunityInteractionSafetyTest.php tests/Feature/Community/CommunityNotificationTest.php
git commit -m "test: cover community feed safety regressions"
```

---

## Final Verification Checklist

- [ ] `php artisan test tests/Feature/Community tests/Unit/Services/Community`
- [ ] `php artisan test tests/Feature/Connectors tests/Unit/Services/Connectors`
- [ ] `php artisan test tests/Feature/Rbac`
- [ ] `php artisan test tests/Feature/Moderation tests/Feature/Admin/Moderation`
- [ ] `php artisan test tests/Feature/Notifications`
- [ ] `npm.cmd run build`
- [ ] Manual browser check as admin: community index, post detail, settings, freeze, unfreeze.
- [ ] Manual browser check as connector owner: community index, create post, moderated question pending state, post detail, moderation actions.
- [ ] Manual browser check as minor learner: direct connector community routes return 403 and no feed controls are visible.

## Notes For Implementers

- Use `Tests\DatabaseTestCase` for database-backed feature tests when RBAC seeding is needed.
- Keep community reports dual-written: local `community_reports` record plus centralized `moderation_cases` record.
- Keep route-level connector ownership checks explicit with 404 for cross-connector post access.
- Keep minor exclusion in services and policies so hidden UI cannot be bypassed.
- Keep guardian notifications absent from V1 feed workflows.
- Keep link rendering escaped and safe; do not render user-submitted HTML as trusted HTML.
