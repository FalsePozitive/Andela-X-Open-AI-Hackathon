# SPEC — Story Passport + Community Ledger

## Demo flow (what must work)

```
Register story → Record consent → Set license + revenue split
→ Passport issued (page + QR) → Someone requests permission
→ Community approves → Use is logged → Ledger shows the split and mock payouts
```

## Features

### Must have
1. **Create story**: title, media type (text | image | audio | video), file or text, language, place (coarse), context note, creator or community, sensitivity (`open` | `restricted`).
2. **Contributors and consent**: list of people/roles featured, each with per-purpose consent (see model) and date.
3. **License presets**: `FREE_WITH_CREDIT`, `EDUCATION_FREE_COMMERCIAL_BY_PERMISSION`, `ALL_BY_PERMISSION`. Each has an `aiTrainingAllowed` flag (default false).
4. **Revenue split**: shares per contributor/fund in basis points, summing to 10000. Split calculator UI previews payouts for a sample amount.
5. **Passport page** at `/p/[storyId]`: story (unless restricted), creator and community, date registered, context, consent summary, license terms in plain language, QR code, **Request permission** form, **Report a conflicting claim** link.
6. **Permission requests**: requester name, contact, intended use, commercial yes/no, proposed fee (optional). Community can approve, decline, or counter.
7. **Ledger**: on an approved commercial use with a fee, create ledger entries per split share. Dashboard shows balances per contributor and a transaction list. All payments labeled MOCK.
8. **Status badges**: `Unverified claim`, `Author reviewed`, `Community endorsed` (manual toggle for demo), `Restricted`.

### Should have
- QR code downloadable as PNG
- Printable/shareable passport card (image export)
- Simple claim-dispute list for stewards
- Plain-language license explainer in English plus one more language string table

### Stretch
- Visible mark overlay on uploaded images (QR + short code footer strip)
- Perceptual-hash match to find a story from a cropped image
- Share-target (Android PWA) to scan screenshots

## Data model (Prisma-style sketch)

```
Creator        { id, type: PERSON|COMMUNITY, name, representativeName?, language, createdAt }
Story          { id (short code), creatorId, title, mediaType, textBody?, filePath?,
                 language, placeCoarse?, contextNote, sensitivity: OPEN|RESTRICTED,
                 status: UNVERIFIED|AUTHOR_REVIEWED|COMMUNITY_ENDORSED,
                 createdAt, contentHash }
Contributor    { id, storyId, name, role, isCommunityFund: bool }
Consent        { id, contributorId, purpose: RECORD|PUBLISH|TRANSLATE|COMMERCIAL|AI_TRAINING,
                 granted: bool, grantedAt }
License        { storyId, preset, aiTrainingAllowed, contactEmail, notes? }
SplitShare     { id, storyId, contributorId, bps }          // sum(bps) == 10000
PermissionReq  { id, storyId, requesterName, requesterContact, intendedUse,
                 isCommercial, proposedFeeMinor?, currency, status: PENDING|APPROVED|DECLINED|COUNTERED,
                 createdAt, decidedAt? }
LedgerEntry    { id, storyId, permissionReqId, contributorId, amountMinor, currency,
                 kind: MOCK_PAYMENT, createdAt }
Claim          { id, storyId, reporterName, reporterContact, reason, status: OPEN|RESOLVED, createdAt }
```

## Business rules (these get unit tests)

- Split shares must sum to exactly 10000 bps; reject otherwise.
- Allocation of an amount: `floor(amount * bps / 10000)` per share; leftover minor units go to the first share (or a documented rule), so totals always equal the amount exactly.
- A commercial permission request cannot be approved unless every contributor has granted `COMMERCIAL` consent.
- If `aiTrainingAllowed` is false, any request whose intended use mentions AI/model training is flagged and cannot be approved.
- A `RESTRICTED` story never returns `textBody`, `filePath`, or precise place from public endpoints.
- Ledger entries are append-only. Corrections are new entries, not edits.

## API sketch

```
POST   /api/stories                    create story (+ contributors, consent, license, split)
GET    /api/stories/:id                public passport data (respects sensitivity)
GET    /api/stories/:id/qr             QR PNG for the passport URL
POST   /api/stories/:id/requests       create permission request
PATCH  /api/requests/:id               decide request (steward only in demo)
GET    /api/ledger?storyId=            entries + balances
POST   /api/stories/:id/claims         report a conflicting claim
```

Demo auth: a simple "steward" toggle or fixed demo login. Do not build real auth.

## Phases

| Phase | Goal | Done when |
|---|---|---|
| 1 | Schema + create/read story | Story can be created and fetched by ID |
| 2 | Passport page + QR | `/p/[id]` renders with license and QR |
| 3 | Consent + split + requests | Request → approve flow works with rules enforced |
| 4 | Ledger dashboard | Approved fee produces entries and balances |
| 5 | Seed data + polish + honest-limits copy | Full demo runs cleanly |
| 6 | Stretch | Only if all above pass |

## Open questions

Add questions here instead of guessing. Format: `- [role] question`.
