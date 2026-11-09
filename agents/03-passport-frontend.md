# Agent: Passport & Dashboard Frontend

**Read first:** `AGENTS.md`, `docs/SPEC.md`.

## Role

Build the screens people actually see: the public Passport, the creation flow, and the Ledger dashboard. The Passport is the hero of the demo, so make it clear, warm, and fast.

## You own

- `/templates/passport.php` (public Passport)
- `/templates/{dashboard,new,manage,requests,claims}.php` (create story, manage requests)
- `/templates/ledger.php` (Ledger dashboard)
- `/templates/layout.php and /resources/app.css`
- `/src/Copy.php and /resources/copy.php` (all user-facing strings)

## Design principles

- **Mobile-first, low bandwidth.** The Passport page should work well on a mid-range Android phone on a slow connection: server-rendered, minimal JS, small images.
- **People first.** Lead with the creator and community, then the story context, then the rules. Metadata tables come last.
- **Plain language.** No legal jargon. A teenager and an elder should both understand the license box.
- **Accessible.** Semantic HTML, labels, focus states, good contrast, readable font sizes.
- **Honest.** Show badges for unverified status and a visible "declared terms, not a legal contract" note.

## Screens and tasks (in order)

1. **Passport page `/p/[storyId]`:**
   - Header: title, creator and community, language, date registered
   - Status badge (Unverified claim / Author reviewed / Community endorsed / Restricted)
   - Story content (text, image, audio player, or video), **hidden when restricted**, replaced with a respectful message and a steward contact action
   - Context note
   - "What you can do with this story" box using `describeLicense` output, with clear icons or labels for Allowed / Ask first / Not allowed (AI training)
   - Consent summary: who agreed, to what, and when (names/roles only, no contact info)
   - Revenue split summary (shares as percentages)
   - QR code (and download button)
   - **Request permission** form
   - **Report a conflicting claim** link
   - Footer note: "These are terms set by the story's stewards. They are a declared record, not a legal contract."
2. **Create story flow `/dashboard/new`:** a stepper:
   1. Story and context
   2. Contributors and consent (per person, per purpose checkboxes)
   3. License preset and AI-training toggle (default off)
   4. Revenue split with a live **split calculator**: enter a sample amount, see per-share payouts, and block submit unless shares sum to 100%
   5. Review and publish, with a link to the new Passport
3. **Requests inbox `/dashboard/requests`:** list of pending requests; Approve / Decline / Counter. If approval is blocked, show the reasons from the API plainly. Show the AI-training warning when flagged.
4. **Ledger dashboard `/ledger`:** balances per contributor, transaction list, totals. Every payment row shows a **MOCK** tag. A simple bar or list visualization is enough.
5. **Polish:** loading and empty states, error messages, print-friendly Passport.

## Rules

- All strings come from `/src/Copy.php and /resources/copy.php`.
- Never display contact details of contributors on public pages.
- Never claim "verified" unless the status is actually `COMMUNITY_ENDORSED` or `AUTHOR_REVIEWED`, and label what that means.
- Do not add heavy UI libraries. Tailwind plus simple components.
- If an API shape is missing something you need, add it to "Open questions" in `docs/SPEC.md` for agent 01 or 02.

## Done when

- The full demo flow can be performed using only the UI
- The Passport is readable on a 360px-wide screen
- Restricted stories show the protected view
- Keyboard-only navigation works for the main forms
- `php bin/console.php lint` passes


## Approved implementation update
PHP 8.3 / MySQL replaces Next.js / SQLite. See docs/SPEC.md for the authoritative PHP paths, API, privacy and rounding decisions. Role ownership is retained by subsystem; the initial integrated build spans all roles under the user-approved plan.


