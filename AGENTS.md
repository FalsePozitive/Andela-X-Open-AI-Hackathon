# AGENTS.md — Okwethu

Read this file, the relevant brief in `agents/`, [the current specification](docs/SPEC.md), and [the testing guide](docs/TESTING.md) before changing the app. This is the consolidated guidance for the implemented PHP application. `docs/ORIGINAL-SPEC.md` is historical context; its former stack and scope do not override the user's later decisions.

## Purpose and approved scope

Okwethu means “Ours”. It helps communities name the people behind a story, record declared consent and sharing terms, and demonstrate how an agreed fee would be allocated. Registration records a claim, not proof of ownership. Community roles record delegated access, not verified cultural authority.

The implemented journey is registration → passport and QR → permission request → consent review or counteroffer → approval → demo ledger. The user also approved:

- Story corrections, reasons for changes and private, read-only version history.
- Purpose-specific consent withdrawal, restricted visibility, paused and withdrawn sharing.
- Private request links, online counteroffer responses and optional SMTP request updates.
- Individual accounts, community collections and scoped admin, steward and reviewer roles.
- Public and private search, preferred attribution and credit-text downloads.
- About and FAQ pages, percentage shares, QR-marked image/video exports, audio-cover ZIPs and branded passport PDFs.

Drafts, community voting, real payments, blockchain, ownership or identity verification, viewer analytics, automated rights enforcement and self-service email password reset are outside the implemented scope. Individual accounts are explicitly approved and supersede the earlier fixed-demo-login-only restriction.

## Principles

1. **Describe declared records honestly.** Never imply guaranteed protection, legal enforcement, verified ownership or independently verified identity/authority. Author reviewed and Community endorsed are declared manual reviews.
2. **Keep payments simulated.** Use “Demo” and “no real money moves” on payment-like displays. Preserve the internal `MOCK_PAYMENT` and `MOCK_ADJUSTMENT` values. Do not add financial credentials or payment integrations.
3. **Record consent per person and purpose.** RECORD, PUBLISH, TRANSLATE, COMMERCIAL and AI_TRAINING remain separate. Missing consent is not granted. Do not change a choice merely to bypass a blocked approval.
4. **Protect content through every route.** Public content requires OPEN sensitivity, ACTIVE sharing and publication consent from every contributor. Otherwise expose only the minimal public record and honest status/contact actions. Hide title, content, context, place, private contributor details and storage paths in HTML, JSON, search, media, credits and PDF exports, including after caches have been populated.
5. **Preserve history.** Consent appends dated choices; story changes retain revisions. Existing approval snapshots and ledger rows remain unchanged. Corrections append signed adjustments with a reason and original-entry reference. Earlier downloads cannot be recalled.
6. **Let people decide terms and shares.** Show defaults and require explicit confirmation. A role or badge does not confer cultural authority.
7. **Collect little and keep it private.** No app viewer analytics or screenshot scanning. Keep account details, requester contacts, capability links, SMTP credentials, private files and backups out of public responses, Git and logs where the app controls logging.
8. **Use honest content.** Use clearly labelled fictional placeholders or content with explicit permission. Never fabricate authentic cultural traditions, sacred material, proverbs or real community authority.

## Stack and structure

PHP 8.3, Apache rewrites, PDO with MySQL/MariaDB, server-rendered templates, Tailwind CSS and small JavaScript enhancements. Composer dependencies include `endroid/qr-code`, `dompdf/dompdf` and `phpmailer/phpmailer`. PHPUnit 11 and PHPStan level 5 provide automated checks. Build assets and install production dependencies locally; hosting needs no Node, Composer command or SSH.

| Area | Current implementation |
|---|---|
| Data and validation | `src/Database.php`, `src/Input.php`, `src/Registry.php`, `database/migrations/` |
| Consent, privacy and money rules | `src/Rules.php` |
| Accounts and scoped access | `src/Accounts.php`, actor checks in `src/Registry.php` and `src/Web.php` |
| Corrections and snapshots | `src/StoryChanges.php`, `story_revisions` |
| Request links and notifications | `src/RequestUpdates.php`, `src/Mailbox.php` |
| Media and PDFs | `src/Media.php`, `src/Watermark.php`, `src/PassportPdf.php` |
| Pages and copy | `templates/`, `src/Copy.php`, `resources/copy.php`, `resources/app.css`, `public/assets/` |
| Fictional seed data | `src/Demo.php` |
| Checks and release tooling | `tests/`, `bin/console.php`, `bin/package.php`, `bin/package-community-update.php` |

Keep business rules separate from request handlers and presentation. Validate writes on the server and return `{ error: { code, message, fields? } }` for API failures. Use parameterized database access, sessions, CSRF checks, escaped output and HTTPS in production.

## Money, concurrency and revisions

- The interface accepts percentages from 0 to 100 with at most two decimal places, totalling exactly 100%. `Input::percentage()` converts exact integer/decimal-string inputs to stored integer basis points; 100% is 10,000 internally. Never ask ordinary users to calculate basis points.
- Money remains integer minor units. Request/counteroffer/adjustment fields use minor units; the registration calculator uses ordinary currency amounts. ZAR is the demo default. Group balances by contributor **and currency**.
- Allocate by flooring each share, then give remaining minor units in descending share order with ascending stable contributor ID as tie-break. Never use floating-point money calculations.
- Commercial approval requires every contributor's COMMERCIAL consent. AI approval additionally requires the declared license flag and every contributor's AI_TRAINING consent. All approvals require publication consent and ACTIVE sharing. Restricted sensitivity alone does not block a private permission conversation or approval; it still hides public content.
- Lock the request and story consistently. Acquire the story lock before a consistent read establishes an approval snapshot. Consent and story updates use the same story lock. Approval and allocations commit together, with uniqueness constraints preventing repeated or simultaneous duplicate allocations.
- Web story edits require `expectedRevision` and a reason. Reject stale edits rather than overwriting them. Legacy history begins with the retained current state, not reconstructed earlier edits. Changes reset declared review badges unless explicitly recording a new review status.
- Counteroffers create no allocations. A requester accepting records agreement to the demo fee; a steward still approves after checking current consent. An accepted counteroffer's final fee must match, or a new counteroffer is needed.

## Accounts and request privacy

The first successful configured legacy steward login bootstraps the site-owner database account once. After bootstrap there is no configuration-password fallback. Legacy flag-only sessions are invalid. Password changes and disabling invalidate sessions; membership changes apply on the next operation.

The owner manages all collections/accounts, recovery and story transfers. Community admins create accounts and manage existing memberships within their own collections. Adding an existing unrelated account to a collection requires the owner. Stewards maintain records and decide requests. Reviewers read their assigned private records without writing.

Enforce roles in services/routes, not just navigation. Scope individual records, listings, requests, claims, versions, ledger rows and notification retries. `Registry::$actor === null` is for trusted CLI/test operations only; every web request must set the actual actor or an anonymous empty actor. Use `privateStory()` for protected access; `story()` returns an internal raw record.

Private `/r/{token}` links are bearer credentials with 30-day expiry. Token hashes are stored in `request_tokens`; unsent outbox bodies contain the link until delivery. Do not expose an approval's private contributor snapshot to requesters. Do not claim mailbox acceptance guarantees inbox delivery.

## Brand and exports

Use Okwethu and the existing logo, with “Ours. Together.” beneath the wordmark and no extra top margin. Goldenrod `#DAA520` is primary; ink `#332513`, paper `#FFFBF1`, pale gold `#FFEAAB`, ochre, bronze and amber support it. Centralize copy and preserve accessible contrast, focus states, labels and mobile layouts at 360px.

Uploads are at most 10 MB; allow JPEG, PNG, WebP, MP3, WAV, MP4 and WebM after content/MIME checks. Images are at most 16 megapixels and re-encoded to strip embedded metadata. Store originals privately. Images served publicly are QR-marked PNG derivatives; browser video export burns in the QR in real time, up to 10 minutes and 50 MB output. Original public video playback is not permanently marked. Audio ZIPs contain the audio, QR cover and attribution text; the QR is not part of the sound. PDFs are branded public snapshots with a version, date, QR and attribution. Media marks do not prove ownership, grant permission or prevent copying.

## Xneelo deployment and email

The configured demo URL is `https://okwethu.wilmans.co.za`. Current hosting has no SSH and no writable location above the subdomain folder. Use the protected `_passport/` shared-host layout described in [Community tools](docs/COMMUNITY-TOOLS.md) and [deployment without SSH](docs/DEPLOYMENT-SHARED.md): root `index.php` and `assets/`; private source, dependencies, configuration and storage under `_passport/`, denied direct HTTP access by Apache rules.

- Preserve root/private `.htaccess`, `_passport/config/local.php`, storage, uploads and existing database data.
- Current migrations are `001_registry.sql` and `002_community_tools.sql`. Existing sites import `dist/okwethu-community-upgrade.sql` once in phpMyAdmin after backup, then upload `dist/okwethu-community-update.zip`. Do not reseed or reset during deployment; stop and inspect any partial SQL import error.
- SMTP is configured in protected `_passport/config/local.php` under `mail`; repository defaults are in `config/example.php`. Sender: `steward@okwethu.wilmans.co.za`. Delivery is disabled until configured. Use the exact mailbox SMTP host, not a guessed hostname. Do not commit or print the mailbox password.
- Only opted-in requester updates are queued. Deliver after committing business changes; retry from the private Email updates screen. At most five attempts; messages older than 29 days cannot be sent. Local spool transport is for non-production tests and does not send mail.
- Verify the hosted HTTPS/rewrite/private-file rules, actual SMTP delivery, phone QR scan, persistent redeployment and backup recovery separately. Local success is not hosted verification.

## Local checks and packaging

Run from the repository with development dependencies installed. Follow [Testing](docs/TESTING.md) for the isolated test configuration and both terminals; never run integration tests against hosting data.

```text
php bin/console.php lint
php vendor/phpstan/phpstan/phpstan analyse --no-progress
php vendor/phpunit/phpunit/phpunit
node tests/wizard.cjs
npm run build
```

Full database/community tests use loopback MySQL port 3317 and `story_passport_test`; upgrade tests create/drop only randomly named temporary test databases. HTTP tests require `PASSPORT_TEST_URL=http://127.0.0.1:8088` and a server using `PASSPORT_CONFIG=config/test.php`. Run HTTP tests before the wizard DOM test so `tests/.runtime/wizard.html` exists. Treat skipped integration/HTTP/package checks as incomplete coverage.

Set `COMPOSER_PHAR` to a local Composer PHAR before `php bin/package.php`; then pass that new production ZIP to `php bin/package-community-update.php <release.zip>`. These are local packaging commands, not commands for the no-SSH host. The web overlay excludes configuration, uploads, SQL and Apache rules; SQL and instructions are separate deliverables.

## Responsibilities and completion

| Brief | Responsibility |
|---|---|
| [01 Registry and backend](agents/01-registry-backend.md) | Schema, registry, input, accounts, access control and routing |
| [02 Consent and ledger](agents/02-consent-license-ledger.md) | Rules, consent, requests, revisions, notification events and allocations |
| [03 Frontend](agents/03-passport-frontend.md) | Templates, client scripts, copy, brand, media/PDF presentation and accessibility |
| [04 Seed and demo](agents/04-seed-and-demo.md) | Fictional fixtures, guarded reset, presenter guide and release handover |
| [05 QA and safety](agents/05-qa-and-safety-review.md) | Automated/manual checks, privacy, role boundaries and evidence |

Shared classes require coordination. A user-authorized integrated task may span roles; note those areas in its change/commit description. Role names describe responsibilities and do not require separate agents or a permission pause for routine edits.

A completed change has server validation, appropriate passing checks, a real-browser journey where applicable, privacy and role checks, updated operator instructions, honest copy and no committed secrets. Record manual/hosted checks as Pass, Fail, Blocked or Not run with evidence. Do not mark unperformed checks as passed. A documentation-only change needs link, consistency and diff checks; it does not require rerunning the application suite unless behavior changed.
