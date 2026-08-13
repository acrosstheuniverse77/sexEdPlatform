# Task 7: Verify chat authorization and complete workflow

## Scope and outcome

- Added `test_learner_direct_chat_requires_a_fully_verified_parent_child_relationship` to `tests/Feature/Chat/ChatChannelAuthorizationTest.php`.
- The test proves a pending parent-child relationship is denied, approval without `relationship_verified_at` remains denied, and approval plus a timestamp is allowed.
- The RED run reproduced a production defect: `ChatAuthorizationService::hasApprovedParentChildRelation()` accepted `verification_status = approved` without requiring `relationship_verified_at`.
- Applied the minimal scoped correction in `app/Services/Chat/ChatAuthorizationService.php`: `->whereNotNull('relationship_verified_at')`.

## TDD evidence

1. RED — `vendor\\bin\\phpunit --do-not-cache-result tests\\Feature\\Chat\\ChatChannelAuthorizationTest.php`

   Exit code: 1

   Exact summary:

   ```text
   There was 1 failure:
   1) Tests\Feature\Chat\ChatChannelAuthorizationTest::test_learner_direct_chat_requires_a_fully_verified_parent_child_relationship
   Failed asserting that true is false.
   C:\Users\Jaded\ConciousConnections\tests\Feature\Chat\ChatChannelAuthorizationTest.php:96
   FAILURES!
   Tests: 4, Assertions: 12, Failures: 1.
   ```

2. GREEN — same command after the minimal correction.

   Exit code: 0

   ```text
   OK (4 tests, 13 assertions)
   ```

## Required verification

All commands were run sequentially.

| Command | Exit | Exact result |
| --- | ---: | --- |
| `php artisan test tests/Feature/Parent/ParentChildInvitationFlowTest.php tests/Feature/Auth/ParentChildVerificationResubmissionTest.php tests/Feature/Admin/AdminParentChildVerificationModerationWorkflowTest.php tests/Feature/Admin/AdminParentChildVerificationUiTest.php tests/Feature/Chat` | 0 | `Tests: 95 passed (581 assertions)`; `Duration: 37.15s` |
| `vendor\\bin\\phpunit --do-not-cache-result` (first attempt) | 124 | `command timed out after 124012 milliseconds`; no PHPUnit result output was emitted before the harness timeout. |
| `vendor\\bin\\phpunit --do-not-cache-result` (rerun with extended timeout) | 0 | `OK (917 tests, 4111 assertions)`; `Time: 02:45.562, Memory: 250.00 MB` |
| `vendor\\bin\\pint --test` in sandbox | 1 | `The path "C:\Users\Jaded\ConciousConnections" is not readable.` |
| `vendor\\bin\\pint --test` outside sandbox | 1 | `FAIL ... 1109 files, 668 style issues`; repository-wide formatting debt, not modified by Task 7. |
| `vendor\\bin\\pint --test app\\Services\\Chat\\ChatAuthorizationService.php tests\\Feature\\Chat\\ChatChannelAuthorizationTest.php` | 1 | `FAIL ... 2 files, 1 style issue`; output was `⨯.`: the test file passed, while the existing service file has a style issue. |
| `vendor\\bin\\pint --test tests\\Feature\\Chat\\ChatChannelAuthorizationTest.php` | 0 | `PASS ... 1 file` |
| `vendor\\bin\\phpstan analyse --level=9` | 1 | Unavailable: PowerShell could not resolve `vendor\\bin\\phpstan`; `vendor\\bin\\phpstan` and `vendor\\bin\\phpstan.bat` both do not exist. No static-analysis coverage is claimed. |

## Diff and review

- `git diff --check` exited 1 due to pre-existing unrelated whitespace errors in `resources/views/auth/create-child-account.blade.php:231` and `resources/views/auth/parent-registration-required.blade.php:94`; it also printed unrelated CRLF warnings.
- `git diff --check -- app/Services/Chat/ChatAuthorizationService.php tests/Feature/Chat/ChatChannelAuthorizationTest.php` exited 0.
- Final code review traced the regression test through `evaluateStart()` to `hasApprovedParentChildRelation()`. The change affects only learner-to-learner direct-chat eligibility and requires the same two fields used by `ParentChildAccount::isVerified()`.
- Pre-existing dirty changes were preserved. Only the Task 7 regression test, its necessary production correction, and this report are intended for the Task 7 commit.

## Follow-up review fixes

### Scope and behavior

- `GuardianRelationshipVerificationService::submitStaged()` now accepts an optional typed `Closure` executed after the under-review transition, inside its transaction and restoration `try` block.
- Proof-required invitation acceptance updates its accepted status and clears staged metadata through that callback. A thrown invitation update now restores moved files before the outer transaction rolls back.
- Acceptance reloads the invitation with `lockForUpdate()`, revalidates pending/expiry state, locks the learner before link lookup, and locks the existing link lookup.
- Empty or null staged proof metadata now raises `A staged verification document is missing.` before any relationship creation attempt, under-review transition, or notification.
- Restored/deleted links clear `verification_document_path` with the other child-verification state.
- Failure-injection tests now install a temporary model event dispatcher and restore the saved dispatcher in `finally`; no test calls `flushEventListeners()`.

### TDD evidence

Initial RED — `vendor\\bin\\phpunit --do-not-cache-result tests\\Feature\\Parent\\ParentChildInvitationFlowTest.php`

```text
There were 4 failures:
1) empty staged proof metadata was accepted.
2) accepted invitation update failure left the source staged file missing.
3) a stale invitation decision was accepted.
4) a restored deleted link retained child-verifications/legacy-child.pdf.
FAILURES!
Tests: 19, Assertions: 113, Failures: 4.
```

Strengthened empty-proof ordering RED:

```text
1) Tests\Feature\Parent\ParentChildInvitationFlowTest::test_accepting_proof_required_invitation_with_empty_staged_documents_leaves_state_unchanged
Failed asserting that true is false.
FAILURES!
Tests: 1, Assertions: 7, Failures: 1.
```

The final strengthened focused regression passed: `OK (1 test, 7 assertions)`.

### Follow-up verification

All PHPUnit invocations were direct and sequential with `--do-not-cache-result`.

| Command | Exit | Exact result |
| --- | ---: | --- |
| Each of the four focused regressions | 0 | `OK (1 test, 6 assertions)`, `OK (1 test, 6 assertions)`, `OK (1 test, 3 assertions)`, and `OK (1 test, 2 assertions)` |
| `vendor\\bin\\phpunit --do-not-cache-result tests\\Feature\\Parent\\ParentChildInvitationFlowTest.php` | 0 | Final run: `OK (19 tests, 126 assertions)`; `Time: 00:29.772` |
| Task 7 affected suites via direct PHPUnit | 0 | `OK (99 tests, 598 assertions)`; `Time: 00:37.667` |
| `vendor\\bin\\phpunit --do-not-cache-result` | 0 | Final run: `OK (921 tests, 4129 assertions)`; `Time: 02:17.957, Memory: 250.00 MB` |
| `vendor\\bin\\pint --test tests\\Feature\\Parent\\ParentChildInvitationFlowTest.php` | 0 | `PASS ... 1 file` |
| Final scoped Pint for both services plus the test | 1 | `FAIL ... 3 files, 2 style issues`; the test passed, while both services retain pre-existing style issue groups. |
| `git diff --check --` for the two services and invitation-flow test | 0 | No whitespace errors. |

The first multi-name `--filter` attempt did not execute PHPUnit because the shell parsed `|` as a pipeline; it exited 1 before tests started. It was replaced by sequential direct PHPUnit commands above.
