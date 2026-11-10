# Agent 04 — Seed data and demo delivery

Read [AGENTS.md](../AGENTS.md), [SPEC.md](../docs/SPEC.md), [DEMO.md](../docs/DEMO.md), [community upgrade](../docs/COMMUNITY-TOOLS.md) and [testing guide](../docs/TESTING.md).

## Responsibility and files

Own fictional fixtures in `src/Demo.php`, the presentation walkthrough in `docs/DEMO.md`, and operator/demo handover. Coordinate seed/reset behavior in `bin/console.php`, packaging via `bin/package.php` / `bin/package-community-update.php`, and release notes with backend and QA. Seed data is PHP; do not invent a `database/seed` directory or depend on Node/SSH on hosting.

## Baseline fictional fixtures

Keep two declared creators (PERSON and fictional COMMUNITY with representative), three clearly labelled stories (text, generated geometric image, restricted record), all three license presets, a community-fund share, mixed purpose-specific consent, a blocked commercial request, an AI-training request blocked by declared rules, and an approved demo allocation. Display shares as percentages, such as 70%/30%, while storing exact basis points internally.

Use only placeholders and fictional people/organisations. Never fabricate authentic cultural material, sacred content, traditions or authority. Never use real names, recordings, photos or contact addresses without permission. Permissioned private material belongs outside committed seed data. Generated image fixtures are attached privately and the first saved revision must match the actual seeded record.

Community-tools examples can demonstrate corrections/history, a pause or publication withdrawal, a private tracking link, counteroffer acceptance, reviewer/steward role differences, search and preferred credit. Prepare these explicitly in a disposable demo; do not silently alter the baseline story count or seed existing hosted data. Accounts, community collections and email delivery are separate setup steps, not verified cultural identities.

## Seed and reset behavior

`php bin/console.php seed` creates fixtures only in an empty registry and refuses a nonempty one. It is not an upsert or automatic deployment step. It has no external network dependency.

`php bin/console.php reset-demo --confirm=RESET-DEMO` is destructive to demo story records and related requests, tokens, notifications, revisions and allocations. It requires all three safeguards: `allow_demo_reset=true`, a database name ending in `_demo`, and the exact confirmation argument. It **preserves accounts, memberships, collections and consumed bootstrap state**; do not claim it wipes every database table. It does not automatically delete old private uploads.

Run reset only when the user explicitly intends to reset a disposable demo. Disable the option afterward. Never reset, reseed or import the initial schema over an existing hosted site during a release. Record the selected environment before any reset command.

## Presentation timing and language

Keep the existing core demonstration exactly **180 seconds**:

| Time | Action |
|---|---|
| 0:00–0:20 | Explain credit, consent and terms travelling with a story — 20 seconds |
| 0:20–1:05 | Register a prepared placeholder with separate consent and 70%/30% shares — 45 seconds |
| 1:05–1:30 | Show passport, QR, declared terms and consent — 25 seconds |
| 1:30–2:00 | Attempt a seeded commercial approval blocked by missing consent — 30 seconds |
| 2:00–2:40 | Record the declared consent, confirm the demo fee, approve and show exact allocations — 40 seconds |
| 2:40–2:50 | Show restricted content and an AI warning — 10 seconds |
| 2:50–3:00 | Close with declared-record and no-real-money limits — 10 seconds |

The total is 20 + 45 + 25 + 30 + 40 + 10 + 10 = 180. The community-tools acceptance journey in the testing guide is a separate rehearsal; do not add it to this script and still claim three minutes. Use **Demo**, not MOCK, in current spoken/display copy, while retaining internal ledger enums.

Explain that registration records a claim, badges record declared reviews, community authority is not independently verified, amounts are simulated, and the app does not enforce rights or recall downloads. Never describe a blocked/failed approval or an unsent email as successful.

## Hosting, email and fallback

The hosted demo is `https://okwethu.wilmans.co.za`; always use HTTPS for secure sessions and QR URLs. Existing Xneelo hosting has no SSH and uses root `index.php`/`assets` with a denied `_passport` private folder. Back up database/files, import the community upgrade once through phpMyAdmin, then extract the matching website overlay. Preserve private configuration, uploads and Apache rules.

SMTP sender is `steward@okwethu.wilmans.co.za`; configuration is private `_passport/config/local.php` under `mail`. Keep delivery disabled until configured and tested with a mailbox the tester controls. Private tracking works without email. Do not send live notifications during seed/test preparation without explicit authorization; local spool tests do not send mail.

Prepare owner/steward and private visitor tabs, a reviewer account where needed, the seeded blocked request, a known restricted passport, valid QR downloads and a local copy of the application. Store private request links and passwords outside public presenter notes/screenshots. If registration fails, continue on the prepared story. If connectivity fails, use the local demo and explain that remote QR access is unavailable. Keep only fictional material in demo screenshots.

## Completion checks

Rehearse the core timed script and the separate testing-guide journey. Check actual phone QR, protected storage, account roles, optional mail status, backup/rollback and data surviving redeployment. Record which checks were actually completed. Delivery requires a ready-to-upload ZIP, separate non-destructive SQL and clear no-SSH instructions; it does not imply the hosted deployment has been performed.
