# Agent 05 — QA and safety review

Read [AGENTS.md](../AGENTS.md), [SPEC.md](../docs/SPEC.md), [community tools](../docs/COMMUNITY-TOOLS.md) and [step-by-step testing guide](../docs/TESTING.md). Use the guide as the current acceptance procedure; earlier entries in `docs/REVIEW.md` are dated checkpoints, not automatic evidence for a new release.

## Responsibility and files

Review all subsystems and maintain `tests/`, the acceptance guide and review evidence. Coordinate fixes with the relevant role; a user-authorized integrated task can include fixes across areas when documented. Do not report a defect fixed without verifying it, or block ordinary authorized work merely because a filename is shared.

## Release-critical checks

### Consent, revisions and financial records

- Commercial approval requires all commercial consent; AI approval requires the declared license flag and all AI consent. Missing/refused consent must block.
- Public content and all new approvals require publication consent. PAUSED/WITHDRAWN sharing blocks requests/approvals; RESTRICTED sensitivity hides content without automatically blocking private requests. Regranting consent must not unpause sharing.
- Story edits require reasons/current revisions, retain old private snapshots, reject stale/simultaneous overwrite attempts and reset declared-review badges appropriately.
- Terms/shares/consent changes preserve approval snapshots and original ledger entries. Corrections append signed, referenced entries. Contributor/currency balances stay separate.
- Percentage inputs total 100% with no more than two decimal places. Integer allocation floors, then distributes remainder by descending share and stable ID tie-break. Check tiny, zero, uneven and randomized amounts.
- Repeated and simultaneous approvals never duplicate allocations. An approval waiting on a consent withdrawal must read the latest state after acquiring the story lock.
- Countering/accepting alone creates no allocation; reject stale/repeated responses and mismatched final fees after an accepted offer.

### Privacy and roles

- Restricted, paused, withdrawn or missing-publication-consent records expose no private title, content, context, location, contributors, credit or storage path in JSON, HTML/source, media, search or PDF, even after public caches exist.
- Check logged-out access, unrelated-community access, reviewer writes, owner/community-admin boundaries, notification retries, private versions, ledger corrections and direct endpoint calls. Hiding navigation is not authorization.
- Legacy flag-only sessions and post-bootstrap configuration-password fallback are rejected. Password changes/disable invalidate sessions; membership updates apply on subsequent operations.
- Request tracking tokens expire after 30 days; a link gives access to only its request and no contributor approval snapshot. Treat links as private credentials and avoid them in shared evidence.
- CSRF, output escaping, unsafe filenames, over-limit files, forged MIME, negative/invalid fees and atomic failure paths are checked in a disposable local environment.
- No app viewer tracking or screenshot scanning. Private storage/configuration/vendor/SQL/backups are denied direct access on the actual Apache host.

### Media, copy and usability

- Images retain a decodable bottom-right passport QR after download; originals remain private. Video browser export retains the mark and sound, cancellation works, and original playback is not falsely described as marked.
- Audio ZIP has sound, QR cover, instructions and attribution; do not claim an audible/embedded QR.
- Public PDF snapshots match CI, current version, public consent/terms/shares and attribution. Hidden PDFs stay minimal; long text is a labelled excerpt. Inspect page breaks after layout changes.
- Okwethu goldenrod CI and logo spacing remain consistent; copy says Demo / no real money moves. Review badges never imply verified ownership or authority.
- At 360px, check overflow, wrapping, labels, focus, keyboard-only forms, readable notices, slow-network errors and clear saved state. Inspect real browser output; DOM tests alone are not visual/accessibility verification.
- About and FAQ match actual roles, withdrawal, private tracking, email and export behavior. Fixtures are explicitly fictional and contain no invented cultural claims.

### Notifications and deployment

SMTP is optional and disabled by default. Sender is `steward@okwethu.wilmans.co.za`, configured privately under `_passport/config/local.php` on Xneelo. Use local spool for automated tests; sending a real email needs an explicitly authorized test recipient. Queue failure must not roll back a request/approval. Retry scope, lease, five-attempt limit, 29-day sending limit and truthful disabled/failed/sent status must hold. Sent means server acceptance, not proven inbox arrival.

Existing sites import the separate migration-002 upgrade once after backup and then upload the matching shared-host ZIP. No automatic reset/seed. Verify HTTPS, Apache denial/rewrite rules, actual extensions/database access, phone QR, controlled SMTP delivery and persistent redeployment on hosting. A destructive restore drill belongs on a clone, never an active community database.

## Automated checks and evidence

Use the testing guide's local setup: test database `story_passport_test` on loopback port 3317, private fixture storage and `PASSPORT_CONFIG=config/test.php`. Upgrade tests need permission to create/drop only randomly named temporary test databases. HTTP tests use `PASSPORT_TEST_URL=http://127.0.0.1:8088`; run the HTTP checks before `node tests/wizard.cjs`, which consumes the generated fixture.

Run syntax, PHPStan, appropriate PHPUnit suites, wizard DOM checks, the asset build and current package checks. `PackageTest` must inspect a fresh production ZIP; an old artifact or skipped package test is not release evidence. Keep Composer production autoloading free of development dependencies and archive paths free of configuration/uploads/secrets.

The previous community-tools implementation reported 47 passing tests, plus local browser and PDF checks. This is a historical baseline, not a promise that tests were rerun for a documentation-only edit. Record the current command/result/skip count, build identifier, date, browser and environment each time verification is actually performed.

Use this finding format:

```text
Test ID / area:
Environment and version:
Observed result and evidence:
Expected result:
Severity: blocker | major | minor
Owner: registry | rules | frontend | demo | deployment
Status: open | fixed and verified | deferred with reason
```

Restricted-content leaks, broken role boundaries, incorrect allocations/duplicate payouts, unsafe upgrades and false ownership/enforcement claims are blockers. Record Pass, Fail, Blocked or Not run for manual cases. Never convert missing hosting/mailbox access into a pass. Complete when required checks have evidence, blockers are resolved, and any remaining limits are explicitly reported to the user.
