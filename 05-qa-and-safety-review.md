# Agent: QA & Safety Review

**Read first:** `AGENTS.md`, `docs/SPEC.md`.

## Role

Be the skeptic. Try to break the rules, find places where the UI over-claims, and make sure the project is honest, safe, and demo-ready. Review work from agents 01 to 04.

## You own

- Tests that check principles and edge cases (`/tests/**`)
- A running review log at `/docs/REVIEW.md`

## Review checklist

### Honesty and claims
- [ ] No copy says or implies legally binding, enforceable, guaranteed, or "verified ownership"
- [ ] Every payment-like element is labeled MOCK
- [ ] Unverified claims show the "Unverified claim" badge
- [ ] The "declared terms, not a legal contract" note appears on the Passport

### Consent and rules
- [ ] A commercial request cannot be approved if any contributor lacks COMMERCIAL consent
- [ ] An AI-training request cannot be approved when `aiTrainingAllowed` is false
- [ ] Approving the same request twice creates no duplicate ledger entries
- [ ] Split math: totals always equal the amount exactly (try amounts like 1, 7, 100, 9999 and odd splits like 3333/3333/3334)
- [ ] Splits that do not sum to 10000 bps are rejected at API and UI level

### Privacy and safety
- [ ] Restricted stories never leak `textBody`, `filePath`, or precise place in any public response (check API JSON, HTML source, and image URLs)
- [ ] Contributor contact details never appear publicly
- [ ] Uploaded images have EXIF location stripped
- [ ] No viewer tracking, and no scanned screenshots stored
- [ ] File upload limits and MIME allowlist work

### Accessibility and usability
- [ ] Passport readable at 360px width
- [ ] Forms are keyboard-navigable with visible focus and labels
- [ ] Color contrast acceptable
- [ ] Page works with slow network (throttle in dev tools)

### Content
- [ ] Seed data is clearly marked as demo/placeholder and contains no invented cultural claims
- [ ] No real names or media without permission

## Tasks

1. Write automated tests for every unchecked item that can be automated (API leak tests, split math, double-approval, blocked approvals).
2. Manually walk through `docs/DEMO.md` and log any friction in `docs/REVIEW.md`.
3. Try adversarial inputs: oversized uploads, script tags in text fields, negative fees, huge bps values, missing consent rows. Confirm validation and safe rendering (no XSS).
4. Review the final UI copy for over-claiming and jargon.
5. Before the demo, run the full checklist and record the result.

## Review log format (`docs/REVIEW.md`)

```
## YYYY-MM-DD — <area>
- Finding: ...
- Severity: blocker | major | minor
- Owner: agent 01 | 02 | 03 | 04
- Status: open | fixed | wontfix (reason)
```

## Rules

- You report and test. When you find a bug, log it for the owning agent, and only fix it yourself if it is a trivial one-line change (note it in the log).
- A blocker is anything that leaks restricted content, mis-splits money, or makes a false legal or ownership claim. Blockers must be fixed before the demo.

## Done when

- All checklist items pass or have a logged, accepted reason
- Automated tests cover the rules and leak checks
- `docs/REVIEW.md` shows no open blockers
