# Okwethu step by step testing guide

Use this guide to check the app as a visitor, steward, reviewer and site owner. It covers the browser journey, privacy and account boundaries, media/PDF exports, optional email and the final hosted checks. The browser sections do not require SSH. The local developer section is for automated and adversarial checks on an isolated copy.

This is a procedure, not a record that every check has already passed. Record **Pass, Fail, Blocked or Not run** for each test, with the version, date and evidence. Never substitute a local success for a hosted result.

## 1. Prepare and record the environment

Use a disposable local or staging copy for tests that change consent, accounts, terms or data. If checking the hosted demo, operate only on explicitly labelled QA records created for the exercise. Do not modify a real community record or reset the hosted database.

1. Record the site address, release/commit, date, browser and tester. The configured hosted address is **https://okwethu.wilmans.co.za**. A staging/local copy may use a different address.
2. Confirm a database export and matching file backup are available before any upgrade/redeployment. Follow [the community upgrade guide](COMMUNITY-TOOLS.md); do not repeat an already applied migration.
3. Open one browser for a steward, a separate private window for a visitor, and another browser/profile for role/session tests. Different tabs in one profile share a sign-in session.
4. Prepare small image, audio and video files containing only your own or fictional material. Use an image without personal/location data and a short video with a simple audible tone. No real cultural material is needed.
5. Keep two email addresses you control ready if testing live mail. Do not use invented addresses as real delivery targets. Keep passwords and private request links out of shared screenshots and reports.
6. If the site is not yet upgraded, complete test T01 before creating accounts or stories. If it already has database accounts, do not attempt to re-bootstrap or reset them.

### Test fixtures

Choose **QA Collection A** and **QA Collection B** as clearly fictional access groups. The owner creates them in Community team. Collection names are access groups, not verified cultural affiliations.

| Fixture | Setup and purpose |
|---|---|
| QA Open Story | Text story in Collection A; fictional creator; distinct marker text such as `QA_PRIVATE_BODY_0911`; two contributors at 70% and 30%; both grant PUBLISH; first initially refuses COMMERCIAL; AI consent/terms off |
| QA AI Story | Separate text story in Collection A for repeated AI-gate checks; both grant PUBLISH |
| QA Other Story | Story in Collection B, for testing unrelated private access |
| QA Image / Audio / Video | Separate open records in Collection A, with both contributors granting PUBLISH; use your prepared media |
| QA Admin A | Individual ADMIN account assigned only to Collection A |
| QA Steward A | Individual STEWARD account assigned only to Collection A |
| QA Reviewer A | Individual REVIEWER account assigned only to Collection A |
| QA Steward B | Individual STEWARD account assigned only to Collection B |

Use a timestamp suffix on usernames/titles to avoid collisions. Create additional fresh QA stories for separate request cases. Requests are limited to five per email/story in a day and forty per story in a day; a rate-limit response is not a consent or delivery failure.

### Amounts used in these tests

| Field | Enter | Meaning |
|---|---|---|
| Contributor share | `70` and `30` | 70% and 30%, totalling 100% |
| Registration calculator sample amount | `1.07` | ZAR 1.07 |
| Request, counteroffer or final fee | `107` | 107 cents = ZAR 1.07 |
| Signed ledger correction | `-1` | Reduce the demo allocation by one cent |

The expected allocation of 107 cents at 70%/30% is **75 cents and 32 cents**. Seven cents allocates **five cents and two cents**. These are simulated records; no money moves.

## 2. Check the upgrade and sign in

### T01 — Existing data survives the upgrade

1. Before upgrading, note an existing passport ID/title, a restricted story ID, one approved request and its ledger entries. Save private evidence of amounts/currencies and current consent.
2. The operator exports the database and downloads matching files, including private configuration/uploads. Check phpMyAdmin's `migrations` table: if `002_community_tools.sql` is already recorded, skip importing it again and record that fact.
3. For an unupgraded copy, import `dist/okwethu-community-upgrade.sql` once through phpMyAdmin, then upload/extract `dist/okwethu-community-update.zip` in the subdomain folder. Stop on an import error; do not rerun a partial schema change blindly. Never run seed or reset for this upgrade.
4. Reopen the recorded passports, consent, approved request and ledger.

**Expected:** Migration 002 is recorded; old stories belong to Existing story collection; old content, IDs, consent, approval snapshots and ledger amounts are preserved. Private configuration, uploads and Apache rules remain in place. A failed/unfinished installation is **Fail or Blocked**, not Pass.

### T02 — HTTPS login and individual accounts

1. Open the HTTPS `/login` page. Sign in with the current credentials. On the first upgrade login only, the configured legacy steward becomes the database site owner once.
2. Open My account. Confirm the signed-in identity and assigned collections. Create the QA collections/accounts above through Steward desk → Community team.
3. Sign out. Try an incorrect password once, then sign in correctly. Avoid deliberately locking the only owner account.
4. Open the site's HTTP address on the production host; confirm it returns to HTTPS. If a form-expired message appears during ordinary sign-in, reopen the HTTPS address and confirm cookies are permitted before retrying.

**Expected:** Correct credentials work over HTTPS; incorrect credentials are rejected without exposing secrets. New roles have their own accounts. Production HTTP redirects to the configured HTTPS URL. The configured password is not a fallback after account bootstrap; the local automated suite checks that behavior without risking the live owner account.

## 3. Create a story and validate percentages

### T03 — Register the open QA story

1. Sign in as QA Steward A and choose Register a story.
2. Step 1: choose Collection A; enter a fictional title/creator, English language, optional coarse location, text with a unique marker and clearly fictional context. Choose open visibility. For a COMMUNITY creator, enter a representative.
3. Step 2: add the two fictional contributors or fund representative. Grant PUBLISH for both. Leave the first person's COMMERCIAL unchecked; grant it for the second. Keep AI consent unchecked.
4. Step 3: choose declared terms, leave AI training off, enter a suitable public steward email, and explicitly confirm terms.
5. Step 4: enter 70% and 30%. Set sample amount to `1.07`; confirm the preview is ZAR 0.75 and ZAR 0.32, clearly labelled Demo. Explicitly confirm shares.
6. Step 5: review all choices, register and record the new passport code.

**Expected:** A single passport is created with version 1, matching content, per-person consent, terms, QR and percentage shares. No ownership verification is claimed. Contributor/requester private contacts are not published. The designated steward email is intentionally public.

### T04 — Invalid registration never partly saves

1. Begin a new fictional registration. Leave a required title/context, community representative or media file missing as applicable, and try to advance.
2. On the shares step, try a 99% total, negative share, share above 100% and more than two decimal places. Try final submission without confirming terms/shares.
3. Correct the fields and submit once. Check the desk for unwanted partial/duplicate records.

**Expected:** Invalid input is blocked or clearly rejected; only the valid registration appears. Use the local API/automated tests to verify server rejection when browser constraints are bypassed. Browser validation alone is insufficient evidence of atomic server behavior.

## 4. Correct a story and inspect history

### T05 — Corrections retain earlier versions

1. From the desk, open Manage story for QA Open Story. Correct its title, text or context and enter a reason. Save.
2. Check that its current version increases and the public passport uses the new details. If it previously had a declared review badge, check that a content/terms/consent change resets it to Unverified claim.
3. Open Private version history and Read saved version for version 1. Confirm the old details and reason/history are readable only to the permitted team.
4. In Declared terms and shares, try an invalid total and then a valid 60%/40% change with both confirmations and a reason. Check a new version is retained. Restore 70%/30% with a reason before the fee-allocation tests.
5. If the story already had an approved use, compare that approval and its allocations before/after the change.

**Expected:** Corrections retain old snapshots without rewriting past approvals/allocations. Invalid shares save nothing. The editor retains existing contributor identities and original media; it does not promise a contributor/media replacement workflow. Legacy records explain that earlier unrecorded edits cannot be reconstructed.

### T06 — Two open forms cannot overwrite each other

1. Open the same Manage story page in two tabs before saving either.
2. Save a preferred-credit change and reason in tab A.
3. Without reloading tab B, try to save another change with a reason.
4. Reload tab B, review the change from A, then save a deliberate new change.

**Expected:** The stale save is rejected with a reload/review message. The first change remains and no extra version is created for the failed attempt. Saving after refresh creates the next version. Automated tests also cover simultaneous saves.

## 5. Test permissions and counteroffers

### T07 — Commercial consent blocks approval

1. As a visitor, open QA Open Story and submit Request permission: your controlled email, fictional name, a commercial booklet use, commercial checked, AI unchecked and proposed fee `107` ZAR. Leave email updates off for this case.
2. Save the private tracking link shown after submission. Open it in the visitor window; confirm it shows only this request and PENDING status.
3. As QA Steward A, open Permission inbox, find the request and attempt approval with final fee `107` and explicit fee confirmation.

**Expected:** Approval is blocked by the first contributor's missing commercial consent. The request remains undecided and no allocations are created. The acknowledgement means recorded, not approved. The private link works without an account; requester information is not public on the passport.

### T08 — Counter, accept and finally approve

1. For this fictional fixture only, record the first contributor's newly declared COMMERCIAL consent with a reason. Do not change real consent merely to make a test pass.
2. In the inbox, choose Counter, enter counteroffer fee `107` and an explanatory note. Save and confirm there are no allocations yet.
3. Open/refresh the visitor's private link. Accept the proposed fee with an optional note. Refresh again.
4. In the steward inbox, confirm the accepted response. Try approving with final fee `108`; record the rejection. Then approve with matching fee `107` and explicit fee confirmation.
5. Open the story-filtered Community ledger and the visitor's tracking page. Refresh the final approval pages again.

**Expected:** Acceptance records agreement and still needs final approval. The mismatched fee is rejected. Final approval creates exactly two Demo allocation rows for this request: 75 and 32 cents, totalling 107. Refreshing adds no allocation. The final private request status is APPROVED. The automated suite tests repeated API approval and simultaneous approval; the UI does not keep an approval button on a completed request.

### T09 — Decline and stale counteroffer response

1. Use a fresh QA story/request. Counter with a fee and note, then keep its visitor response form open.
2. As steward, make a new counteroffer with a different fee. Submit the old visitor form without refreshing.
3. Refresh the visitor page, decline the current offer and refresh the inbox.

**Expected:** The stale response is rejected. Declining the current offer closes it as DECLINED and creates no allocations. A second response is rejected. A new purpose requires a new request rather than rewriting an already final one.

### T10 — AI training needs both gates

1. On QA AI Story, submit an AI-training request with the AI checkbox selected, noncommercial use and no fee. Keep publication consent granted for everyone; AI terms and AI consent start off.
2. Try approving as steward with final fee `0` and confirmation. Record the license/consent block.
3. Turn on the license's AI-training flag through the terms form, confirm terms/shares and give a reason. Keep one contributor's AI consent refused; try approval again.
4. For the fictional test, record every contributor's declared AI consent with a reason; approve with fee `0`.
5. On a separate request, leave the AI checkbox off but use a phrase such as “build a model training dataset”. Inspect the AI review flag and its imperfect-detection explanation.

**Expected:** The license flag alone is insufficient; consent alone is insufficient. Both are required. Keyword detection can also flag a request without explicit selection and is described as imperfect. A noncommercial approval creates no money-allocation rows. A noncommercial request with a nonzero final fee is rejected in local adversarial tests.

## 6. Withdraw consent and control sharing

### T11 — Publication withdrawal hides content immediately

1. While QA Open Story is public, open its passport/API/PDF and save a private copy of their URLs. Submit another pending request before changing consent.
2. Record one contributor's PUBLISH consent as not granted, with a reason.
3. Refresh the visitor passport and `/api/stories/{id}`. Generate a new PDF and try the credit-text download. In the private inbox, attempt to approve the pending request.
4. Compare the earlier approved request and its ledger rows with the records from T08.

**Expected:** The public page/JSON/PDF become a minimal record with a waiting-for-publication-consent status. Title, body, context, place and private contributors/attribution disappear; credit download is unavailable. New approval is blocked. Existing approval snapshots/ledger amounts remain unchanged. Earlier copies already downloaded cannot be recalled and are not evidence of a new leak.

### T12 — Pause, regrant, withdraw and restrict are distinct

1. In Sharing and visibility, set sharing to Paused with a reason.
2. Regrant PUBLISH consent for the fictional contributor, but leave sharing Paused. Refresh the visitor passport and pending request approval attempt.
3. Change sharing to Withdrawn with a reason; check the visitor page again.
4. Restore Active but choose Restricted visibility, with a reason. Confirm a minimal passport and contact/request conversation are available, while content stays hidden.
5. Restore Active and Open only after all contributors grant PUBLISH; record the reason and check public content returns.

**Expected:** Paused/withdrawn sharing hides content and stops new requests/approvals. Regranting consent does not unpause it. Restricted visibility hides content but allows a private permission conversation; restriction alone is not an approval-consent gate. All changes retain history.

## 7. Test community roles and private access

### T13 — Roles and collections are enforced

1. As QA Reviewer A, open the desk, Collection A story, private history, requests, claims and ledger. Confirm read access where there are records.
2. Look for registration, consent/edit, decision, claim-update and ledger-correction controls. Attempt to open Community team.
3. As QA Steward A, maintain Collection A; copy a private Collection B story/history/request/ledger URL from the owner and try it in the steward-A session. Check lists/search for B's private data.
4. As QA Admin A, create a new reviewer in A and change an existing A member's role. Confirm there are no global account-disable/password-reset or new-collection powers.
5. As owner, add an existing unrelated account to a collection, move a QA story between collections, and check the old/new teams' access on the next operation. Include the moved story's history, requests, ledger and notifications.

**Expected:** Reviewers can read only assigned private records and cannot write. Stewards cannot inspect or change unrelated records by guessing a URL. Community admins manage only their own teams; adding an existing unrelated account requires the owner. Owner transfers move access to the record and its related private material. The local HTTP/service tests verify direct API writes and retries, beyond hidden buttons.

### T14 — Password, disable and membership changes affect sessions

1. Use a disposable QA member account in two separate browser profiles, signed in independently. In My account, change its password using the current password and a new 12–72-byte password.
2. Refresh a private page in each profile. Sign in with the new password; try the old password once.
3. As owner, disable the disposable member account. Refresh its still-open private page. Re-enable it and sign in again.
4. Remove or downgrade a QA membership and try the next private read/write. Preserve a working owner account throughout.

**Expected:** Password change ends all old sessions, including the current one. Old password and disabled-account sign-in fail. Membership changes apply on the next operation. Disabling/recovery is owner-only; the owner cannot be disabled through the team controls. Do not store test passwords in the result record.

## 8. Test search and preferred attribution

### T15 — Search finds only appropriate records

1. As visitor, search for an open title, passport code and public creator/steward identity. Filter by media type, exact language, collection and declared review status; clear filters afterward.
2. Hide a QA record using restriction, pause or publication withdrawal. Search for its unique title, then apply its known language/media/collection filters.
3. Search the hidden record's passport code without those filters. As a steward, repeat searches on the private desk and check collection isolation.
4. If enough fixtures exist, use Next/Previous across more than 24 results and confirm filters persist.

**Expected:** Open records match visible fields. Hidden private title/language/media/collection information does not produce public matches; the visible code/identity can match. A typed query may appear in the search input—inspect returned cards/data, not that echoed input, for leaks. Private search contains only assigned collections. Paginated pages contain at most 24 records. Mark pagination Not run if there are too few fixtures.

### T16 — Preferred credit copies and exports safely

1. As steward, save a preferred credit line with a reason; open the public passport.
2. Choose Copy attribution and paste into a local text editor. Download credit text and compare.
3. Confirm the passport URL and current-terms reminder are added. Clear the preferred line with a reason and check the generated creator/contributor fallback.
4. Restrict/pause the story and check private credit is not exposed on the public page or credit endpoint.

**Expected:** Copy/download matches the preferred or fallback credit and links to the passport. If clipboard permission is unavailable, the text remains manually selectable with guidance. Credit is attribution, not permission or verified ownership.

## 9. Test media and PDF exports

### T17 — Image download retains a usable QR

1. Register QA Image with your prepared JPEG/PNG/WebP file and valid publication consent. Open its passport and download the QR-marked PNG.
2. Open the downloaded image separately; confirm the QR is actually in its bottom-right pixels, not merely a web overlay. Scan it with a phone.
3. Try a small image and check that any added border keeps the QR readable. Confirm the original registered media/hash has not been replaced by the derivative using the local integration checks.

**Expected:** The downloaded PNG has a decodable QR leading to the configured HTTPS passport. The public image is re-encoded/marked; private originals are retained. A mark is not proof of ownership or copy protection. Record phone scanning separately if the device cannot reach a local URL.

### T18 — Audio package and browser video export

1. Register QA Audio. Download audio + QR cover ZIP, extract it and play the sound.
2. Confirm the ZIP includes the sound file, QR cover, instructions and `ATTRIBUTION.txt`; scan the cover QR. Keep audio/cover together.
3. Register QA Video using a short prepared clip with sound. In a supported browser, choose Create QR-marked video and keep the tab active until it completes.
4. Play the exported file, check sound and the bottom-right QR across playback, and scan a paused frame. Start a second export and cancel it; then retry successfully.

**Expected:** Audio is unchanged sound plus a separate visual cover, not a QR embedded in the sound. Video export produces a marked WebM or supported MP4 with retained sound and working cancellation. Export is real time, limited to 10 minutes/50 MB output; original playback is not permanently marked. Record unsupported-browser behavior honestly; it is not an export pass.

### T19 — PDF snapshots use CI and respect later privacy

1. Download the open passport PDF. Check Okwethu logo/goldenrod styling, code, version/date, QR, terms, consent, percentage shares and attribution.
2. For a text story over 1,800 characters, check the PDF labels its shortened story as an excerpt and points to the live record. Audio/video should not be embedded.
3. Check every PDF page at readable zoom: no clipped text, overlapping footer, orphaned final notice or unreadable QR. Scan the main passport QR.
4. For QA Image, first request its public `/media/{id}`, `/api/stories/{id}/cover`, `/api/stories/{id}/download`, `/api/stories/{id}/credit` and `/api/stories/{id}/pdf` URLs to populate caches. Withdraw one contributor's PUBLISH consent or pause sharing; request those URLs again in the visitor window.
5. Inspect the newly generated hidden PDF using text search/extraction as well as visually. Check page source and JSON for the QA markers.

**Expected:** Open PDFs contain only the current public snapshot; hidden PDFs contain only the minimal record and honest status/contact. Fresh media/cover/download/credit requests are unavailable after hiding, even when caches exist. QR download can remain available because it links to the minimal passport. A previously saved offline copy remains on the tester's device.

## 10. Test optional email updates

### T20 — Disabled delivery and opt-in

1. On a local/staging copy with delivery disabled, submit a new request using your controlled email and leave Email me updates unchecked.
2. Confirm a private tracking link is shown. Check Email updates as a permitted steward; no message should be queued for that unselected request.
3. Use a fresh request and tick Email me updates. Save its tracking link and check Email updates.

**Expected:** Tracking works in both cases. The opted-in request can be queued while delivery is disabled; the UI explains delivery is disabled and does not claim an email was sent. Other requests' existing notifications do not count as this request's messages. Do not change active hosting mail settings merely to test disabled mode.

### T21 — Configured SMTP delivers a controlled test

1. The operator configures `mail` inside protected `_passport/config/local.php`, retaining existing settings. Use sender `steward@okwethu.wilmans.co.za`, the exact Xneelo outgoing hostname, mailbox username/password, and the supported TLS/port combination. See [email setup](COMMUNITY-TOOLS.md#email-setup).
2. After entering settings, enable `transport => smtp`. Submit a new opted-in request to an email address you control.
3. Check Email updates and the recipient's inbox/spam folder. Open the link from the received email in the visitor window.
4. Counter the request, reply through its private link and record a final decision. Verify the opted-in update messages and request history.

**Expected:** The saved business action succeeds independently of mail delivery. A SENT row means the SMTP server accepted it; actual inbox arrival must be observed separately. The received link reaches only that request and contains no private contributor approval snapshot. If SMTP settings/mailbox access are unavailable, record Blocked rather than Pass.

### T22 — Failure, retry and expired links

1. On a disposable local/staging environment only, configure an unavailable test SMTP endpoint or use the local mail-spool automated checks. Submit an opted-in request or decision.
2. Confirm the request/decision is saved even if delivery fails. On a message that has not exhausted attempts, restore valid test settings and use Email updates → Send or retry update in the permitted community.
3. Confirm successful delivery is not repeatedly sent by refreshing. On a separate failing test message, leave the endpoint unavailable and exhaust five attempts; confirm retry is then unavailable. Check unrelated/reviewer retry attempts through a focused developer check and record it separately. Existing opt-in/spool tests alone do not prove every lease or concurrent SMTP-delivery case.
4. In the private inbox, generate a fresh tracking link for a QA request; confirm the earlier unexpired link still works. Use local automated checks for the 30-day expiry and stale counter response, without waiting or altering a live database clock.

**Expected:** Failed/pending status is truthful, retries are scoped and limited to five attempts, and messages older than 29 days cannot be sent. Expired/invalid tracking links are rejected. Local spool success is not proof that any external email was delivered. An ambiguous SMTP failure may cause a duplicate on a later retry; do not claim exactly-once external delivery.

## 11. Test claims and ledger corrections

### T23 — A conflicting claim stays private

1. As visitor, send a clearly fictional conflicting-claim report with your controlled contact address and a unique reason marker.
2. Refresh the public passport/source/JSON; inspect the relevant steward's Conflicting claims inbox.
3. Record Reviewed / resolved as a permitted steward. Check an unrelated community member and reviewer cannot update it.

**Expected:** Contact/reason remain in the assigned private inbox and are not published. Resolution records a review, not a determination of ownership. Role restrictions apply to direct APIs as well as buttons.

### T24 — Corrections append and currencies stay separate

1. On the T08 story's ledger, record the original 75/32-cent allocation row IDs and displayed total.
2. Expand Append a demo correction on the 75-cent row. Enter `-1` minor unit and a fictional reason; save. Check the history and contributor balance. Optionally append `+1` with another reason to demonstrate a restoring adjustment.
3. On a fresh QA request, approve a fee in another supported currency. Inspect balances/totals and the currency labels.
4. Use automated checks for zero/invalid corrections and tiny/uneven-share rounding; do not infer totals from one row if the story has other approved requests.

**Expected:** The original row remains unchanged. A new signed row references it and adjusts only that contributor/currency balance. Different currencies have separate balances/totals and are never added together. Every amount remains visibly Demo; no funds are transferred.

## 12. Test privacy and unsafe input

### T25 — Direct routes and hostile input are safe

Perform adversarial writes only on the disposable local/staging environment. The automated suites in section 14 cover cases that normal browser controls prevent.

| Check | Expected result |
|---|---|
| Logged-out private APIs such as `/api/requests`, `/api/claims`, `/api/ledger` | Unauthorized; private HTML routes require sign-in |
| Unrelated collection's private story/history or ledger URL | Denied with no private payload |
| Reviewer write or community-admin global disable/reset | Denied on the server |
| Missing/invalid CSRF on a write | Rejected; no change recorded |
| Script-like title/note/credit | Escaped text, never executed |
| Renamed text file pretending to be JPEG/MP4 | Rejected by content/MIME checks |
| File over 10 MB, empty file or image over 16 megapixels | Rejected without partial registration |
| Unsafe/traversal-style upload filename | Never used as a storage path; no overwrite or path disclosure |
| Negative/fractional/too-large fee or invalid currency | Rejected; no approval/allocation |
| Missing consent, stale revision, missing confirmations or invalid percentages | Rejected with useful error; no partial write |
| Direct `/_passport/config/local.php`, `/_passport/vendor/autoload.php`, storage or a known private upload path on Apache | Denied (403/404), with no configuration/file bytes; source text must not be served |
| Known private marker in public JSON, HTML/source, search results, media or newly generated hidden PDF | Absent |

On a clone, use a harmless test marker/private file for the Apache denial check. Never copy private configuration into a report to demonstrate that it was protected. PHP's local development server does not enforce Apache `.htaccess`, so its result cannot prove the production denial rules.

## 13. Check mobile, keyboard and slow connections

### T26 — Readable and usable at 360px

1. Use a phone or browser viewport at 360px. Open the home/search page, public/hidden passports, registration wizard, Manage story, private tracking, account/team, inbox and ledger.
2. Check horizontal scrolling, long names/emails/credit, nav wrapping, visible labels, error/saved messages, media controls and share fields.
3. Use only Tab, Shift+Tab, Enter, Space and arrow keys through sign-in, the five-step wizard, copy-credit fallback, a counteroffer response and FAQ disclosures. Confirm visible focus and sensible reading order.
4. Enable a slow connection in browser developer tools. Submit one request and one story edit; check in-progress button states, a readable failure state if disconnected, and safe retry after reconnection. Inspect records before resubmitting an action whose first response was lost.
5. Open browser print preview and separately inspect the actual downloaded PDF.

**Expected:** No horizontal overflow; important actions/terms/status remain readable; keyboard users can reach and use controls. Errors are announced/displayed without losing necessary context. A slow/lost response does not justify claiming success or blindly duplicating a write. Full accessibility conformance requires a separate audit; record what was actually exercised.

## 14. Run local developer checks

These commands are for a local checkout, **not the no-SSH Xneelo account**. No application changes are required merely to follow this guide. Use PHP 8.3, Composer development dependencies, a supported local Node runtime and a dedicated MySQL instance on loopback port 3317. Never change the live configuration to point tests at hosting.

1. Install the locked local dependencies with `composer install` and `npm ci` when the checkout does not already have them. Keep dependency updates separate from a routine test run.
2. Create ignored `config/test.php` using `config/example.php` as the structure. It must use `environment => development`, local base URL `http://127.0.0.1:8088`, DSN `mysql:host=127.0.0.1;port=3317;dbname=story_passport_test;charset=utf8mb4`, isolated database credentials, and an absolute private path such as `tests/.runtime/storage`. Keep `allow_demo_reset` false and mail disabled or non-production spool. Do not commit this file.
3. Set its test-only steward username to `steward`. Generate a hash for the suite's test-only password `Test-only-password-1907` using `php bin/console.php password` and store the hash in this ignored test config. This known fixture password must never be used by the hosted account. If using an existing test database, ensure its bootstrapped test owner matches the fixture; do not reset a live account to make a test pass.
4. Ensure `story_passport_test` exists on the isolated instance. Upgrade tests also need permission to create/drop fresh randomly named databases prefixed `story_passport_upgrade_test_`; never grant those permissions to the hosted application just for tests. Do not seed the test database: tests create their own fixtures. Create `tests/.runtime`/private storage directories if absent.
5. In PowerShell terminal A, run from the repository:

```powershell
$env:PASSPORT_CONFIG = Join-Path (Get-Location) 'config/test.php'
php bin/console.php migrate
php -S 127.0.0.1:8088 -t public public/router.php
```

6. Keep that server running. In terminal B, from the same checkout, run:

```powershell
$env:PASSPORT_TEST_URL = 'http://127.0.0.1:8088'
php bin/console.php lint
php vendor/phpstan/phpstan/phpstan analyse --no-progress
php vendor/phpunit/phpunit/phpunit
node tests/wizard.cjs
npm run build
```

7. Run HTTP/PHPUnit checks before the wizard test: the HTTP suite writes `tests/.runtime/wizard.html`, which the DOM test consumes. Record failures, warnings, skipped tests and actual counts. No HTTP URL means HTTP cases can skip; no fresh package means package coverage can be missing. A green partial run is not full coverage.
8. For release validation, set `COMPOSER_PHAR` to the absolute path of the installed local Composer PHAR, run `php bin/package.php`, and pass that newly printed production ZIP path to `php bin/package-community-update.php <release.zip>`. Then run `php vendor/phpunit/phpunit/phpunit tests/PackageTest.php`. The overlay must exclude private settings, uploads, SQL and `.htaccess`; SQL and instructions are separate files.
9. Complete browser/PDF checks above. Stop only the local test server/database you started when finished. Keep test credentials, cookie jars, token links, spool bodies and fixture exports ignored/private.

### What the automated suites cover

| Suite | Coverage to preserve |
|---|---|
| `RulesTest.php` | IDs, integer splits/allocation, consent/AI rules, public projection and escaping |
| `RegistryTest.php` | Atomic registration, consent/approval snapshots, counter/decline, append-only corrections, currencies and concurrent approval |
| `CommunityToolsTest.php` | Scoped roles, revisions/stale and simultaneous edits, withdrawal, counter replies/fees/expiry, mail opt-in/spool, session invalidation, safe search and approval waiting on consent change |
| `HttpTest.php` | Actual session/CSRF requests, public/private routes, restricted/withdrawn responses, tracking/counter replies, community boundaries, registration and marked image delivery |
| `MediaTest.php`, `PassportExportTest.php` | Media validation, percentage conversion, marked derivatives/packages and public/minimal PDF creation |
| `TransportTest.php` | Production HTTPS redirect behavior |
| `UpgradeTest.php` | Migration of an existing registration/ledger without data loss; uses fresh temporary test databases |
| `PackageTest.php` | Fresh production archive, required runtime/autoload classes, no secrets/development dependencies |
| `wizard.cjs` | Wizard validation, consent, confirmations, exact preview, review/back navigation and payload |

The previous community-tools checkpoint recorded **47 passing tests** plus browser/PDF checks. Record the actual new run rather than copying that result; counts may change as coverage grows. Local spool tests do not send real mail, and local PHP-server tests do not verify Apache rules or actual inbox delivery.

## 15. Complete hosted acceptance checks

### T27 — QR, persistence and recovery readiness

1. On the hosted HTTPS site, scan a passport QR from an external phone connection. Confirm the configured hostname/path and the same record/status. Download/scan one marked image or PDF as applicable.
2. Complete a clearly fictional visitor request → steward counteroffer → visitor acceptance → final consent check/approval → Demo ledger journey using the actual host. Run the controlled email test only if configured and authorized.
3. Before redeployment, record a QA story version, request decision and allocation row; export a database backup. Redeploy the same tested overlay without importing migration 002 again or replacing private config/storage.
4. Check those records and private media again after redeployment. Confirm HTTPS and `/_passport/` denial still work.
5. Verify the previous matching release and database/file backups can be located and read. If a restore rehearsal is needed, restore to a separate clone; do not overwrite the active database for this test.

**Expected:** Phone QR works publicly; the approved journey behaves as locally tested; records/uploads survive code redeployment. Backup and previous release are available. A code-only rollback to the old fixed-login app cannot enforce new roles/sharing rules: if rollback is necessary, use the matching database/release recovery procedure in [Community tools](COMMUNITY-TOOLS.md#recovery-and-rollback). Record any hosted check you could not perform.

## 16. Record results and decide readiness

For every T01–T27 case, record a row in a private result sheet or copy this table for the run. Use Blocked when a prerequisite such as mailbox/host access is unavailable; use Not run when the check has not been attempted. Neither is a pass.

| Test ID | Status | Actual result and evidence | Issue/owner | Retest |
|---|---|---|---|---|
| T01 | Not run | | | |
| T02 | Not run | | | |
| T03 | Not run | | | |
| T04 | Not run | | | |
| T05 | Not run | | | |
| T06 | Not run | | | |
| T07 | Not run | | | |
| T08 | Not run | | | |
| T09 | Not run | | | |
| T10 | Not run | | | |
| T11 | Not run | | | |
| T12 | Not run | | | |
| T13 | Not run | | | |
| T14 | Not run | | | |
| T15 | Not run | | | |
| T16 | Not run | | | |
| T17 | Not run | | | |
| T18 | Not run | | | |
| T19 | Not run | | | |
| T20 | Not run | | | |
| T21 | Not run | | | |
| T22 | Not run | | | |
| T23 | Not run | | | |
| T24 | Not run | | | |
| T25 | Not run | | | |
| T26 | Not run | | | |
| T27 | Not run | | | |

Record the overall version/environment/date, automated command results and skip counts, browsers/device widths, unresolved issues, and which hosted checks were actually completed. Do not attach secrets, passwords, full request tokens or real community content to shared evidence.

Content/privacy leaks, broken role boundaries, incorrect or duplicated allocations, destructive upgrades and false ownership/enforcement claims are **blockers**. Fix and retest before release. Record major/minor usability or compatibility issues with an owner and decision. If email is intentionally disabled, document that operator choice and confirm tracking links still work; do not claim email delivery passed.

Related references: [agent guidance](../AGENTS.md), [specification](SPEC.md), [community upgrade/email setup](COMMUNITY-TOOLS.md), [media exports](PASSPORT-EXPORTS.md), [presentation script](DEMO.md), [historical review](REVIEW.md).
