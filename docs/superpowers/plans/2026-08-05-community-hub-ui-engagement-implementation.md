# Community Hub UI And Engagement Implementation Plan

> Current source of truth for Community Hub/feed implementation. Use this plan
> for active work instead of the older Community Feed V1 plan. The V1 plan is
> retained only as the backend safety baseline.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Maintain the current Connector Community Hub/feed as a polished,
moderation-first workspace that matches the existing module UI and supports
announcements, seminar/event posts, resources, moderated Q&A, and safe
discussion prompts.

**Architecture:** Keep the existing Community Feed V1 backend safety architecture
and route/model names stable while treating Community Hub as the current
user-facing feed. Add only the smallest backend changes needed for new post
types, featured posts, seminar-aware event posts, and official Q&A answers. Use
reusable Blade components so connector and admin pages stay visually consistent
with seminars, modules, connector management, and moderation screens.

**Tech Stack:** Laravel 12, PHP 8.2, Eloquent, Blade, Alpine.js, Tailwind CSS, Spatie Laravel Permission, Laravel notifications, PHPUnit/Laravel feature and unit tests, Vite.

## Global Constraints

- User-facing copy should say `Community Hub` on connector-facing pages.
- Internal route/model names may remain `community` and `CommunityFeed` to avoid unnecessary churn.
- Community Hub remains connector-scoped and adult-facing.
- Minors cannot create posts, comment, react, or receive replies in this version.
- No private messaging, direct replies, nested replies, DM-like feature, global feed, follower counts, or viral sharing controls.
- Comments remain flat.
- Reactions remain education-focused: `learned`, `helpful`, `question`, `support`.
- Reports must continue to feed the centralized moderation flow through the community moderation adapter.
- Hidden, removed, edited, and escalated content must remain available to authorized moderators and admins for audit.
- UI must match existing connector, seminar, admin, and moderation module patterns.
- Use finite literal Tailwind classes so Vite can detect styles.
- Do not render user-submitted HTML as trusted HTML.
- Avoid touching unrelated dirty files or generated build assets unless the task explicitly runs the frontend build.

---

## File Structure

Modify:

- `config/community_feed.php`: add `event` and `discussion_prompt` post types, tab labels, featured-post defaults, and optional seminar link settings.
- `app/Enums/CommunityPostType.php`: add `Event` and `DiscussionPrompt`.
- `app/Models/CommunityPost.php`: expose `isFeatured()`, `isEvent()`, `isQuestion()`, and optional seminar relationship if a seminar link column exists.
- `database/migrations/*community_feed*`: if not yet migrated in the target environment, add `featured_at`, `featured_by`, `seminar_id`, and `official_answer_comment_id` to `community_posts`; otherwise create a new additive migration.
- `app/Services/Community/CommunityPostService.php`: accept new post types and seminar linkage.
- `app/Services/Community/CommunityModerationService.php`: feature/unfeature posts and mark official Q&A answer when permitted.
- `app/Http/Requests/Community/StoreCommunityPostRequest.php`: validate new post types and optional `seminar_id`.
- `app/Http/Requests/Community/UpdateCommunityPostRequest.php`: validate new post types and optional `seminar_id`.
- `app/Http/Requests/Community/ModerateCommunityContentRequest.php`: validate feature/unfeature and official-answer actions.
- `app/Http/Controllers/Connector/CommunityFeedController.php`: provide hub tab filters, featured posts, upcoming seminars, and type-specific counts.
- `app/Http/Controllers/Connector/CommunityModerationController.php`: add feature/unfeature and official-answer actions.
- `app/Http/Controllers/Admin/CommunityFeedController.php`: add hub type filters and featured/escalated/pending counts.
- `routes/connector.php`: add feature/unfeature and official answer routes if missing.
- `routes/admin.php`: add admin feature/unfeature routes if missing.
- `resources/views/connectors/community/index.blade.php`: rename and restructure as Community Hub.
- `resources/views/connectors/community/show.blade.php`: show post type-specific layouts, seminar metadata, flat comments, official answer state, and moderation tools.
- `resources/views/connectors/community/create.blade.php`: match seminar form visual style and support new post types.
- `resources/views/connectors/community/edit.blade.php`: match create view and preserve moderation state messaging.
- `resources/views/connectors/community/moderation/index.blade.php`: match admin/connector table patterns for pending, reported, and escalated content.
- `resources/views/admin/community/index.blade.php`: match admin moderation/table patterns and add hub filters.
- `resources/views/admin/community/show.blade.php`: show audit details, versions, reports, comments, and moderation controls.
- `resources/views/components/community/feed-sidebar.blade.php`: add hub tabs and mobile-friendly layout.
- `resources/views/components/community/post-card.blade.php`: add event/resource/Q&A/discussion presentation while keeping compact module-style cards.
- `resources/views/components/community/post-composer.blade.php`: support hub post types without social-media copy.
- `resources/views/components/community/post-type-badge.blade.php`: add event and discussion prompt badges.
- `resources/views/components/community/right-panel.blade.php`: show upcoming seminars, rules, and moderation counts.
- `resources/views/components/community/safety-reminder.blade.php`: keep adult-facing safety guidance concise.
- `resources/views/layouts/connector-app.blade.php`: label connector navigation as `Community Hub`.
- `resources/views/layouts/admin.blade.php`: keep admin nav label `Community Hub` or `Community Moderation`.
- `tests/Feature/Community/CommunityHubTaxonomyTest.php`: cover new post types, featured posts, and seminar linkage.
- `tests/Feature/Community/CommunityHubUiSmokeTest.php`: cover connector/admin UI rendering and minor exclusion.
- `tests/Feature/Community/CommunityHubModerationUiTest.php`: cover feature/unfeature and official answer permissions.

---

### Task 1: Add Hub Taxonomy And Additive Data Fields

**Files:**
- Modify: `config/community_feed.php`
- Modify: `app/Enums/CommunityPostType.php`
- Modify: `app/Models/CommunityPost.php`
- Create: `database/migrations/2026_08_05_000001_add_hub_fields_to_community_posts.php`
- Test: `tests/Feature/Community/CommunityHubTaxonomyTest.php`

**Interfaces:**
- Produces: `CommunityPostType::Event`
- Produces: `CommunityPostType::DiscussionPrompt`
- Produces: `CommunityPost::isFeatured(): bool`
- Produces: `CommunityPost::isEvent(): bool`
- Produces: `CommunityPost::isQuestion(): bool`
- Produces: nullable `community_posts.featured_at`
- Produces: nullable `community_posts.featured_by`
- Produces: nullable `community_posts.seminar_id`
- Produces: nullable `community_posts.official_answer_comment_id`

- [ ] **Step 1: Write taxonomy test**

Create `tests/Feature/Community/CommunityHubTaxonomyTest.php`:

```php
<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityPostType;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityHubTaxonomyTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_hub_post_types_and_fields_are_available(): void
    {
        $this->assertContains('event', CommunityPostType::values());
        $this->assertContains('discussion_prompt', CommunityPostType::values());
        $this->assertTrue(Schema::hasColumns('community_posts', [
            'featured_at',
            'featured_by',
            'seminar_id',
            'official_answer_comment_id',
        ]));
    }

    public function test_post_helpers_identify_featured_event_and_question_posts(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $post = CommunityPost::query()->create([
            'community_space_id' => $connector->communitySpaces()->firstOrCreate([
                'connector_id' => $connector->id,
            ], [
                'name' => $connector->name.' Community',
                'status' => 'active',
            ])->id,
            'connector_id' => $connector->id,
            'author_id' => $owner->id,
            'post_type' => 'event',
            'status' => 'published',
            'title' => 'Community health seminar',
            'body' => 'Join the seminar for verified adult members.',
            'prescreen_decision' => 'allow',
            'featured_at' => now(),
            'featured_by' => $owner->id,
        ]);

        $this->assertTrue($post->isFeatured());
        $this->assertTrue($post->isEvent());
        $this->assertFalse($post->isQuestion());
    }
}
```

- [ ] **Step 2: Run taxonomy test to verify failure**

Run:

```bash
php artisan test tests/Feature/Community/CommunityHubTaxonomyTest.php
```

Expected: FAIL because `event`, `discussion_prompt`, and the hub fields are missing or incomplete.

- [ ] **Step 3: Update config and enum**

In `config/community_feed.php`, make `post_types` include:

```php
'post_types' => [
    'announcement' => 'Announcement',
    'event' => 'Event',
    'resource' => 'Educational Resource',
    'moderated_question' => 'Q&A',
    'discussion_prompt' => 'Discussion',
],
```

In `app/Enums/CommunityPostType.php`, define:

```php
case Event = 'event';
case DiscussionPrompt = 'discussion_prompt';
```

Keep the existing `label()` and `values()` methods unchanged.

- [ ] **Step 4: Add additive migration**

Create `database/migrations/2026_08_05_000001_add_hub_fields_to_community_posts.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('community_posts', 'featured_at')) {
                $table->timestamp('featured_at')->nullable()->after('published_by');
            }

            if (! Schema::hasColumn('community_posts', 'featured_by')) {
                $table->foreignId('featured_by')->nullable()->after('featured_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('community_posts', 'seminar_id')) {
                $table->foreignId('seminar_id')->nullable()->after('post_type')->constrained('seminars')->nullOnDelete();
            }

            if (! Schema::hasColumn('community_posts', 'official_answer_comment_id')) {
                $table->foreignId('official_answer_comment_id')->nullable()->after('moderation_case_id')->constrained('community_comments')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table): void {
            if (Schema::hasColumn('community_posts', 'official_answer_comment_id')) {
                $table->dropConstrainedForeignId('official_answer_comment_id');
            }

            if (Schema::hasColumn('community_posts', 'seminar_id')) {
                $table->dropConstrainedForeignId('seminar_id');
            }

            if (Schema::hasColumn('community_posts', 'featured_by')) {
                $table->dropConstrainedForeignId('featured_by');
            }

            if (Schema::hasColumn('community_posts', 'featured_at')) {
                $table->dropColumn('featured_at');
            }
        });
    }
};
```

- [ ] **Step 5: Add model helpers**

In `app/Models/CommunityPost.php`, add casts and helpers:

```php
protected $casts = [
    'prescreen_flags' => 'array',
    'published_at' => 'datetime',
    'featured_at' => 'datetime',
    'locked_at' => 'datetime',
    'hidden_at' => 'datetime',
    'removed_at' => 'datetime',
    'escalated_at' => 'datetime',
];

public function isFeatured(): bool
{
    return $this->featured_at !== null;
}

public function isEvent(): bool
{
    return (string) $this->post_type === 'event';
}

public function isQuestion(): bool
{
    return (string) $this->post_type === 'moderated_question';
}
```

If `CommunityPost` already has `$casts`, merge these entries without removing existing casts.

- [ ] **Step 6: Run taxonomy test to verify pass**

Run:

```bash
php artisan test tests/Feature/Community/CommunityHubTaxonomyTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add config/community_feed.php app/Enums/CommunityPostType.php app/Models/CommunityPost.php database/migrations/2026_08_05_000001_add_hub_fields_to_community_posts.php tests/Feature/Community/CommunityHubTaxonomyTest.php
git commit -m "feat: add community hub taxonomy"
```

---

### Task 2: Add Hub Service And Request Support

**Files:**
- Modify: `app/Services/Community/CommunityPostService.php`
- Modify: `app/Services/Community/CommunityModerationService.php`
- Modify: `app/Http/Requests/Community/StoreCommunityPostRequest.php`
- Modify: `app/Http/Requests/Community/UpdateCommunityPostRequest.php`
- Modify: `app/Http/Requests/Community/ModerateCommunityContentRequest.php`
- Test: `tests/Feature/Community/CommunityHubModerationUiTest.php`

**Interfaces:**
- Produces: `CommunityModerationService::featurePost(User $actor, CommunityPost $post, string $reason): CommunityPost`
- Produces: `CommunityModerationService::unfeaturePost(User $actor, CommunityPost $post, string $reason): CommunityPost`
- Produces: `CommunityModerationService::markOfficialAnswer(User $actor, CommunityPost $post, CommunityComment $comment, string $reason): CommunityPost`
- Consumes: `CommunityPostType::values()`

- [ ] **Step 1: Write service behavior test**

Create `tests/Feature/Community/CommunityHubModerationUiTest.php` with this first test:

```php
<?php

namespace Tests\Feature\Community;

use App\Models\CommunityComment;
use App\Models\User;
use App\Services\Community\CommunityModerationService;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityHubModerationUiTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_connector_moderator_can_feature_post_and_mark_official_answer(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        $post = app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'moderated_question',
            'title' => 'How should we prepare for the seminar?',
            'body' => 'What should adult members review before attending?',
            'resource_url' => null,
        ]);

        $comment = CommunityComment::query()->create([
            'community_post_id' => $post->id,
            'author_id' => $owner->id,
            'body' => 'Please review the consent basics module before the session.',
            'status' => 'visible',
            'prescreen_decision' => 'allow',
        ]);

        $moderation = app(CommunityModerationService::class);
        $featured = $moderation->featurePost($owner, $post, 'Important question for this week.');
        $answered = $moderation->markOfficialAnswer($owner, $featured, $comment, 'Connector-approved answer.');

        $this->assertTrue($featured->fresh()->isFeatured());
        $this->assertSame($comment->id, $answered->fresh()->official_answer_comment_id);
    }
}
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Feature/Community/CommunityHubModerationUiTest.php
```

Expected: FAIL because feature and official-answer service methods are missing.

- [ ] **Step 3: Validate new post types in requests**

In `StoreCommunityPostRequest` and `UpdateCommunityPostRequest`, ensure `post_type` uses:

```php
Rule::in(CommunityPostType::values())
```

Add optional seminar validation:

```php
'seminar_id' => ['nullable', 'integer', 'exists:seminars,id'],
```

In controller or service code, reject seminar IDs that do not belong to the same connector as the post.

- [ ] **Step 4: Persist seminar linkage**

In `CommunityPostService::create()` and `CommunityPostService::update()`, include:

```php
'seminar_id' => $payload['seminar_id'] ?? null,
```

Before saving a non-null seminar ID, verify:

```php
abort_unless(
    \App\Models\Seminar::query()
        ->whereKey($payload['seminar_id'])
        ->where('connector_id', $connector->id)
        ->exists(),
    422
);
```

- [ ] **Step 5: Add feature and official answer methods**

In `CommunityModerationService`, add:

```php
public function featurePost(User $actor, CommunityPost $post, string $reason): CommunityPost
{
    $this->access->abortUnlessCanModerateSpace($actor, $post->connector);

    $previous = (string) $post->status;

    $post->forceFill([
        'featured_at' => now(),
        'featured_by' => $actor->id,
    ])->save();

    $this->recordAction($actor, $post, 'feature', $previous, $previous, $reason);

    return $post->fresh();
}
```

Add `unfeaturePost()` by setting `featured_at` and `featured_by` to `null`, recording action type `unfeature`.

Add `markOfficialAnswer()`:

```php
public function markOfficialAnswer(User $actor, CommunityPost $post, CommunityComment $comment, string $reason): CommunityPost
{
    $this->access->abortUnlessCanModerateSpace($actor, $post->connector);

    abort_unless($post->isQuestion(), 422);
    abort_unless((int) $comment->community_post_id === (int) $post->id, 404);

    $previous = (string) $post->status;

    $post->forceFill([
        'official_answer_comment_id' => $comment->id,
    ])->save();

    $this->recordAction($actor, $post, 'mark_official_answer', $previous, $previous, $reason);

    return $post->fresh();
}
```

If `recordAction()` expects an enum, add `feature`, `unfeature`, and `mark_official_answer` to `CommunityModerationActionType`.

- [ ] **Step 6: Run service test to verify pass**

Run:

```bash
php artisan test tests/Feature/Community/CommunityHubModerationUiTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Community/CommunityPostService.php app/Services/Community/CommunityModerationService.php app/Http/Requests/Community/StoreCommunityPostRequest.php app/Http/Requests/Community/UpdateCommunityPostRequest.php app/Http/Requests/Community/ModerateCommunityContentRequest.php app/Enums/CommunityModerationActionType.php tests/Feature/Community/CommunityHubModerationUiTest.php
git commit -m "feat: support community hub moderation actions"
```

---

### Task 3: Upgrade Connector Community Hub UI

**Files:**
- Modify: `app/Http/Controllers/Connector/CommunityFeedController.php`
- Modify: `resources/views/connectors/community/index.blade.php`
- Modify: `resources/views/connectors/community/show.blade.php`
- Modify: `resources/views/connectors/community/create.blade.php`
- Modify: `resources/views/connectors/community/edit.blade.php`
- Modify: `resources/views/connectors/community/moderation/index.blade.php`
- Modify: `resources/views/components/community/feed-sidebar.blade.php`
- Modify: `resources/views/components/community/post-card.blade.php`
- Modify: `resources/views/components/community/post-composer.blade.php`
- Modify: `resources/views/components/community/post-type-badge.blade.php`
- Modify: `resources/views/components/community/right-panel.blade.php`
- Modify: `resources/views/components/community/safety-reminder.blade.php`
- Modify: `resources/views/layouts/connector-app.blade.php`
- Test: `tests/Feature/Community/CommunityHubUiSmokeTest.php`

**Interfaces:**
- Consumes: `CommunityPostType::Event`
- Consumes: `CommunityPostType::DiscussionPrompt`
- Produces: connector page title `Community Hub`
- Produces: visible tabs `Featured`, `Announcements`, `Events`, `Resources`, `Q&A`, `Discussions`
- Produces: connector sidebar nav label `Community Hub`

- [ ] **Step 1: Write connector UI smoke test**

Create `tests/Feature/Community/CommunityHubUiSmokeTest.php`:

```php
<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityHubUiSmokeTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_connector_hub_shows_module_matched_tabs_and_no_social_media_controls(): void
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);

        app(CommunityPostService::class)->create($owner, $connector, [
            'post_type' => 'event',
            'title' => 'Consent seminar this Friday',
            'body' => 'Adult members are invited to join the connector seminar.',
            'resource_url' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('connector.community.index', $connector))
            ->assertOk()
            ->assertSee('Community Hub')
            ->assertSee('Featured')
            ->assertSee('Announcements')
            ->assertSee('Events')
            ->assertSee('Resources')
            ->assertSee('Q&A')
            ->assertSee('Discussions')
            ->assertSee('Consent seminar this Friday')
            ->assertDontSee('Message privately')
            ->assertDontSee('Share to feed')
            ->assertDontSee('Followers');
    }

    public function test_minor_direct_access_does_not_render_hub_controls(): void
    {
        $adult = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $adult->assignRole('learner');
        $connector = $this->createVerifiedConnector($adult);
        $minor = $this->createMinorLearner(13);

        $this->actingAs($minor)
            ->get(route('connector.community.index', $connector))
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run connector UI test to verify failure**

Run:

```bash
php artisan test tests/Feature/Community/CommunityHubUiSmokeTest.php
```

Expected: FAIL until labels, tabs, post type support, and minor route behavior are aligned.

- [ ] **Step 3: Update controller query data**

In `CommunityFeedController::index`, pass these values to the view:

```php
'hubTabs' => [
    'featured' => 'Featured',
    'announcement' => 'Announcements',
    'event' => 'Events',
    'resource' => 'Resources',
    'moderated_question' => 'Q&A',
    'discussion_prompt' => 'Discussions',
],
'featuredPosts' => CommunityPost::query()
    ->where('connector_id', $connector->id)
    ->whereNotNull('featured_at')
    ->whereIn('status', ['published', 'locked'])
    ->with(['author', 'comments', 'reactions', 'reports'])
    ->latest('featured_at')
    ->limit(3)
    ->get(),
```

Keep pagination and existing safety filtering for normal posts.

- [ ] **Step 4: Rename connector-facing copy**

Replace connector-facing headings and nav labels:

- `Community Feed` -> `Community Hub`
- `Adult connector space` -> `Connector learning community`
- `Questions` -> `Q&A`
- `Pinned announcement` -> `Featured post`

Do not rename route names, controller classes, model classes, or database tables in this task.

- [ ] **Step 5: Implement hub tabs in `feed-sidebar`**

The sidebar should render the hub tabs with compact module styling:

```blade
@props(['connector', 'active' => 'featured', 'canModerate' => false])

@php
    $items = [
        ['key' => 'featured', 'label' => 'Featured', 'href' => route('connector.community.index', [$connector, 'tab' => 'featured'])],
        ['key' => 'announcement', 'label' => 'Announcements', 'href' => route('connector.community.index', [$connector, 'type' => 'announcement'])],
        ['key' => 'event', 'label' => 'Events', 'href' => route('connector.community.index', [$connector, 'type' => 'event'])],
        ['key' => 'resource', 'label' => 'Resources', 'href' => route('connector.community.index', [$connector, 'type' => 'resource'])],
        ['key' => 'moderated_question', 'label' => 'Q&A', 'href' => route('connector.community.index', [$connector, 'type' => 'moderated_question'])],
        ['key' => 'discussion_prompt', 'label' => 'Discussions', 'href' => route('connector.community.index', [$connector, 'type' => 'discussion_prompt'])],
    ];
@endphp

<aside {{ $attributes->merge(['class' => 'rounded-lg border border-gray-200 bg-white p-3']) }}>
    <nav class="space-y-1">
        @foreach($items as $item)
            <a href="{{ $item['href'] }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold {{ $active === $item['key'] ? 'bg-purple-50 text-purple-800' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach

        @if($canModerate)
            <a href="{{ route('connector.community.moderation.index', $connector) }}" class="mt-3 flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-bold text-amber-800">
                <span>Moderation</span>
            </a>
        @endif
    </nav>
</aside>
```

Preserve any existing variables the current component needs by merging rather than deleting local behavior.

- [ ] **Step 6: Upgrade post cards**

`post-card.blade.php` must show:

- Connector avatar block using connector initial.
- Post type badge.
- Status badge.
- Featured label when `isFeatured()` is true or `isPinned` is passed.
- Event metadata when `post_type` is `event`.
- Resource link block when `resource_url` exists.
- Q&A official answer marker when `official_answer_comment_id` exists.
- Reaction count and comment count.
- `Flat comments only` copy.
- Report button.
- Moderate button only when `$canModerate`.

The card must not show:

- `Share`
- `DM`
- `Message privately`
- `Followers`
- Nested reply controls

- [ ] **Step 7: Match create and edit forms to seminar module UI**

Use the same form rhythm as connector seminars:

- Page header with title and short helper copy.
- White bordered form shell.
- Compact labels.
- Status/safety guidance in a small side panel or inline alert.
- Primary purple submit button.
- Secondary neutral cancel link.

Post type select labels:

```php
[
    'announcement' => 'Announcement',
    'event' => 'Event or Seminar',
    'resource' => 'Educational Resource',
    'moderated_question' => 'Q&A',
    'discussion_prompt' => 'Discussion Prompt',
]
```

- [ ] **Step 8: Run connector UI smoke test to verify pass**

Run:

```bash
php artisan test tests/Feature/Community/CommunityHubUiSmokeTest.php
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Connector/CommunityFeedController.php resources/views/connectors/community resources/views/components/community resources/views/layouts/connector-app.blade.php tests/Feature/Community/CommunityHubUiSmokeTest.php
git commit -m "feat: refine connector community hub ui"
```

---

### Task 4: Upgrade Admin Community Hub Moderation UI

**Files:**
- Modify: `app/Http/Controllers/Admin/CommunityFeedController.php`
- Modify: `resources/views/admin/community/index.blade.php`
- Modify: `resources/views/admin/community/show.blade.php`
- Modify: `resources/views/admin/community/settings.blade.php`
- Modify: `resources/views/layouts/admin.blade.php`
- Test: `tests/Feature/Community/AdminCommunityHubUiTest.php`

**Interfaces:**
- Produces: admin heading `Community Hub`
- Produces: admin filters for `status`, `type`, `connector_id`, and `search`
- Produces: admin stats for `spaces`, `pending`, `reported`, `escalated`, and `featured`

- [ ] **Step 1: Write admin UI smoke test**

Create `tests/Feature/Community/AdminCommunityHubUiTest.php`:

```php
<?php

namespace Tests\Feature\Community;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DatabaseTestCase;

class AdminCommunityHubUiTest extends DatabaseTestCase
{
    use RefreshDatabase;

    public function test_admin_hub_index_matches_moderation_workspace_language(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.community.index'))
            ->assertOk()
            ->assertSee('Community Hub')
            ->assertSee('Platform moderation')
            ->assertSee('Pending')
            ->assertSee('Reported')
            ->assertSee('Escalated')
            ->assertSee('Global safety controls')
            ->assertDontSee('Trending')
            ->assertDontSee('Followers');
    }

    public function test_admin_settings_show_emergency_freeze_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.community.settings'))
            ->assertOk()
            ->assertSee('Emergency Freeze')
            ->assertSee('Read-only')
            ->assertSee('Hidden');
    }
}
```

- [ ] **Step 2: Run admin UI test to verify failure**

Run:

```bash
php artisan test tests/Feature/Community/AdminCommunityHubUiTest.php
```

Expected: FAIL until admin labels, stats, filters, and settings copy are aligned.

- [ ] **Step 3: Update admin index query data**

In `Admin\CommunityFeedController::index`, include:

```php
'stats' => [
    'spaces' => CommunitySpace::query()->count(),
    'pending' => CommunityPost::query()->where('status', 'pending_review')->count(),
    'reported' => CommunityReport::query()->whereIn('status', ['open', 'under_review'])->count(),
    'escalated' => CommunityPost::query()->where('status', 'escalated')->count(),
    'featured' => CommunityPost::query()->whereNotNull('featured_at')->count(),
],
```

Ensure post query filters by:

- `status`
- `type`
- `connector_id`
- `search`

- [ ] **Step 4: Update admin views**

Admin index must use:

- A top white bordered header card.
- Compact stat cards.
- A table with columns `Post`, `Connector`, `Type`, `Status`, `Reports`, `Actions`.
- Filter controls above the table.
- A `Global safety controls` link to settings.

Admin show must include:

- Full escaped post body.
- Connector and author details.
- Reports panel.
- Comments panel.
- Version history panel.
- Moderation action history.
- Approve, hide, lock, unlock, restore, remove, escalate, feature, and unfeature controls when allowed.

Admin settings must include:

- `Emergency Freeze`.
- Freeze form requiring `reason`.
- Unfreeze form.
- Suspended connector visibility selector with `Read-only` and `Hidden`.

- [ ] **Step 5: Update admin navigation label**

In `resources/views/layouts/admin.blade.php`, place `Community Hub` or `Community Moderation` under the existing moderation group. Use the current active-state pattern with:

```php
request()->routeIs('admin.community.*')
```

- [ ] **Step 6: Run admin UI smoke test to verify pass**

Run:

```bash
php artisan test tests/Feature/Community/AdminCommunityHubUiTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/CommunityFeedController.php resources/views/admin/community resources/views/layouts/admin.blade.php tests/Feature/Community/AdminCommunityHubUiTest.php
git commit -m "feat: refine admin community hub moderation ui"
```

---

### Task 5: Add Final Safety And Visual Regression Coverage

**Files:**
- Modify: `tests/Feature/Community/CommunityInteractionSafetyTest.php`
- Modify: `tests/Feature/Community/CommunityNotificationTest.php`
- Modify: `tests/Feature/Community/CommunityHubUiSmokeTest.php`
- Modify: `tests/Feature/Community/AdminCommunityHubUiTest.php`

**Interfaces:**
- Consumes: all Community Hub routes, services, views, and post types from Tasks 1-4.
- Produces: final regression evidence for safety and UI alignment.

- [ ] **Step 1: Add no-social-controls assertions**

Add these assertions to connector and admin UI tests where pages render community content:

```php
->assertDontSee('DM')
->assertDontSee('Message privately')
->assertDontSee('Share to feed')
->assertDontSee('Followers')
->assertDontSee('Trending')
```

- [ ] **Step 2: Add minor exclusion assertions**

In `CommunityInteractionSafetyTest`, add coverage that a minor receives `403` for:

```php
route('connector.community.store', $connector)
route('connector.community.comments.store', [$connector, $post])
route('connector.community.reactions.store', [$connector, $post])
route('connector.community.reports.store', [$connector, $post])
```

Expected result for each route: forbidden or redirected to an existing age/safety denial page if that is the platform pattern.

- [ ] **Step 3: Add guardian notification absence assertion**

In `CommunityNotificationTest`, ensure adult Community Hub activity does not notify guardians or child accounts:

```php
Notification::assertNothingSentTo($minor);
```

Keep this test focused on Community Hub workflows only.

- [ ] **Step 4: Run community regression suite**

Run:

```bash
php artisan test tests/Feature/Community tests/Unit/Services/Community
```

Expected: PASS.

- [ ] **Step 5: Run connector, RBAC, moderation, notification regressions**

Run:

```bash
php artisan test tests/Feature/Connectors tests/Unit/Services/Connectors tests/Feature/Rbac tests/Feature/Moderation tests/Feature/Admin/Moderation tests/Feature/Notifications
```

Expected: PASS.

- [ ] **Step 6: Run frontend build**

Run:

```bash
npm.cmd run build
```

Expected: PASS.

- [ ] **Step 7: Manual browser checks**

Check these pages as an admin:

- Community Hub index.
- Community post detail.
- Community settings.
- Emergency freeze and unfreeze.

Check these pages as a connector owner:

- Community Hub index.
- Create post.
- Event post.
- Resource post.
- Moderated Q&A post.
- Discussion prompt post.
- Post detail.
- Moderation queue.

Check as a minor learner:

- Direct connector community routes return 403 or the established safety denial response.
- No Community Hub controls appear in minor-facing navigation.

- [ ] **Step 8: Commit**

```bash
git add tests/Feature/Community/CommunityInteractionSafetyTest.php tests/Feature/Community/CommunityNotificationTest.php tests/Feature/Community/CommunityHubUiSmokeTest.php tests/Feature/Community/AdminCommunityHubUiTest.php
git commit -m "test: cover community hub safety and ui regressions"
```

---

## Final Verification Checklist

- [ ] `php artisan test tests/Feature/Community tests/Unit/Services/Community`
- [ ] `php artisan test tests/Feature/Connectors tests/Unit/Services/Connectors`
- [ ] `php artisan test tests/Feature/Rbac`
- [ ] `php artisan test tests/Feature/Moderation tests/Feature/Admin/Moderation`
- [ ] `php artisan test tests/Feature/Notifications`
- [ ] `npm.cmd run build`
- [ ] Manual browser check as admin.
- [ ] Manual browser check as connector owner.
- [ ] Manual browser check as minor learner.

## Plan Self-Review

- Spec coverage: the tasks cover Community Hub naming, hub tabs, new post types, seminar/event support, featured posts, official Q&A answers, connector/admin UI matching, safety constraints, minor exclusion, and regression verification.
- Placeholder scan: no task uses undefined placeholder work; each task includes concrete files, tests, commands, and expected results.
- Type consistency: method names and fields introduced in Task 1 are reused consistently by later tasks.
