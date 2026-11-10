# Three-minute demonstration

Use only fictional placeholder content. Every payment is Demo. The app records claims and declared choices, not verified ownership or legal enforcement.

| Time | Action |
|---|---|
| 0:00–0:20 (20s) | Explain the problem: stories travel without credit, consent or terms. |
| 0:20–1:05 (45s) | Register a short placeholder story. Add contributors, record separate consent choices, choose terms, set 70%/30% shares and explicitly confirm both. |
| 1:05–1:30 (25s) | Open its passport; show QR, consent summary and declared terms. |
| 1:30–2:00 (30s) | Use the seeded text story. Submit/review a commercial request. Attempt approval; point out the missing commercial consent and clear blocking reason. |
| 2:00–2:40 (40s) | Manage consent, record the contributor's declared permission, return to the inbox, confirm the final Demo fee and approve. Open the ledger and show exact allocation. |
| 2:40–2:50 (10s) | Open the restricted passport and the seeded AI-training request warning. |
| 2:50–3:00 (10s) | Close with honest limits: declared records, no ownership proof, no real payouts. |

Total: **180 seconds**.

For the longer acceptance journey, use [the step-by-step testing guide](TESTING.md). The scenarios there are separate from this exact 180-second script.

## Before presenting

- Complete the deployment release gate; check the base URL used by QR codes.
- Use a dedicated demo database and placeholder data. Seed creates two creators, three stories, two pending requests and one approved Demo use. No network connection is needed by the seed command.
- Reset only when intended: enable `allow_demo_reset`, use a database ending in `_demo`, and run `php bin/console.php reset-demo --confirm=RESET-DEMO`. Disable reset again afterward. This resets demo story records and their related requests, tokens, notifications, revisions and allocations; accounts, memberships, collections and consumed login-bootstrap state remain. Never run it on real data. Old private upload files are not exposed and may be cleaned separately after a backup.
- Keep steward login, passport, permission inbox, consent screen, ledger and restricted passport in prepared tabs. Use a private/incognito window to show the visitor view.
- Rehearse creation; use prepared placeholder text. Check keyboard navigation, media and QR download.
- Keep a local copy of the seeded app and screenshots of the verified walkthrough for connectivity failure. Screenshots must contain only fictional demo material.

## Fallback

If live registration fails, open the seeded text passport and continue with its pending commercial request. If internet fails, switch to the local demo; explain that remote QR access is unavailable. Never imply a failed or blocked approval succeeded.

## Presenter notes

1. Declared terms are not legal contracts.
2. Every payment shown is Demo; no funds move.
3. A timestamp records a claim, not proof of ownership.
4. Community governance requires people and processes beyond this proof of concept.
5. Enforcement and real payouts would need future work with appropriate partners.
