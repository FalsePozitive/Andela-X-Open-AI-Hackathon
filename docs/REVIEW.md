# QA and release review

## Reading these checkpoints

The entries below describe the initial implementation at their recorded checkpoint. They do not describe every later feature or constitute fresh verification of the current release. The community-tools release added individual community-scoped accounts, optional SMTP updates, private request tracking, sharing controls, version history and exports; current behavior is documented in [AGENTS.md](../AGENTS.md) and [Community tools](COMMUNITY-TOOLS.md).

The later community-tools handover recorded 47 passing automated tests and local browser/PDF checks. The documentation update on 9 November 2026 does not rerun or supersede those results. Use [the step-by-step testing guide](TESTING.md) to record new results, including actual hosted HTTPS/Apache, phone QR, mailbox delivery and redeployment checks. Earlier statements about unavailable browser tools, fixed-login-only scope or absence of email integration are historical observations, not current feature restrictions.

## 2026-11-09 — Automated verification

- Finding: PHP unit, database integration and HTTP suites pass: 22 tests, 2,609 assertions at the recorded checkpoint. These cover randomized integer allocations, unambiguous IDs, missing/refused commercial consent, AI term/consent gates, atomic registration, consent history, immutable approval snapshots, counter/decline behavior, currency separation, append-only corrections and concurrent approval idempotence.
- Severity: major
- Owner: registry, rules, QA
- Status: fixed — tested against an isolated local MySQL 8.0.44 instance; no existing application database was used.

- Finding: HTTP checks cover restricted JSON/HTML/list/media redaction, private-contact acknowledgements, session/CSRF enforcement, escaped script input, QR PNG downloads, protected endpoints, steward pages and API registration. Images re-encode without appended metadata; forged media and oversized files are rejected in media tests.
- Severity: blocker
- Owner: registry, frontend, QA
- Status: fixed — no failing automated leak or approval tests at handover.

- Finding: Registration wizard DOM checks cover required fields, contributors, purpose-specific choices, default-off AI terms, explicit confirmations, exact split calculator, review, back navigation and submitted payload. PHP syntax, PHPStan level 5, JavaScript syntax and production CSS build pass.
- Severity: major
- Owner: frontend, QA
- Status: fixed — automated DOM verification complements HTTP checks; it is not a real browser or accessibility audit.

## 2026-11-09 — Verification limits

- Finding: Both the in-app browser and Windows browser-control runtime failed initialization with `failed to write kernel assets: The system cannot find the path specified. (os error 3)`. Visual inspection, keyboard-only walkthrough, actual 360px viewport and network-throttled browser testing have not been completed. Responsive CSS, semantic inputs, visible focus rules and server-rendered public content are implemented.
- Severity: major
- Owner: frontend, QA
- Status: open — finish the manual checklist on the local preview or hosted demo before presenting.

- Finding: Xneelo account/subdomain access has not been supplied. Real host extensions, HTTPS, Apache rewrites, database grants, private filesystem permissions, external-phone QR scan, persistent redeployment and backup/restore require on-host verification.
- Severity: major
- Owner: deployment, QA
- Status: open — deployment instructions and a production release are prepared. Not a live deployment claim.

## Honest-limits and content check

- Declared terms / claim-only notice is visible in page footer and public passport. Manual badge text says declared review or endorsement; no verified-ownership promise is made.
- Payment-like amounts and transaction kinds use MOCK. Public shares are percentages, not transfers. There are no payment credentials or SDKs.
- Seed content consists of explicitly fictional placeholders and locally generated geometric imagery. No real cultural story or community is fabricated.
- A designated public steward email is intentionally visible; contributor and requester contact data stays private.
- Consent is a declared record entered by the demo steward, not proof of personal identity or authority. Revocation changes future approvals; past approved uses retain their original snapshot. No enforcement or retroactive revocation mechanism is claimed.
- No app viewer tracking, screenshot scanner, real login/account provider, AI provider, email-sending service, or payment provider is integrated.
