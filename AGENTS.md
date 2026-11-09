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
2. **Payments are mock only.** No real money, no payment provider SDKs, no real bank or mobile-money details. Every ledger payment is labeled "MOCK" in the UI.
3. **Consent is specific.** Consent is stored per person and per purpose (record, publish, translate, commercial, AI training). Never collapse it into a single boolean.
4. **The community decides.** Rights terms and splits are set by creators/community representatives. The system suggests defaults but never silently applies them.
5. **Sensitive stories stay protected.** A story marked `restricted` must not expose its content, audio, or location publicly. The passport shows only that it exists, who stewards it, and how to contact them.
6. **Show uncertainty.** Where something is unverified (identity, authority to speak for a community, translation quality), the UI says so with a visible badge. Do not hide limits.
7. **Privacy by default.** Collect the minimum. Location is optional and coarse. No tracking of viewers. Do not store scanned screenshots.
8. **No invented culture.** Do not fabricate proverbs, traditions, community names, or "authentic" details in seed data or copy. Use clearly labeled placeholder stories or public-domain/permissioned material only.

## Stack

- Next.js (App Router) + TypeScript
- Tailwind CSS
- Prisma + SQLite (file DB, hackathon scale)
- `qrcode` for QR generation
- Zod for input validation
- Vitest for unit tests (ledger math, consent logic, license rules)

Adjust the stack only if the team agrees; update this file if you do.

## Commands

Update these if scripts change.

```bash
npm install
npx prisma migrate dev      # create/update the local DB
npm run seed                # load demo data (see agents/04-seed-and-demo.md)
npm run dev                 # start the app on http://localhost:3000
npm test                    # run unit tests
npm run lint                # lint + typecheck
```

## Repo layout

```
/app                 Next.js routes (UI + API route handlers)
/app/p/[storyId]     Public Passport page
/app/dashboard       Creator/community dashboard
/app/ledger          Ledger dashboard
/lib                 Pure logic: ledger math, consent rules, license rules, id generation
/prisma              Schema + migrations + seed
/components          Shared UI
/agents              Role instructions for coding agents
/docs                Spec and demo script
```

Keep business logic in `/lib` as pure, tested functions. Route handlers and components stay thin.

## Conventions

- TypeScript strict mode. No `any` without a comment explaining why.
- Validate every API input with Zod. Return typed errors with clear messages.
- **Money is stored as integers in minor units** (e.g., cents), never floats. Percentages are stored as integer basis points (1% = 100 bps; 100% = 10000 bps). Splits must always sum to exactly 10000 bps.
- Story IDs are short, human-readable, URL-safe codes (e.g., `K7F2-9QXA`), generated without ambiguous characters (no 0/O, 1/I/l).
- Dates are stored as UTC ISO strings and displayed in the viewer's locale.
- Components are accessible: semantic HTML, labels on inputs, sufficient contrast, keyboard-navigable. Assume low-end phones and slow connections: mobile-first, minimal JS on the public passport page, no heavy images.
- UI text is plain and warm. Avoid legal jargon. Keep strings in one place (`/lib/copy.ts`) so they can be translated later.
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
| `agents/01-registry-backend.md` | `/prisma`, `/lib/ids`, story and creator API routes |
| `agents/02-consent-license-ledger.md` | `/lib/consent`, `/lib/license`, `/lib/ledger`, related API routes and tests |
| `agents/03-passport-frontend.md` | `/app/p`, `/app/dashboard`, `/app/ledger`, `/components` |
| `agents/04-seed-and-demo.md` | `/prisma/seed`, `/docs/DEMO.md` |
| `agents/05-qa-and-safety-review.md` | Reviews everything; owns tests for principles and edge cases |

## Build order

Follow `docs/SPEC.md` phases. Get an ugly end-to-end flow working by Phase 3, then polish. Do not start stretch items until Must-haves pass.

## Out of scope

Real payments, blockchain, real identity verification, user accounts beyond a simple demo login, multi-language UI beyond a string table, legal advice, and any claim of enforceability.
