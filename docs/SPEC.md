# Implemented specification — Story Passport + Community Ledger

The user approved PHP/MySQL on Xneelo in place of the original Next.js/SQLite stack. The original concept is retained in ORIGINAL-SPEC.md. This document is authoritative where the old sketches conflict.

## Structure and commands

- `src/Rules.php`: pure ID, consent, license, privacy projection and integer allocation rules.
- `src/Registry.php`: validated transactional registry/request/ledger services; `src/Input.php`: validation; `src/Media.php`: private upload processing.
- `src/Web.php`: session/CSRF enforcement, routes and response handling. `templates/`: server-rendered pages. `resources/`: Tailwind source and English copy catalog. `public/`: sole document root.
- `database/migrations/`: InnoDB schema; `src/Demo.php`: placeholder seed; `bin/console.php`: migrations, seed/reset, preflight and syntax checks.
- `composer install`, `npm ci`, `npm run build`; `php bin/console.php migrate`; `php bin/console.php seed`; `php -S 127.0.0.1:8087 -t public public/router.php`.
- Validation: `php bin/console.php lint`, `php vendor/phpstan/phpstan/phpstan analyse`, `php vendor/phpunit/phpunit/phpunit`.

## Data and decisions

Creators, stories, contributors, purpose-specific consent history, licenses, split shares, permission requests, ledger entries, and conflicting claims follow the original model. MySQL column names use snake_case; API story/request inputs use camelCase. Status and purpose values match the original uppercase enums.

Additional fields: `isAiTraining`; counter fee and decision note; final confirmed fee; approval snapshot of license, shares, consent and currency; original ledger entry and adjustment note; unique payment key per request/contributor. Consent records append rather than overwrite. Latest record by increasing consent ID determines the current choice.

Money: signed 64-bit DB minor units; nonnegative request fees, maximum 1,000,000,000 minor units per operation; basis points 0–10000, exactly 10000 total. Floor allocation, then one remainder unit per eligible share in descending bps and ascending saved contributor ID. This resolves the original rounding discrepancy. Zero-bps beneficiaries never receive remainder units. Currencies ZAR/USD/EUR/GBP/KES/NGN remain separate; ZAR is the UI default. Corrections append signed MOCK adjustments. No payment provider or actual funds.

Fixed demo steward session, no account system. All writes require CSRF. Creator registration, consent/status updates, request decisions, ledger and claim management require steward access. Visitors may create permission requests and claim reports; private contacts are never included in their acknowledgements. Requester communication is manual using email links; the app sends no messages.

Commercial approvals require all contributors' COMMERCIAL consent. AI approvals additionally require allowed license terms and every AI_TRAINING consent. Explicit AI declaration OR conservative phrase detection triggers the rule; a heuristic flag is not a factual verdict. Counteroffers create no ledger entries. Declined/approved requests are final; re-approval is idempotent. Consent updates and approvals lock the same story row, and payment uniqueness prevents concurrent duplicates.

Open passports explicitly expose story content, coarse location, context, contributor names/roles/current consent and shares. Restricted responses expose only ID, existence/sensitivity, manual claim status, registration date, steward name/type and the designated public steward email. No restricted title, media, context, location, terms, hashes or contributor list is public. Public media checks visibility on every request. App responses use no-store; public assets contain no story data.

Uploads: 10 MB, 16 MP image cap; real MIME checks; supported JPEG/PNG/WebP, MP3/WAV, MP4/WebM. Images re-encode to WebP without original EXIF; file names are server-generated. Original client filenames are discarded. Storage is outside the web root. Audio/video are delivered only for open stories, with escaped controls and no external embeds. No scanner/screenshot ingestion or viewer records.

## API

All API errors: `{ "error": { "code": "...", "message": "...", "fields": {} } }`. Validation 422, missing records 404, missing login 401, CSRF 403, blocked approvals/final-state conflict 409. GET `/api/session` returns the current CSRF token and steward-session state, no credentials. Send `X-CSRF-Token` and the same session cookie on writes.

| Method | Path | Access / behavior |
|---|---|---|
| POST | `/api/stories` | Steward; atomic creation; returns ID |
| GET | `/api/stories/:id` | Public allowlisted passport |
| GET | `/api/stories/:id/qr?download=1` | Public QR PNG for configured HTTPS base URL |
| GET | `/media/:id` | Public only for OPEN stories with media |
| POST | `/api/stories/:id/requests` | Public; acknowledgement only |
| POST | `/api/stories/:id/claims` | Public; acknowledgement only |
| GET | `/api/steward/stories/:id` | Steward; full internal record |
| PATCH | `/api/stories/:id/consent` | Steward; append `{updates:[{contributorId,purpose,granted}]}` |
| PATCH | `/api/stories/:id/status` | Steward; declared review status |
| GET | `/api/requests` | Steward inbox |
| PATCH | `/api/requests/:id` | Steward; status APPROVED/DECLINED/COUNTERED, note, finalFeeMinor + feeConfirmed for approval, counterFeeMinor for counter |
| GET | `/api/ledger?storyId=` | Steward; entries, contributor/currency balances, currency totals |
| POST | `/api/ledger/:entryId/adjustments` | Steward; signed amountMinor, mandatory note |
| GET / PATCH | `/api/claims`, `/api/claims/:id` | Steward; list / OPEN or RESOLVED review state |

Creation accepts a JSON object with story fields, `creator:{type,name,representativeName}`, `license:{preset,aiTrainingAllowed,contactEmail,notes}`, `contributors:[{name,role,isCommunityFund,bps,consents:{RECORD,PUBLISH,TRANSLATE,COMMERCIAL,AI_TRAINING}}]`, and explicit `termsConfirmed:true`, `sharesConfirmed:true`. Files use multipart `payload` containing that JSON and `media` containing the file. Client-supplied IDs/paths/hashes/status are not used.

Target subdomain: https://okwethu.wilmans.co.za. Hosted access and provider-specific preflight checks remain operational prerequisites, not verified local facts.

See [Passport/media exports](PASSPORT-EXPORTS.md) for percentage inputs, QR-marked downloads and branded PDFs. No database migration is required.

