# AGENTS.md — Story Passport + Community Ledger

Read this file first. Then read the agent file for your role in `agents/`, and `docs/SPEC.md` for the data model and build order.

## What we're building

A hackathon proof of concept (an invention sprint, not a product launch) for the OpenAI x Andela "African Stories" hackathon, **Truth, Authorship & Ownership** track.

**Story Passport + Community Ledger** gives every African story:

1. **A Passport:** a public page and QR code showing who created the story, which community it comes from, when it was registered, and the rules for using it.
2. **A Ledger:** a transparent record of who consented to what, how revenue is split, and (mock) payout tracking.

One-line pitch: *Every African story gets a passport that says whose it is, what's allowed, and who gets paid.*

## Non-negotiable principles

These override convenience. If a task conflicts with them, stop and flag it.

1. **Be honest about what this is.** Licenses and consent records are *declared terms*, not legally enforceable contracts. The registry records who *claimed* a story and when; it does not prove ownership. Never write UI copy, comments, or docs that imply legal enforcement, guaranteed protection, or verified ownership.
2. **Payments are mock only.** No real money, no payment provider SDKs, no real bank or mobile-money details. Every ledger amount is clearly labeled "Demo", with "no real money moves" visible on payment screens. Internal MOCK_PAYMENT/MOCK_ADJUSTMENT values remain unchanged.
3. **Consent is specific.** Consent is stored per person and per purpose (record, publish, translate, commercial, AI training). Never collapse it into a single boolean.
4. **The community decides.** Rights terms and splits are set by creators/community representatives. The system suggests defaults but never silently applies them.
5. **Sensitive stories stay protected.** A story marked `restricted` must not expose its content, audio, or location publicly. The passport shows only that it exists, who stewards it, and how to contact them.
6. **Show uncertainty.** Where something is unverified (identity, authority to speak for a community, translation quality), the UI says so with a visible badge. Do not hide limits.
7. **Privacy by default.** Collect the minimum. Location is optional and coarse. No tracking of viewers. Do not store scanned screenshots.
8. **No invented culture.** Do not fabricate proverbs, traditions, community names, or "authentic" details in seed data or copy. Use clearly labeled placeholder stories or public-domain/permissioned material only.

## Stack

- PHP 8.3 server-rendered templates and PHP routing
- Tailwind CSS
- PDO + MySQL/MariaDB with versioned SQL migrations
- `endroid/qr-code` for QR PNG generation
- PHP Input validators for server-side validation
- PHPUnit for unit/integration tests; PHPStan for static analysis

Adjust the stack only if the team agrees; update this file if you do.

## Commands

Update these if scripts change.

```text
composer install
npm ci
npm run build
php bin/console.php migrate
php bin/console.php seed
php -S 127.0.0.1:8087 -t public public/router.php
php vendor/phpunit/phpunit/phpunit
php bin/console.php lint
php vendor/phpstan/phpstan/phpstan analyse
```

## Repo layout

- src/: pure rules, validation, data and web services
- templates/: server-rendered pages
- public/: only web root; compiled assets
- database/migrations/: versioned MySQL schema
- agents/: role briefs; docs/: specification, demo and deployment
- tests/: PHPUnit checks

Keep rules pure and controllers thin.

## Conventions

- PHP strict types. Validate untrusted input at every boundary.
- Validate every API input with the PHP Input validators. Return typed errors with clear messages.
- **Money is stored as integers in minor units** (e.g., cents), never floats. Percentages are stored as integer basis points (1% = 100 bps; 100% = 10000 bps). Splits must always sum to exactly 10000 bps.
- Story IDs are short, human-readable, URL-safe codes (e.g., `K7F2-9QXA`), generated without ambiguous characters (no 0/O, 1/I/l).
- Dates are stored as UTC ISO strings and displayed in the viewer's locale.
- Components are accessible: semantic HTML, labels on inputs, sufficient contrast, keyboard-navigable. Assume low-end phones and slow connections: mobile-first, minimal JS on the public passport page, no heavy images.
- UI text is plain and warm. Avoid legal jargon. Keep strings in one place (`/src/Copy.php and /resources/copy.php`) so they can be translated later.
- Commits are small and descriptive. One concern per commit.

## Definition of done (per task)

- [ ] Works end to end in the browser for the demo flow
- [ ] Input validated; errors handled with user-friendly messages
- [ ] Pure logic has unit tests (especially ledger math and consent rules)
- [ ] `npm run lint` and `npm test` pass
- [ ] Honest-limits copy present wherever a claim could be over-read (see principles 1, 2, 6)
- [ ] No secrets or personal data committed

## How agents work together

Each role owns specific files. Do not edit another role's files without noting it in the PR/commit message. If you need a change in another area, leave a `TODO(role-name): ...` comment or add it to `docs/SPEC.md` under "Open questions".

| Agent file | Owns |
|---|---|
| `agents/01-registry-backend.md` | `/database`, `/src/Rules.php`, story and creator API routes |
| `agents/02-consent-license-ledger.md` | `/src/Rules.php`, `/src/Rules.php`, `/src/Registry.php`, related API routes and tests |
| `agents/03-passport-frontend.md` | `/templates/passport.php`, `/templates/`, `/templates/ledger.php`, `/resources` |
| `agents/04-seed-and-demo.md` | `/database/seed`, `/docs/DEMO.md` |
| `agents/05-qa-and-safety-review.md` | Reviews everything; owns tests for principles and edge cases |

## Build order

Follow `docs/SPEC.md` phases. Get an ugly end-to-end flow working by Phase 3, then polish. Do not start stretch items until Must-haves pass.

## Out of scope

Real payments, blockchain, real identity verification, user accounts beyond a simple demo login, multi-language UI beyond a string table, legal advice, and any claim of enforceability.


## Approved PHP build overrides
The original commands and Next.js layout above are superseded by README.md and docs/SPEC.md. Use src/ for pure rules and services, templates/ for pages, public/ as the only web root, database/migrations/ for SQL, and src/Demo.php for seed data. Current checks: php bin/console.php lint; php vendor/phpunit/phpunit/phpunit; php vendor/phpstan/phpstan/phpstan analyse; npm run build. The integrated initial implementation spans all ownership areas. No real payments, no identity verification, and no claims of enforceability.




## Okwethu visual update
User-approved name: Okwethu (Ours). Goldenrod #DAA520 is the primary brand colour. Public copy uses Demo instead of MOCK while clearly identifying simulated payments. The rebrand spans frontend, copy and amount formatting; it changes no financial or consent behavior.


## Passport/media update
User-approved additions: visible percentage shares totalling 100% (stored as exact integer basis points), QR-marked image derivatives, browser video export, audio plus QR-cover ZIP downloads, branded public PDF snapshots, and tighter logo tagline spacing. Originals and registration hashes remain private and unchanged. A media mark supports attribution; it is not copy protection or proof of ownership. This work spans registry, rules, frontend, exports, documentation and QA.


## Community tools approval
The user explicitly approved editable records and private versions, consent withdrawal and sharing controls, private request tracking and optional SMTP updates, individual accounts with community-scoped roles, search, and preferred attribution (features 1, 3, 4, 5, 6, 7). Individual accounts supersede the earlier fixed-login-only scope. This release spans registry, ledger/consent, frontend, demo and QA ownership areas. See docs/COMMUNITY-TOOLS.md for upgrade, role boundaries and verification. Keep existing approvals and ledger entries immutable. Never reset a hosted database as part of deployment.
