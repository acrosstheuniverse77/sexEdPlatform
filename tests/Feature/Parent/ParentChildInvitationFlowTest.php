<?php

namespace Tests\Feature\Parent;

use App\Models\LearnerProfile;
use App\Models\ParentChildAccount;
use App\Models\ParentChildInvitation;
use App\Models\User;
use App\Services\ParentChildInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParentChildInvitationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_parent_can_send_invitation_to_existing_learner(): void
    {
        $this->seedLocationRows();

        $parent = $this->createApprovedParent();
        $child = $this->createLearner('invitedchild', 12);

        $this->actingAs($parent)
            ->from(route('parent.invitations.index'))
            ->post(route('parent.invitations.store'), [
                'identifier' => $child->learnerProfile->username,
                'relationship_type' => 'grandmother',
                'message' => 'Please accept this invitation so I can guide your learning progress.',
            ])
            ->assertRedirect(route('parent.invitations.index'))
            ->assertSessionHasNoErrors();

        $invitation = ParentChildInvitation::query()->first();

        $this->assertNotNull($invitation);
        $this->assertSame($parent->id, $invitation->inviter_parent_user_id);
        $this->assertSame($child->id, $invitation->child_user_id);
        $this->assertSame('grandmother', $invitation->relationship_type);
        $this->assertSame('pending', $invitation->status->value);

        $childNotification = $child->fresh()->notifications()->latest()->first();
        $this->assertNotNull($childNotification);
        $this->assertSame('parent_child_invitation_received', data_get($childNotification->data, 'type'));
    }

    public function test_verification_required_invitation_requires_supporting_document(): void
    {
        $this->seedLocationRows();

        $parent = $this->createApprovedParent();
        $child = $this->createLearner('docrequiredchild', 12);

        $this->actingAs($parent)
            ->from(route('parent.invitations.index'))
            ->post(route('parent.invitations.store'), [
                'identifier' => $child->learnerProfile->username,
                'relationship_type' => 'legal_guardian',
                'relationship_document_type' => 'court_order',
                'confirm_relationship_verification' => '1',
            ])
            ->assertRedirect(route('parent.invitations.index'))
            ->assertSessionHasErrors(['relationship_document']);

        $this->assertDatabaseCount('parent_child_invitations', 0);
        $this->assertDatabaseCount('parent_child_accounts', 0);
    }

    public function test_verification_required_invitation_defers_relationship_until_acceptance(): void
    {
        $this->seedLocationRows();
        Storage::fake('local');

        $parent = $this->createApprovedParent();
        $child = $this->createLearner('legaldocchild', 12);

        $this->actingAs($parent)
            ->from(route('parent.invitations.index'))
            ->post(route('parent.invitations.store'), [
                'identifier' => $child->learnerProfile->username,
                'relationship_type' => 'legal_guardian',
                'relationship_document_type' => 'court_order',
                'relationship_document' => UploadedFile::fake()->create('court-order.pdf', 64, 'application/pdf'),
                'confirm_relationship_verification' => '1',
            ])
            ->assertRedirect(route('parent.invitations.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('parent_child_accounts', [
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
        ]);
        $this->assertDatabaseCount('guardian_relationship_verification_documents', 0);
        $this->assertNotEmpty(ParentChildInvitation::query()->sole()->relationship_verification_documents);
    }

    public function test_failed_invitation_staging_removes_uploaded_documents(): void
    {
        $this->seedLocationRows();
        Storage::fake('local');

        $parent = $this->createApprovedParent();
        $child = $this->createLearner('stagingcleanupchild', 12);
        ParentChildInvitation::updating(static function (): void {
            throw new \RuntimeException('Unable to persist staged documents.');
        });

        try {
            app(ParentChildInvitationService::class)->sendInvitation(
                $parent,
                $child->learnerProfile->username,
                'legal_guardian',
                verificationPayload: [
                    'document_type' => 'court_order',
                    'document' => UploadedFile::fake()->create('court-order.pdf', 64, 'application/pdf'),
                ],
            );
            $this->fail('Expected staged document persistence to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Unable to persist staged documents.', $exception->getMessage());
        } finally {
            ParentChildInvitation::flushEventListeners();
        }

        Storage::disk('local')->assertDirectoryEmpty('guardian-relationship-invitations');
        $this->assertDatabaseCount('parent_child_invitations', 0);
    }

    public function test_pending_existing_learner_can_access_dashboard_and_chat(): void
    {
        $this->seedLocationRows();
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $parent = $this->createApprovedParent();
        $child = $this->createLearner('pendingaccesschild', 12);

        $this->actingAs($parent)
            ->post(route('parent.invitations.store'), [
                'identifier' => $child->learnerProfile->username,
                'relationship_type' => 'legal_guardian',
                'relationship_document_type' => 'court_order',
                'relationship_document' => UploadedFile::fake()->create('court-order.pdf', 64, 'application/pdf'),
                'confirm_relationship_verification' => '1',
            ])
            ->assertRedirect(route('parent.invitations.index'));

        $this->assertSame(0, $admin->fresh()->notifications()
            ->where('data->type', 'guardian_relationship_verification_submitted')
            ->count());
        $this->assertDatabaseMissing('parent_child_accounts', [
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
        ]);

        $invitation = ParentChildInvitation::query()->sole();

        $this->actingAs($child)
            ->post(route('parent.invitations.respond', $invitation), ['decision' => 'accept'])
            ->assertRedirect(route('parent.invitations.show', $invitation));

        $this->actingAs($child)->get(route('learner.dashboard'))->assertOk();
        $this->actingAs($child)->get(route('chat.page'))->assertOk();
    }

    public function test_child_can_accept_proof_required_invitation_and_submit_relationship_for_admin_review(): void
    {
        $this->seedLocationRows();
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $parent = $this->createApprovedParent();
        $child = $this->createLearner('acceptproofchild', 12);

        $this->actingAs($parent)
            ->post(route('parent.invitations.store'), [
                'identifier' => $child->learnerProfile->username,
                'relationship_type' => 'legal_guardian',
                'relationship_document_type' => 'court_order',
                'relationship_document' => UploadedFile::fake()->create('court-order.pdf', 64, 'application/pdf'),
                'confirm_relationship_verification' => '1',
            ])
            ->assertRedirect(route('parent.invitations.index'));

        $invitation = ParentChildInvitation::query()->sole();
        $stagedDocument = $invitation->relationship_verification_documents[0];

        $this->actingAs($child)
            ->post(route('parent.invitations.respond', $invitation), ['decision' => 'accept'])
            ->assertRedirect(route('parent.invitations.show', $invitation));

        $relationship = ParentChildAccount::query()
            ->where('parent_user_id', $parent->id)
            ->where('child_user_id', $child->id)
            ->sole();

        $this->assertSame('under_review', $relationship->relationship_verified_status);
        $this->assertSame('pending', $relationship->relationship_status);
        $this->assertSame('pending', $relationship->verification_status);
        $this->assertNull($relationship->relationship_verified_at);
        $verificationDocument = $relationship->verificationDocuments()->sole();
        $this->assertSame($stagedDocument['document_type'], $verificationDocument->document_type);
        $this->assertSame($stagedDocument['disk'], $verificationDocument->disk);
        $this->assertStringStartsWith("guardian-relationship-verifications/{$relationship->id}/", $verificationDocument->path);
        $this->assertNotSame($stagedDocument['path'], $verificationDocument->path);
        $this->assertSame($stagedDocument['original_name'], $verificationDocument->original_name);
        $this->assertSame($stagedDocument['mime_type'], $verificationDocument->mime_type);
        $this->assertSame($stagedDocument['size_bytes'], $verificationDocument->size_bytes);
        Storage::disk('local')->assertMissing($stagedDocument['path']);
        Storage::disk('local')->assertExists($verificationDocument->path);
        $this->assertDatabaseHas('guardian_relationship_verification_documents', [
            'parent_child_account_id' => $relationship->id,
            'document_type' => $stagedDocument['document_type'],
            'path' => $verificationDocument->path,
        ]);
        $this->assertNull($invitation->fresh()->relationship_verification_documents);
        $this->assertSame(1, $admin->fresh()->notifications()->where('data->type', 'guardian_relationship_verification_submitted')->count());
    }

    public function test_accepting_proof_required_invitation_with_missing_staged_document_leaves_state_unchanged(): void
    {
        $this->seedLocationRows();
        Storage::fake('local');

        $parent = $this->createApprovedParent();
        $child = $this->createLearner('missingstagedchild', 12);
        $documents = [[
            'document_type' => 'court_order',
            'disk' => 'local',
            'path' => 'guardian-relationship-invitations/missing/court-order.pdf',
            'original_name' => 'court-order.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 64,
        ]];
        $invitation = ParentChildInvitation::query()->create([
            'inviter_parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'invite_token' => (string) \Illuminate\Support\Str::uuid(),
            'relationship_type' => 'legal_guardian',
            'relationship_verification_documents' => $documents,
            'status' => 'pending',
            'expires_at' => now()->addDays(3),
        ]);

        try {
            app(ParentChildInvitationService::class)->respondToInvitation($child, $invitation, 'accept');
            $this->fail('Expected missing staged verification document to prevent acceptance.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('A staged verification document is missing.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('parent_child_accounts', [
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
        ]);
        $this->assertSame('pending', $invitation->fresh()->status->value);
        $this->assertSame($documents, $invitation->fresh()->relationship_verification_documents);
    }

    public function test_acceptance_restores_staged_documents_when_document_creation_fails(): void
    {
        $this->seedLocationRows();
        Storage::fake('local');

        $parent = $this->createApprovedParent();
        $child = $this->createLearner('rollbackstagedchild', 12);
        $stagedPath = 'guardian-relationship-invitations/rollback/court-order.pdf';
        Storage::disk('local')->put($stagedPath, 'court order');
        $documents = [[
            'document_type' => 'court_order',
            'disk' => 'local',
            'path' => $stagedPath,
            'original_name' => 'court-order.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 11,
        ]];
        $invitation = ParentChildInvitation::query()->create([
            'inviter_parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'invite_token' => (string) \Illuminate\Support\Str::uuid(),
            'relationship_type' => 'legal_guardian',
            'relationship_verification_documents' => $documents,
            'status' => 'pending',
            'expires_at' => now()->addDays(3),
        ]);
        \App\Models\GuardianRelationshipVerificationDocument::creating(static function (): void {
            throw new \RuntimeException('Unable to create verification document.');
        });

        try {
            app(ParentChildInvitationService::class)->respondToInvitation($child, $invitation, 'accept');
            $this->fail('Expected verification document creation to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Unable to create verification document.', $exception->getMessage());
        } finally {
            \App\Models\GuardianRelationshipVerificationDocument::flushEventListeners();
        }

        Storage::disk('local')->assertExists($stagedPath);
        Storage::disk('local')->assertDirectoryEmpty('guardian-relationship-verifications');
        $this->assertDatabaseMissing('parent_child_accounts', [
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
        ]);
        $this->assertSame('pending', $invitation->fresh()->status->value);
        $this->assertSame($documents, $invitation->fresh()->relationship_verification_documents);
    }

    public function test_acceptance_logs_failed_staged_document_compensation_without_masking_the_original_exception(): void
    {
        $this->seedLocationRows();

        $parent = $this->createApprovedParent();
        $child = $this->createLearner('rollbackfailurechild', 12);
        $source = 'guardian-relationship-invitations/rollback/court-order.pdf';
        $documents = [[
            'document_type' => 'court_order',
            'disk' => 'local',
            'path' => $source,
            'original_name' => 'court-order.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 11,
        ]];
        $invitation = ParentChildInvitation::query()->create([
            'inviter_parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'invite_token' => (string) \Illuminate\Support\Str::uuid(),
            'relationship_type' => 'legal_guardian',
            'relationship_verification_documents' => $documents,
            'status' => 'pending',
            'expires_at' => now()->addDays(3),
        ]);
        $filesystems = app('filesystem');
        $logger = app('log');
        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('exists')->with($source)->twice()->andReturn(true, false);
        $disk->shouldReceive('exists')
            ->with(\Mockery::on(static fn (string $path): bool => $path !== $source))
            ->once()
            ->andReturn(true);
        $disk->shouldReceive('move')->with($source, \Mockery::type('string'))->once()->andReturn(true);
        $disk->shouldReceive('move')->with(\Mockery::type('string'), $source)->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        Log::shouldReceive('error')
            ->once()
            ->with(
                'Unable to restore staged verification document after submission failure.',
                \Mockery::on(static fn (array $context): bool => $context['source'] === $source && $context['destination'] !== $source),
            );
        \App\Models\GuardianRelationshipVerificationDocument::creating(static function (): void {
            throw new \RuntimeException('Unable to create verification document.');
        });

        try {
            app(ParentChildInvitationService::class)->respondToInvitation($child, $invitation, 'accept');
            $this->fail('Expected verification document creation to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Unable to create verification document.', $exception->getMessage());
        } finally {
            \App\Models\GuardianRelationshipVerificationDocument::flushEventListeners();
            Storage::swap($filesystems);
            Log::swap($logger);
        }

        $this->assertSame('pending', $invitation->fresh()->status->value);
        $this->assertSame($documents, $invitation->fresh()->relationship_verification_documents);
    }

    public function test_child_can_accept_invitation_and_create_parent_link(): void
    {
        $this->seedLocationRows();

        $parent = $this->createApprovedParent();
        $child = $this->createLearner('acceptchild', 11);

        $invitation = ParentChildInvitation::query()->create([
            'inviter_parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'invite_token' => (string) \Illuminate\Support\Str::uuid(),
            'relationship_type' => 'grandmother',
            'status' => 'pending',
            'expires_at' => now()->addDays(3),
        ]);

        $this->actingAs($child)
            ->post(route('parent.invitations.respond', $invitation), [
                'decision' => 'accept',
            ])
            ->assertRedirect(route('parent.invitations.show', $invitation));

        $invitation->refresh();
        $this->assertSame('accepted', $invitation->status->value);

        $this->assertDatabaseHas('parent_child_accounts', [
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'verification_status' => 'pending',
        ]);

        $link = ParentChildAccount::query()
            ->where('parent_user_id', $parent->id)
            ->where('child_user_id', $child->id)
            ->first();

        $this->assertNull($link?->relationship_verified_at);
        $this->assertTrue((bool) $link?->can_approve_content);
        $this->assertSame('grandmother', $link?->relationship_type);
        $this->assertSame('pending', $link?->relationship_status);
        $this->assertSame('not_required', $link?->relationship_verified_status);
    }

    public function test_child_can_reject_invitation(): void
    {
        $this->seedLocationRows();

        $parent = $this->createApprovedParent();
        $child = $this->createLearner('rejectchild', 13);

        $invitation = ParentChildInvitation::query()->create([
            'inviter_parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'invite_token' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'pending',
            'expires_at' => now()->addDays(3),
        ]);

        $this->actingAs($child)
            ->post(route('parent.invitations.respond', $invitation), [
                'decision' => 'reject',
                'note' => 'I will keep my current setup.',
            ])
            ->assertRedirect(route('parent.invitations.show', $invitation));

        $invitation->refresh();
        $this->assertSame('rejected', $invitation->status->value);

        $this->assertDatabaseMissing('parent_child_accounts', [
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
        ]);

        $this->actingAs($parent)
            ->post(route('parent.invitations.store'), [
                'identifier' => $child->learnerProfile->username,
                'relationship_type' => 'grandmother',
            ])
            ->assertRedirect(route('parent.invitations.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('parent_child_invitations', [
            'inviter_parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'status' => 'pending',
        ]);
    }

    public function test_my_children_page_shows_outgoing_invitation_status(): void
    {
        $this->seedLocationRows();

        $parent = $this->createApprovedParent();
        $child = $this->createLearner('statuschild', 10);

        ParentChildInvitation::query()->create([
            'inviter_parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'invite_token' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($parent)
            ->get(route('parent.children.index'))
            ->assertOk()
            ->assertSee('Latest Guardian Link Invitation')
            ->assertSee($child->name)
            ->assertSee('Pending');
    }

    public function test_parent_can_view_full_invitation_history_page(): void
    {
        $this->seedLocationRows();

        $parent = $this->createApprovedParent();
        $firstChild = $this->createLearner('historychildone', 10);
        $secondChild = $this->createLearner('historychildtwo', 11);

        ParentChildInvitation::query()->create([
            'inviter_parent_user_id' => $parent->id,
            'child_user_id' => $firstChild->id,
            'invite_token' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        ParentChildInvitation::query()->create([
            'inviter_parent_user_id' => $parent->id,
            'child_user_id' => $secondChild->id,
            'invite_token' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'rejected',
            'responded_at' => now()->subDay(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($parent)
            ->get(route('parent.invitations.history'))
            ->assertOk()
            ->assertSee('Guardian Invitation History')
            ->assertSee($firstChild->name)
            ->assertSee($secondChild->name)
            ->assertSee('Pending')
            ->assertSee('Rejected');
    }

    public function test_parent_can_invite_older_dependent_learner(): void
    {
        $this->seedLocationRows();

        $parent = $this->createApprovedParent();
        $adultLearner = $this->createLearner('adultlearner', 20);

        $this->actingAs($parent)
            ->from(route('parent.invitations.index'))
            ->post(route('parent.invitations.store'), [
                'identifier' => $adultLearner->email,
                'relationship_type' => 'biological_mother',
            ])
            ->assertRedirect(route('parent.invitations.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('parent_child_invitations', [
            'inviter_parent_user_id' => $parent->id,
            'child_user_id' => $adultLearner->id,
            'relationship_type' => 'biological_mother',
            'status' => 'pending',
        ]);
    }

    private function createApprovedParent(): User
    {
        $parent = User::factory()->create([
            'first_name' => 'Parent',
            'last_name' => 'Account',
            'birthdate' => now()->subYears(35)->toDateString(),
            'email_verified_at' => now(),
            'is_parent_registration' => true,
            'parent_verification_status' => 'approved',
            'role' => 'learner',
        ]);
        $parent->assignRole('learner');

        LearnerProfile::query()->create([
            'user_id' => $parent->id,
            'username' => 'parent' . $parent->id,
            'birthdate' => now()->subYears(35)->toDateString(),
            'gender' => 'female',
            'city_code' => '402101000',
            'barangay_code' => '402101001',
            'barangay' => 'Sample Barangay',
            'province_code' => '402100000',
            'is_parent_account' => true,
            'requires_parental_consent' => false,
        ]);

        return $parent;
    }

    private function createLearner(string $username, int $age): User
    {
        $learner = User::factory()->create([
            'first_name' => ucfirst($username),
            'last_name' => 'Learner',
            'birthdate' => now()->subYears($age)->toDateString(),
            'email_verified_at' => now(),
            'role' => 'learner',
        ]);
        $learner->assignRole('learner');

        LearnerProfile::query()->create([
            'user_id' => $learner->id,
            'username' => $username . $learner->id,
            'birthdate' => now()->subYears($age)->toDateString(),
            'gender' => 'male',
            'city_code' => '402101000',
            'barangay_code' => '402101001',
            'barangay' => 'Sample Barangay',
            'province_code' => '402100000',
            'is_parent_account' => false,
            'requires_parental_consent' => true,
        ]);

        return $learner;
    }

    private function seedLocationRows(): void
    {
        DB::table('provinces')->updateOrInsert(
            ['code' => '402100000'],
            [
                'name' => 'Sample Province',
                'region_code' => '040000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('cities')->updateOrInsert(
            ['code' => '402101000'],
            [
                'name' => 'Sample City',
                'region_code' => '040000000',
                'province_code' => '402100000',
                'is_city' => true,
                'city_class' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('barangays')->updateOrInsert(
            ['code' => '402101001'],
            [
                'name' => 'Sample Barangay',
                'city_code' => '402101000',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
