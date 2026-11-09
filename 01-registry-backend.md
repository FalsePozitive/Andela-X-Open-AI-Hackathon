# Agent: Registry & Backend

**Read first:** `AGENTS.md`, `docs/SPEC.md`.

## Role

Own the data layer and story/creator APIs: the registry that powers every Passport.

## You own

- `/prisma/schema.prisma` and migrations
- `/lib/ids.ts` (short story codes)
- `/lib/hash.ts` (content hashing)
- `/app/api/stories/**` and creator-related routes
- `/lib/db.ts` (Prisma client singleton)

## Tasks (in order)

1. **Schema:** implement the data model from `docs/SPEC.md`. Use enums for status, purpose, and sensitivity. Add indexes on `Story.id`, `PermissionReq.storyId`, `LedgerEntry.storyId`.
2. **ID generator:** `generateStoryId()` returns codes like `K7F2-9QXA` (8 chars + hyphen) from the alphabet `23456789ABCDEFGHJKLMNPQRSTUVWXYZ` (no 0/O/1/I). Retry on collision. Unit test the format and the alphabet.
3. **Content hash:** SHA-256 of the text body or uploaded file bytes, stored on `Story.contentHash`. Used later for stretch fingerprinting; for now it only records the content at registration time.
4. **`POST /api/stories`:** validate with Zod, create Creator (if new), Story, Contributors, Consents, License, and SplitShares in one transaction. Reject if split bps do not sum to 10000 (call the validator in `/lib/ledger`, owned by agent 02).
5. **`GET /api/stories/:id`:** return passport data for the public page. **Apply sensitivity rules:** if `RESTRICTED`, omit `textBody`, `filePath`, and `placeCoarse`. Never include contributor contact details.
6. **File handling:** store uploads under `/public/uploads` or a local `uploads/` dir, with a size limit (e.g., 10 MB) and an allowlist of MIME types. Strip EXIF location data from images before storing.

## Rules

- Public endpoints must never leak restricted content or private contact info. Add tests for this (coordinate with agent 05).
- Return consistent error shapes: `{ error: { code, message, fields? } }`.
- All writes that span tables use a single transaction.
- Do not add real auth. Use the demo steward flag described in `docs/SPEC.md`.
- Do not store anything about viewers.

## Done when

- A story can be created via API with contributors, consent, license, and split, and fetched back correctly
- Restricted stories are verifiably redacted in the public response
- ID and hash utilities have unit tests
- `npm run lint` and `npm test` pass

## Hand-offs

- Agent 02 provides the split validator and consent rules you call.
- Agent 03 consumes your API shapes. Document any change to response shapes in `docs/SPEC.md`.
