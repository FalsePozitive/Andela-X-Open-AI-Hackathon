# Agent: Consent, License & Ledger Logic

**Read first:** `AGENTS.md`, `docs/SPEC.md`.

## Role

Own the rules that make this project trustworthy: consent, license terms, permission requests, and ledger math. This is the core of the "Ownership" story. Correctness matters more than speed.

## You own

- `/lib/consent.ts`
- `/lib/license.ts`
- `/lib/ledger.ts`
- `/app/api/requests/**`, `/app/api/ledger/**`, `/app/api/stories/[id]/requests/**`
- Unit tests for all of the above (`/lib/*.test.ts`)

## Tasks (in order)

1. **Split validation (`ledger.ts`):**
   - `validateSplit(shares)` returns ok only if every `bps` is a non-negative integer and the sum equals exactly 10000.
   - `allocate(amountMinor, shares)` returns per-share amounts as integers whose sum equals `amountMinor` exactly. Rule: `floor(amount * bps / 10000)` per share, and any remainder is distributed one minor unit at a time starting from the largest share. Document the rule in a comment.
   - Property-style tests: random splits and amounts always sum correctly; no negative values.
2. **Balances:** `computeBalances(entries)` groups append-only ledger entries by contributor.
3. **Consent rules (`consent.ts`):**
   - `canApprove(request, contributors, consents, license)` returns `{ ok, reasons[] }`.
   - Commercial requests require every contributor to have granted `COMMERCIAL`.
   - Any request flagged as AI training requires `license.aiTrainingAllowed === true` and every contributor to have granted `AI_TRAINING`.
   - Return human-readable reasons so the UI can explain why approval is blocked.
4. **AI-training detection:** `detectAiTrainingIntent(text)` is a simple keyword/phrase check on `intendedUse` ("train", "training data", "dataset", "fine-tune", "LLM", "model"). It is a **conservative flag, not a verdict**: on a match, the UI shows "This request may involve AI training. Review carefully." Document its limits in a comment.
5. **License text (`license.ts`):** `describeLicense(preset, aiTrainingAllowed)` returns plain-language bullets (what is allowed, what needs permission, what is never allowed). No legal jargon. Never use phrases like "legally binding" or "enforceable".
6. **Request endpoints:**
   - `POST /api/stories/:id/requests` creates a request (validate with Zod).
   - `PATCH /api/requests/:id` lets the demo steward approve, decline, or counter. On approve, call `canApprove`; if blocked, return 409 with reasons.
   - On approve of a commercial request with a fee, create one `LedgerEntry` per split share via `allocate`, in a single transaction. Entries are `MOCK_PAYMENT`.
   - Approval is idempotent: approving twice must not create duplicate entries.
7. **Ledger endpoints:** `GET /api/ledger?storyId=` returns entries, balances, and totals.

## Rules

- Money is integer minor units. Percentages are integer basis points. No floating point in money math.
- Ledger entries are append-only. Never update or delete them. A correction is a new entry with a negative amount and a note.
- Every payment-like field or label says MOCK.
- If rules conflict or something is ambiguous, add it to "Open questions" in `docs/SPEC.md` instead of guessing.

## Done when

- All rules in the "Business rules" section of `docs/SPEC.md` have passing tests
- Approving a request with a fee creates entries that sum exactly to the fee
- Double approval creates no duplicates
- Blocked approvals return clear reasons
- `npm run lint` and `npm test` pass

## Hand-offs

- Agent 01 calls `validateSplit` when creating stories.
- Agent 03 displays your `reasons[]` and balances.
- Agent 05 will try to break your rules. Expect it.
