# Agent: Seed Data & Demo

**Read first:** `AGENTS.md`, `docs/SPEC.md`.

## Role

Make the demo reliable and honest. Provide seed data, a demo script, and a reset command so the full flow can run on stage with no surprises.

## You own

- `/src/Demo.php`
- `/docs/DEMO.md`
- `php bin/console.php seed` and `php bin/console.php reset-demo --confirm=RESET-DEMO` scripts

## Tasks (in order)

1. **Seed script:** create a small, realistic set of demo records:
   - 2 creators (one PERSON, one COMMUNITY with a named representative)
   - 3 stories: one text story, one image story, and one `RESTRICTED` story
   - Contributors with mixed consent (include one story where a contributor has **not** granted COMMERCIAL, so the blocked-approval demo works)
   - License presets covering all three options, with at least one `aiTrainingAllowed = false`
   - Split shares summing to 10000 bps, including a community fund share
   - 2 pending permission requests (one commercial with a fee, one that mentions AI training)
   - 1 approved request with ledger entries, so the Ledger isn't empty
2. **Content rules for seed data (important):**
   - Do **not** invent or imitate real cultural traditions, proverbs, sacred stories, or named real communities.
   - Use clearly labeled placeholder stories (e.g., "Placeholder story: a market day") or public-domain/permissioned text, and mark them in the UI as demo data.
   - Use fictional names for people and organizations. Add a visible "Demo data" note in the seed story context.
   - If the team has real, permissioned stories, they go in a separate `seed.local.php` that is gitignored.
3. **Reset command:** `php bin/console.php reset-demo --confirm=RESET-DEMO` wipes the DB and reseeds.
4. **Demo script (`docs/DEMO.md`):** a timed 3-minute walkthrough:
   1. Problem (20s)
   2. Create a story with consent and split (50s)
   3. Open the Passport and show QR and license box (30s)
   4. Submit a permission request; show approval **blocked** because a contributor hasn't consented to commercial use (30s)
   5. Fix consent, approve, and show the Ledger update with MOCK payouts (40s)
   6. Show the restricted story view and the AI-training flag (10s)
   7. Close with the honest limits (10s)
   Include a backup plan if live creation fails (use seeded story), and a checklist for pre-demo setup.
5. **Honest-limits slide notes:** write 5 short bullets the presenters can say aloud: declared terms not legal contracts, mock payments, registry records claims not proof, community governance is bigger than a PoC, enforcement and payouts are future work with partners.

## Rules

- Never use real people's names, photos, or recordings without explicit permission.
- Seed data must pass the same validation as real data (splits sum to 10000, etc.).
- The seed must be idempotent via `reset-demo` and must not depend on network access.

## Done when

- `php bin/console.php reset-demo --confirm=RESET-DEMO` produces a clean demo state every time
- The scripted demo can be run start to finish without errors
- `docs/DEMO.md` includes the fallback plan and checklist


## Approved implementation update
PHP 8.3 / MySQL replaces Next.js / SQLite. See docs/SPEC.md for the authoritative PHP paths, API, privacy and rounding decisions. Role ownership is retained by subsystem; the initial integrated build spans all roles under the user-approved plan.


