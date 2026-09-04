<?php

namespace Tests\Feature\Community;

use App\Enums\CommunityPostStatus;
use App\Models\CommunityPostMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\DatabaseTestCase;
use Tests\Feature\Connectors\ConnectorTestHelpers;

class CommunityPostMediaTest extends DatabaseTestCase
{
    use ConnectorTestHelpers;
    use RefreshDatabase;

    public function test_post_can_store_an_ordered_six_image_gallery_on_private_storage(): void
    {
        Storage::fake('local');
        [$connector, $author] = $this->authorFixture();

        $images = collect(range(1, 6))
            ->map(fn (int $number) => UploadedFile::fake()->image("image-{$number}.jpg")->size(5_000))
            ->all();

        $this->actingAs($author)
            ->post(route('connector.community.store', $connector), [
                'post_type' => 'announcement',
                'topic_choice' => 'Connector announcement',
                'body' => 'Six images from an adult community event.',
                'images' => $images,
            ])
            ->assertRedirect();

        $post = $connector->communityPosts()->latest('id')->firstOrFail();
        $media = $post->activeMedia()->get();

        $this->assertCount(6, $media);
        $this->assertSame(range(0, 5), $media->pluck('display_order')->all());
        $this->assertSame(['image'], $media->pluck('media_type')->unique()->values()->all());
        $this->assertSame($media->first()->path, $post->fresh()->media_path);
        $media->each(fn (CommunityPostMedia $item) => Storage::disk('local')->assertExists($item->path));
    }

    public function test_post_rejects_too_many_oversized_invalid_and_mixed_images(): void
    {
        Storage::fake('local');
        [$connector, $author] = $this->authorFixture();
        $base = [
            'post_type' => 'announcement',
            'topic_choice' => 'Connector announcement',
            'body' => 'Media validation for an adult Community Hub post.',
        ];

        $this->actingAs($author)
            ->from(route('connector.community.create', $connector))
            ->post(route('connector.community.store', $connector), $base + [
                'images' => collect(range(1, 7))
                    ->map(fn (int $number) => UploadedFile::fake()->image("image-{$number}.png"))
                    ->all(),
            ])
            ->assertRedirect(route('connector.community.create', $connector))
            ->assertSessionHasErrors('images');

        $this->actingAs($author)
            ->from(route('connector.community.create', $connector))
            ->post(route('connector.community.store', $connector), $base + [
                'images' => [UploadedFile::fake()->image('large.webp')->size(5_121)],
            ])
            ->assertSessionHasErrors('images.0');

        $this->actingAs($author)
            ->from(route('connector.community.create', $connector))
            ->post(route('connector.community.store', $connector), $base + [
                'images' => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
            ])
            ->assertSessionHasErrors('images.0');

        $this->actingAs($author)
            ->from(route('connector.community.create', $connector))
            ->post(route('connector.community.store', $connector), $base + [
                'images' => [UploadedFile::fake()->image('image.jpg')],
                'video' => UploadedFile::fake()->create('clip.mp4', 1_000, 'video/mp4'),
            ])
            ->assertSessionHasErrors(['images', 'video']);

        $this->assertDatabaseCount('community_posts', 0);
        $this->assertDatabaseCount('community_post_media', 0);
    }

    public function test_post_can_store_one_video_and_rejects_an_oversized_video(): void
    {
        Storage::fake('local');
        [$connector, $author] = $this->authorFixture();
        $base = [
            'post_type' => 'announcement',
            'topic_choice' => 'Community seminar',
            'body' => 'Recorded highlights for verified adult members.',
        ];

        $this->actingAs($author)
            ->post(route('connector.community.store', $connector), $base + [
                'video' => UploadedFile::fake()->create('highlights.mp4', 25_000, 'video/mp4'),
            ])
            ->assertRedirect();

        $post = $connector->communityPosts()->latest('id')->firstOrFail();
        $video = $post->activeMedia()->sole();
        $this->assertSame('video', $video->media_type);
        $this->assertSame('video/mp4', $video->mime_type);
        Storage::disk('local')->assertExists($video->path);

        $this->actingAs($author)
            ->from(route('connector.community.create', $connector))
            ->post(route('connector.community.store', $connector), $base + [
                'video' => UploadedFile::fake()->create('too-large.mp4', 25_601, 'video/mp4'),
            ])
            ->assertSessionHasErrors('video');

        $this->assertDatabaseCount('community_posts', 1);
    }

    public function test_edit_marks_selected_media_removed_retains_the_file_and_adds_replacement(): void
    {
        Storage::fake('local');
        [$connector, $author] = $this->authorFixture();

        $this->actingAs($author)->post(route('connector.community.store', $connector), [
            'post_type' => 'announcement',
            'topic_choice' => 'Connector announcement',
            'body' => 'Original gallery for adult members.',
            'images' => [
                UploadedFile::fake()->image('first.jpg'),
                UploadedFile::fake()->image('second.jpg'),
            ],
        ])->assertRedirect();

        $post = $connector->communityPosts()->latest('id')->firstOrFail();
        $removed = $post->activeMedia()->firstOrFail();
        $kept = $post->activeMedia()->whereKeyNot($removed->id)->firstOrFail();

        $this->actingAs($author)
            ->put(route('connector.community.update', [$connector, $post]), [
                'post_type' => 'announcement',
                'topic_choice' => 'Connector announcement',
                'body' => 'Updated gallery for adult members.',
                'remove_media_ids' => [$removed->id],
                'images' => [UploadedFile::fake()->image('replacement.webp')],
            ])
            ->assertRedirect(route('connector.community.show', [$connector, $post]));

        $removed->refresh();
        $this->assertNotNull($removed->removed_at);
        $this->assertSame($author->id, $removed->removed_by);
        Storage::disk('local')->assertExists($removed->path);

        $active = $post->activeMedia()->get();
        $this->assertCount(2, $active);
        $this->assertTrue($active->contains($kept));
        $this->assertTrue($active->contains(fn (CommunityPostMedia $item) => $item->original_name === 'replacement.webp'));
        $this->assertSame($kept->path, $post->fresh()->media_path);
    }

    public function test_edit_rejects_foreign_removal_ids_and_an_invalid_final_media_set(): void
    {
        Storage::fake('local');
        [$connector, $author] = $this->authorFixture();

        foreach (['First post', 'Second post'] as $body) {
            $this->actingAs($author)->post(route('connector.community.store', $connector), [
                'post_type' => 'announcement',
                'topic_choice' => 'Connector announcement',
                'body' => $body.' for verified adult members.',
                'images' => [UploadedFile::fake()->image(str($body)->slug().'.jpg')],
            ])->assertRedirect();
        }

        [$first, $second] = $connector->communityPosts()->oldest('id')->get()->all();
        $foreignMedia = $second->activeMedia()->sole();

        $this->actingAs($author)
            ->from(route('connector.community.edit', [$connector, $first]))
            ->put(route('connector.community.update', [$connector, $first]), [
                'post_type' => 'announcement',
                'topic_choice' => 'Connector announcement',
                'body' => 'Foreign media removal must not be accepted.',
                'remove_media_ids' => [$foreignMedia->id],
            ])
            ->assertSessionHasErrors('remove_media_ids');

        $this->actingAs($author)
            ->from(route('connector.community.edit', [$connector, $first]))
            ->put(route('connector.community.update', [$connector, $first]), [
                'post_type' => 'announcement',
                'topic_choice' => 'Connector announcement',
                'body' => 'Existing images and a new video cannot be mixed.',
                'video' => UploadedFile::fake()->create('clip.webm', 1_000, 'video/webm'),
            ])
            ->assertSessionHasErrors('video');

        $this->assertNull($foreignMedia->fresh()->removed_at);
    }

    public function test_active_media_delivery_is_item_scoped_and_denies_ineligible_viewers(): void
    {
        Storage::fake('local');
        [$connector, $author] = $this->authorFixture();
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);

        foreach (['First delivery post', 'Second delivery post'] as $body) {
            $this->actingAs($author)->post(route('connector.community.store', $connector), [
                'post_type' => 'announcement',
                'topic_choice' => 'Connector announcement',
                'body' => $body.' for verified adults.',
                'images' => [UploadedFile::fake()->image(str($body)->slug().'.jpg')],
            ])->assertRedirect();
        }

        [$first, $second] = $connector->communityPosts()->oldest('id')->get()->all();
        $firstMedia = $first->activeMedia()->sole();
        $secondMedia = $second->activeMedia()->sole();

        $this->actingAs($viewer)
            ->get(route('connector.community.media.show', [$connector, $first, $firstMedia]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($viewer)
            ->get(route('connector.community.media.show', [$connector, $first, $secondMedia]))
            ->assertNotFound();

        $minor = $this->createMinorLearner(14);
        $minorRole = $this->createCustomRole($connector, ['community.view_space']);
        $connector->memberships()->create([
            'user_id' => $minor->id,
            'connector_role_id' => $minorRole->id,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $this->actingAs($minor)
            ->get(route('connector.community.media.show', [$connector, $first, $firstMedia]))
            ->assertForbidden();

        $first->update(['status' => CommunityPostStatus::Hidden->value]);
        $this->actingAs($viewer)
            ->get(route('connector.community.media.show', [$connector, $first, $firstMedia]))
            ->assertNotFound();
    }

    public function test_removed_or_missing_media_is_hidden_from_members_but_removed_media_remains_available_to_moderators(): void
    {
        Storage::fake('local');
        [$connector, $author] = $this->authorFixture();
        $viewer = $this->createAdultConnectorMember($connector, ['community.view_space']);

        $this->actingAs($author)->post(route('connector.community.store', $connector), [
            'post_type' => 'announcement',
            'topic_choice' => 'Connector announcement',
            'body' => 'Media audit access for connector moderators.',
            'images' => [
                UploadedFile::fake()->image('removed.jpg'),
                UploadedFile::fake()->image('missing.jpg'),
            ],
        ])->assertRedirect();

        $post = $connector->communityPosts()->latest('id')->firstOrFail();
        [$removed, $missing] = $post->activeMedia()->get()->all();
        $removed->update(['removed_at' => now(), 'removed_by' => $author->id]);
        Storage::disk('local')->delete($missing->path);

        $this->actingAs($viewer)
            ->get(route('connector.community.media.show', [$connector, $post, $removed]))
            ->assertNotFound();

        $this->actingAs($viewer)
            ->get(route('connector.community.media.show', [$connector, $post, $missing]))
            ->assertNotFound();

        $owner = $connector->memberships()
            ->whereHas('role', fn ($query) => $query->where('is_owner', true))
            ->firstOrFail()
            ->user;

        $this->actingAs($owner)
            ->get(route('connector.community.media.show', [$connector, $post, $removed]))
            ->assertOk();
    }

    public function test_create_edit_and_post_views_expose_clean_media_picker_and_gallery_contracts(): void
    {
        Storage::fake('local');
        [$connector, $author] = $this->authorFixture();

        $this->actingAs($author)
            ->get(route('connector.community.create', $connector))
            ->assertOk()
            ->assertSee('Add images')
            ->assertSee('Add video')
            ->assertSee('Up to 6 images')
            ->assertSee('5 MB each')
            ->assertSee('25 MB')
            ->assertSee('name="images[]"', false)
            ->assertSee('multiple', false)
            ->assertSee('data-testid="community-media-picker"', false)
            ->assertDontSee('name="media"', false);

        $this->actingAs($author)->post(route('connector.community.store', $connector), [
            'post_type' => 'announcement',
            'topic_choice' => 'Connector announcement',
            'body' => 'A gallery preview and removal test for adults.',
            'images' => [
                UploadedFile::fake()->image('first.jpg'),
                UploadedFile::fake()->image('second.jpg'),
            ],
        ])->assertRedirect();

        $post = $connector->communityPosts()->latest('id')->firstOrFail();
        $media = $post->activeMedia()->get();

        $this->actingAs($author)
            ->get(route('connector.community.edit', [$connector, $post]))
            ->assertOk()
            ->assertSee('Existing media')
            ->assertSee('first.jpg')
            ->assertSee('second.jpg')
            ->assertSee('data-existing-media-id="'.$media->first()->id.'"', false)
            ->assertSee('remove_media_ids[]', false);

        $this->actingAs($author)
            ->get(route('connector.community.show', [$connector, $post]))
            ->assertOk()
            ->assertSee('data-testid="community-media-gallery"', false)
            ->assertSee(route('connector.community.media.show', [$connector, $post, $media->first()]), false)
            ->assertSee(route('connector.community.media.show', [$connector, $post, $media->last()]), false);
    }

    public function test_edit_restores_media_removal_choice_and_warns_that_new_files_must_be_reselected_after_any_validation_error(): void
    {
        Storage::fake('local');
        [$connector, $author] = $this->authorFixture();

        $this->actingAs($author)->post(route('connector.community.store', $connector), [
            'post_type' => 'announcement',
            'topic_choice' => 'Connector announcement',
            'body' => 'A post with media before a validation retry.',
            'images' => [UploadedFile::fake()->image('retry.jpg')],
        ])->assertRedirect();

        $post = $connector->communityPosts()->latest('id')->firstOrFail();
        $media = $post->activeMedia()->sole();
        $editUrl = route('connector.community.edit', [$connector, $post]);

        $this->actingAs($author)
            ->from($editUrl)
            ->put(route('connector.community.update', [$connector, $post]), [
                'post_type' => 'announcement',
                'topic_choice' => 'Connector announcement',
                'body' => '',
                'remove_media_ids' => [$media->id],
            ])
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors('body');

        $this->actingAs($author)
            ->get($editUrl)
            ->assertOk()
            ->assertSee('If you selected new files, choose them again before saving.')
            ->assertSee('checked', false);
    }

    private function authorFixture(): array
    {
        $owner = User::factory()->create([
            'role' => 'learner',
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $owner->assignRole('learner');
        $connector = $this->createVerifiedConnector($owner);
        $author = $this->createAdultConnectorMember($connector, [
            'community.view_space',
            'community.create_post',
            'community.edit_own_post',
        ]);

        return [$connector, $author];
    }
}
