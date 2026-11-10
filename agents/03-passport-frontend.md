# Agent 03 — Okwethu frontend and exports

Read [AGENTS.md](../AGENTS.md), [SPEC.md](../docs/SPEC.md), [community tools](../docs/COMMUNITY-TOOLS.md), [passport exports](../docs/PASSPORT-EXPORTS.md) and [testing guide](../docs/TESTING.md).

## Responsibility and files

Own `templates/`, `resources/app.css`, copy in `src/Copy.php` / `resources/copy.php`, and browser behavior in `public/assets/app.js` / `public/assets/media-export.js`. Coordinate `src/Watermark.php` and `src/PassportPdf.php` with backend/rules roles for safe export presentation. Compile `public/assets/app.css` locally. Do not require Node, a browser automation tool or a media encoder on Xneelo.

## Brand, tone and access

Use the established Okwethu logo and goldenrod palette: primary `#DAA520`, ink `#332513`, paper `#FFFBF1`, pale gold `#FFEAAB`, with the existing ochre/amber/bronze accents. Keep “Ours. Together.” directly beneath the wordmark without extra top margin. Use warm, plain English and community agency, with accessible contrast and focus states.

Registration is a claim. Review badges are declared review/endorsement; **never describe them as independently verified ownership, identity or authority**. Public money copy says Demo and no real money moves. QR marks and attribution support a record; neither grants permission nor prevents copying.

Server authorization determines access; hiding a button is only a usability aid. Reviewers must not be offered write controls. Team controls follow owner/community-admin permissions. Public rendering uses the safe projection, not raw records.

## Screens to preserve

| Screen | Required behavior |
|---|---|
| `/` | Public search/filtering and paginated passport cards; search only visible fields for hidden records |
| `/about`, `/faq` | Explain the purpose, declared limits, current workflows, roles, sharing and email setup truthfully |
| `/dashboard/new` | Five steps: story/collection; people and purpose consent; terms; percentage shares; explicit final review |
| `/p/{id}` | Current version/status, safe content, consent, terms, percentages, QR/PDF, preferred credit, permission and conflicting-claim forms |
| `/dashboard` | Search only assigned private collections; links appropriate to roles |
| `/dashboard/stories/{id}` | Details, sharing, consent, terms/shares, preferred credit, review status, reasons and private version history; owner transfer control |
| `/dashboard/requests` | Approve/decline/counter, clear consent/AI blocks, requester response, fresh private tracking link and explicit final-fee confirmation |
| `/r/{token}` | Private request status/history and current counteroffer response; keep-link-private and expiry explanation |
| `/dashboard/account`, `/dashboard/team` | Password changes and scoped account/membership management; no public signup/reset claims |
| `/dashboard/notifications` | Truthful disabled/pending/failed/sent/expired delivery status and permitted retries |
| `/dashboard/claims`, `/ledger` | Private claim review; contributor/currency balances, transaction history and appended signed corrections |

Public content is available only with OPEN sensitivity, ACTIVE sharing and every contributor's publication consent. Otherwise hide title, context, place, media and private contributors, and show the minimal contact record and a plain sharing status. PAUSED/WITHDRAWN passports must not offer a new permission form; conflicting claims/contact remain available. Do not embed raw snapshots or private data in hidden DOM nodes, JSON, data attributes or page source.

## Forms, percentages and copy

Keep exact percentage input up to two decimal places, 100% validation and the live split preview. Send exact decimal strings to the API. Registration's sample amount is ordinary ZAR; request/counter/correction amounts remain integer minor units. Always label the units clearly.

Missing consent remains visibly not granted. AI terms are default-off suggestions, explicitly confirmed with the people involved. Provide accessible errors and a saved/reload state. Preserve `expectedRevision` and reason fields in every story edit/consent/status form; on a stale record, ask the user to reload and review.

After a public request, show the private tracking URL as a safe DOM link and explain who can use it and its expiry. Email is optional and may be disabled or fail. A counteroffer acceptance records a fee agreement, not final approval. Render all supplied names/notes/credits safely; use DOM text APIs or escaped template output.

Preferred credit can be copied with a manual-selection fallback and downloaded as text. Blank preferred credit uses the generated title/creator/contributor credit plus passport URL. Hide private attribution on nonpublic records. Update About and FAQ whenever behavior or limits change.

## Media and PDF presentation

- Public images are actual bottom-right QR-marked PNG derivatives; private originals are unchanged. Small images may get a border.
- Video exports draw the QR into frames in the browser, in real time, retaining sound where supported. Keep progress, cancellation and failure states. Limits are 10 minutes and 50 MB output; output is WebM or supported MP4. Original playback video is not permanently marked. Do not imply DRM.
- Audio downloads are a ZIP containing unchanged sound, a QR cover, instructions and `ATTRIBUTION.txt`; the QR is not embedded in sound.
- PDFs use the app's CI, date, version, passport URL/QR, current public terms/consent/shares and preferred attribution. Restricted/paused/withdrawn PDFs stay minimal. Text over 1,800 characters is a labelled excerpt. Do not embed audio/video or fetch remote/private resources.
- Authorization must run before cached-file use. Review PDF page breaks and avoid orphaned final notices when layout changes.

## Completion checks

Use the testing guide for the full browser journey, 360px layout, visible keyboard focus, labels, slow connection, print/PDF, clipboard fallback and all media paths. Run wizard DOM checks after HTTP tests generate the fixture, then production CSS build and appropriate PHP checks. Inspect real browser/PDF output when changed. Local screenshots and automated results do not replace hosted phone QR, email-delivery and redeployment checks.
