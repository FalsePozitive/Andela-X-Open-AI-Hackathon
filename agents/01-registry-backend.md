# Agent 01 — Registry and backend

Read [AGENTS.md](../AGENTS.md), [SPEC.md](../docs/SPEC.md), [community tools](../docs/COMMUNITY-TOOLS.md) and [testing guide](../docs/TESTING.md). This brief describes the implemented PHP application, including the user-approved account and community extensions.

## Responsibility and files

Maintain the schema, atomic registry, safe input boundaries, private media storage, account lifecycle, access checks and PHP routes. Own `database/migrations/`, `src/Database.php`, `src/Input.php`, `src/Accounts.php`, `src/Media.php` and registry/routing infrastructure in `src/Registry.php` and `src/Web.php`. Coordinate IDs/public projection in `src/Rules.php`, revisions in `src/StoryChanges.php`, exports and packaging with the other roles.

`Database.php` provides PDO connections, parameterized queries and versioned migrations; it is not a client singleton. Implemented services replace the original proposed Next.js/Prisma/Zod files.

## Registry and privacy

1. Preserve short codes from `23456789ABCDEFGHJKLMNPQRSTUVWXYZ`, four characters, hyphen, four characters. Let the database unique key arbitrate collisions and retry actual code collisions.
2. Store SHA-256 content hashes. Text corrections update the current hash and retain earlier snapshots. Original uploaded media and its registered hash are not changed by QR derivatives.
3. Create the creator, story, contributors, purpose-specific consents, declared terms, exact shares and first revision atomically. Invalid confirmation, media or percentages must leave no partial registration or orphan upload.
4. Validate public percentage strings/integers using `Input::percentage()` and shared `Rules::split()`. Require 100% total. Store integer basis points internally and integer minor-unit money; do not create a separate nonexistent ledger-validator module.
5. Apply `Rules::publicStory()` as an allowlist. Public content requires OPEN + ACTIVE + every contributor's PUBLISH consent. Hidden projections omit title, text/media, context, place, collection details and private contributors. Apply the same gate before reading cached media, credit downloads and PDF exports.
6. `story()` is a raw internal record; protected handlers use `privateStory()`. Private lists and object mutations must enforce community scope. Public search must search the safe projection rather than revealing hidden-field matches.
7. Limit uploads to 10 MB and JPEG/PNG/WebP/MP3/WAV/MP4/WebM by inspected content and MIME. Enforce the 16-megapixel image limit, re-encode images and use generated private filenames. Never trust a supplied path or filename.

## Accounts and authorization

Individual database accounts supersede the former demo-flag-only rule. The first valid legacy login creates the site owner once under a lock; the bootstrap state prevents any later configuration-password fallback. Require active accounts and matching session `auth_version`; invalidate legacy flag-only sessions.

- Owner: all collections, account recovery/disable, new collections and story transfers.
- Community ADMIN: maintain records and manage accounts/memberships within administered collections. Adding an existing unrelated account requires the owner.
- STEWARD: maintain assigned records and decide requests.
- REVIEWER: read assigned private records/history, without writes.

Check roles on the server for direct links, APIs, lists, requests, claims, history, ledger corrections and notification retries. Re-read membership/account state on subsequent operations. Password changes and disabling must invalidate sessions. Web routes always set `Registry::$actor`; `null` means a trusted CLI/test operation and is never a public caller.

Retain server-side sessions, CSRF protection on writes, escaped output, production HTTPS enforcement, no-store private responses and consistent typed API errors. Do not expose hashes, tokens, secrets or private paths in errors. Accounts and declared community roles do not verify identity or authority.

## Routing and upgrade interfaces

Preserve public `/p/{id}`, `/media/{id}`, `/api/stories/{id}`, QR/PDF/cover/download/credit routes, and request/claim submission. Preserve steward story creation, private records, consent/status/edit, history, request decisions, claims and ledger interfaces. Current additional screens are account, team and notifications under `/dashboard`; tracking uses private `/r/{token}` and CSRF-protected `/api/tracking/{token}`. Refer to `src/Web.php` for exact methods and paths; do not invent creator-management endpoints.

Keep visible financial labels and validation messages consistent with Demo / no real money moves; retain internal ledger kinds.

Keep `001_registry.sql` and `002_community_tools.sql` as released schema history. Future changes require new migrations. Migration 002 assigns existing stories to Existing story collection and preserves old rows and snapshots. Story edits require a reason/current revision; historical snapshots remain private. Acquire the story lock before approval reads establish a snapshot, in coordination with role 02.

Xneelo uses PHP 8.3, Apache and MySQL/MariaDB without SSH. Locally compile dependencies/assets and produce the shared-host overlay. Private files live under denied `_passport/` when above-root storage is unavailable. Preserve configuration, uploads, `.htaccess` and database data; never seed/reset on deployment. SMTP settings belong in private `config/local.php`, not source or public assets.

## Verification and handover

Run the relevant registry, community, HTTP, media, transport and upgrade tests from the isolated setup in the testing guide, plus syntax and static checks. Include atomic rejection, role isolation, legacy-session rejection, invalid upload content, post-withdrawal cache protection, stale edits, simultaneous approval and migration preservation. Record hosted storage/rewrite verification separately.

Document API/schema changes and affected ownership areas. Provide non-destructive SQL, the matching ready-to-upload release and an operator upgrade path. Do not mark the hosted system verified based only on local tests.
