# Task 2 Report: Registry, Configuration Validation, and Native Shuffling

## Implementation

- Added the `InteractiveActivityHandler` contract with the required handler, normalization, learner-payload, evaluation, fingerprint, and preview APIs.
- Added `InteractiveActivityRegistry`, which resolves Matching and Sequencing handlers from either `InteractiveActivityType` or its backed string value and rejects unsupported types.
- Added strict, typed Matching and Sequencing handlers. They validate schema version 1 and text envelopes, normalize display values, reject normalized duplicates, preserve only IDs found in stored configuration, generate server UUIDs for new IDs, and produce canonical configuration shapes.
- Matching accepts 2–12 pairs; Sequencing accepts 3–12 items and assigns continuous one-based `correct_position` values.
- Both handlers use native `Random\Randomizer`; initial orders are deterministically shuffled and rotated/reversed if the shuffle would be canonical.
- Learner payloads exclude Matching pair relationships and Sequencing `correct_position`; evaluations reject unknown, missing, and duplicate IDs before updating state.
- Answer fingerprints use normalized canonical answer material, excluding UUIDs and display-only metadata.

## TDD evidence

### Existing RED evidence retained

Before changing the interrupted suite, focused PHPUnit reported:

```text
FAILURES!
Tests: 8, Assertions: 31, Failures: 2.
```

Both failures expected pre-normalization client IDs, while normalization intentionally replaces unrecognized IDs with server UUIDs. The tests were corrected to derive learner IDs and deterministic expected orders from the normalized configuration. JSON secrecy assertions and rejection assertions remain in place.

### New RED/GREEN cycle

Added coverage requiring the Matching and Sequencing envelope `kind` fields to be supplied and equal to `text`.

RED:

```text
FAILURES!
Tests: 8, Assertions: 41, Failures: 1.
```

The missing-kind configuration was accepted because the rules used `sometimes`.

GREEN:

```text
OK (8 tests, 42 assertions)
```

The minimal implementation change made `kind` required with `in:text` in both handlers.

## Verification

- `php vendor/bin/phpunit tests/Unit/Services/Learning/InteractiveActivityHandlerTest.php --do-not-cache-result`
  - Passed: 8 tests, 42 assertions.
- `vendor/bin/pint app/Contracts/Learning/InteractiveActivityHandler.php app/Services/Learning/InteractiveActivities/InteractiveActivityRegistry.php app/Services/Learning/InteractiveActivities/MatchingActivityHandler.php app/Services/Learning/InteractiveActivities/SequencingActivityHandler.php tests/Unit/Services/Learning/InteractiveActivityHandlerTest.php`
  - Passed; it fixed two spacing issues in the handler files.

`php artisan test` could not launch its child test process in this environment because Symfony reported its supplied `C:\Users\Jaded\ConciousConnections` cwd as nonexistent. Direct PHPUnit against the same focused test file completed successfully.

## Files

- `app/Contracts/Learning/InteractiveActivityHandler.php`
- `app/Services/Learning/InteractiveActivities/InteractiveActivityRegistry.php`
- `app/Services/Learning/InteractiveActivities/MatchingActivityHandler.php`
- `app/Services/Learning/InteractiveActivities/SequencingActivityHandler.php`
- `tests/Unit/Services/Learning/InteractiveActivityHandlerTest.php`
- `.superpowers/sdd/task-2-report.md`

## Self-review

Reviewed the implementation against every Task 2 requirement:

- Registry enum/string resolution and unregistered rejection: covered and implemented.
- Bounds, normalized duplicate detection, schema validation, text envelope validation, existing-ID preservation, and server UUID replacement: covered and implemented.
- Sequencing position canonicalization, deterministic non-canonical shuffle behavior, learner secrecy, evaluation input rejection, and canonical fingerprints: covered and implemented.
- All new PHP files declare strict types; shuffling uses `Random\Randomizer`; no dependencies were added.

## Concerns

No Task 2 implementation concerns. The Artisan test-launch cwd issue is environmental; focused direct PHPUnit is green.
