# About and FAQ pages

The user requested an About page explaining Okwethu's purpose and an FAQ describing operation for visitors and stewards. This update spans public routes, shared navigation, frontend templates, central copy and styles.

- `/about`: community agency, credit, purpose-specific consent, declared terms, revenue shares, restricted stories and the limits of a claim registry.
- `/faq`: separate visitor and steward guidance, plus a section on what records mean. Topics include QR/print, requests, privacy, counteroffers, registration, uploads, basis points/minor units, consent, approval blocks, AI flags, ledger corrections, claims and HTTPS sign-in.
- Both pages are public and linked in header/footer navigation. FAQ sections have direct anchors and native keyboard-operable disclosure controls. Content requires no JavaScript.
- Copy is held in `src/Copy.php`; reusable goldenrod page styles extend the existing theme. These screens describe the current workflow and do not promise automatic emails, visitor request accounts, online counteroffer acceptance, general post-registration editing or real payments.

## Apply to your existing site

Upload `okwethu-about-faq-update.zip` into the subdomain folder containing `index.php`, extract it and allow the included files to be replaced. Delete the uploaded ZIP afterward. Refresh the site, then open `/about` and `/faq` or use the new navigation links. This overlay includes the current Okwethu design and does not include configuration, credentials, SQL, or uploaded stories; no database import is required.

Local fixture rendering, syntax/static checks, registration regression checks and desktop/mobile browser checks are used before handover. Hosted routing is verified only after the upload; the assistant has not deployed the files to Xneelo.
