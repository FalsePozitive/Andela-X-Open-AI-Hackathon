# Agent 02 — Consent, requests and community ledger

Read [AGENTS.md](../AGENTS.md), [SPEC.md](../docs/SPEC.md), [community tools](../docs/COMMUNITY-TOOLS.md) and [testing guide](../docs/TESTING.md).

## Responsibility and files

Own business rules in `src/Rules.php`, consent/request/ledger behavior in `src/Registry.php`, revisions in `src/StoryChanges.php`, request links/events in `src/RequestUpdates.php`, and notification behavior in `src/Mailbox.php`. Share route, schema, input and template changes with registry/frontend roles. Cover rules with `tests/RulesTest.php`, `tests/RegistryTest.php`, `tests/CommunityToolsTest.php` and their concurrency workers. There are no separate `ledger.php`, `consent.php` or `license.php` service modules.

## Exact shares and money

Users enter percentages totalling exactly 100%, with at most two decimal places. `Input::percentage()` converts them to integer basis points; `Rules::split()` validates unique contributors, nonnegative integer shares and an exact internal total of 10,000. Require explicit confirmation of terms and shares at registration and when changing them.

`Rules::allocate()` floors each amount × share / 10,000, then distributes remaining minor units in descending share order, tied by ascending stable contributor ID. Use integer arithmetic. Test zero, tiny amounts, uneven shares, ties and randomized totals. For 70%/30% and seven cents, the allocation is five cents/two cents.

Group balances by contributor **and currency**; never add ZAR and USD together. Use ZAR by default. The registration preview takes ordinary currency amounts; request, counteroffer and signed correction fields use integer minor units. Explain this distinction in copy.

## Consent and visibility

- Store append-only choices separately for RECORD, PUBLISH, TRANSLATE, COMMERCIAL and AI_TRAINING. Latest choice applies; absent/refused means not granted.
- Public content requires OPEN sensitivity, ACTIVE sharing and every contributor's publication consent. Missing/withdrawn PUBLISH consent hides content, including cached exports.
- Every approval needs ACTIVE sharing and publication consent. Commercial use also needs every contributor's COMMERCIAL consent. AI use needs both the license flag and every contributor's AI_TRAINING consent.
- Restricted sensitivity hides public content but does not itself stop a private permission request or approval. PAUSED/WITHDRAWN sharing stops new requests and approvals. Regranting consent does not undo an explicit pause.
- Explicit AI intent and the conservative keyword detector both trigger review. Explain false positives/negatives; a flag is not proof.
- Preserve old approvals and allocations when consent or terms change. The app cannot recall earlier downloads or enforce withdrawal outside its records.

## Corrections and revisions

The current editor changes story details/text, declared terms, existing contributor shares, preferred credit, sharing/visibility and review status. It retains contributor identities and original uploads. Give every web change a reason and integer `expectedRevision`; lock the story, reject stale versions and capture a private snapshot atomically. Story/terms/consent changes reset review badges. Legacy stories begin with their retained current state, without invented history.

Snapshots can contain private data: authorize access using the current story's collection and never place snapshots in public history or requester tracking responses. Old approvals retain the terms, consent and shares used at approval.

## Decisions and ledger integrity

`Registry::decide()` supports approve, decline and counter. A counter requires a proposed fee and explanation but produces no allocations. Requesters can accept or decline the current offer through a private link. Acceptance is not approval. Reject stale/repeated replies; an accepted offer's final fee must match unless the steward makes a new counteroffer.

For approval require explicit final-fee confirmation, including zero; noncommercial approvals cannot allocate a nonzero fee. Lock request and story consistently and acquire the story lock before a consistent snapshot read. Recheck consent after waiting for an in-progress consent update. Commit the approval snapshot and allocations together. Repeated/concurrent approval must return an existing result without duplicate allocations; preserve the unique payment key constraint.

Ledger rows are append-only. `Registry::adjust()` appends a nonzero signed adjustment with a reason and original-entry reference; positive and negative corrections are supported. Never silently rewrite an original allocation. Internal kinds remain `MOCK_PAYMENT` / `MOCK_ADJUSTMENT`; all visible money displays use **Demo · no real money moves**.

## Private tracking and optional email

Tracking links are random bearer credentials expiring after 30 days. Store token hashes; a link holder may read and respond only to that request, not inspect contributor approval snapshots or other requests. Fresh links do not invalidate earlier unexpired ones. Authorized stewards can generate a fresh link to share privately with the recorded requester.

Queue opted-in requester notifications within the business transaction and attempt delivery after commit. Failed delivery must never undo the saved decision. Configure SMTP under `mail` in protected `_passport/config/local.php` on the shared host; sender `steward@okwethu.wilmans.co.za`. Disabled delivery still leaves usable private tracking links and queued updates. Use a private local spool for tests, never real external recipients without authorization.

Outbox retries are community-scoped, limited to five attempts, with leases protecting concurrent delivery. Messages older than 29 days cannot be sent; clear sent bodies. Mail-server acceptance is not guaranteed inbox arrival and ambiguous failures may duplicate a retry. No hosted background cron is required. Preserve truthful pending/failed/disabled status.

## Completion checks

Follow the testing guide's request, withdrawal, role and ledger cases. Cover missing/refused consent, both AI gates, pause/regrant behavior, stale edits/counters, matching accepted fees, expiry, optional mail, currency isolation, signed corrections, immutable snapshots, repeated/simultaneous approvals and an approval waiting on withdrawal. Share human-readable blocking reasons with frontend and keep all private endpoints community-scoped.
