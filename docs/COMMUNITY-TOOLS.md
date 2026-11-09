# Okwethu community tools: upgrade without SSH

This release adds story corrections and private version history, consent withdrawal and sharing controls, private request tracking and optional email updates, individual accounts with community roles, search, and preferred attribution. Drafts and a community voting workflow are not included.

## Install on the existing subdomain

Use the **existing application database** and the web folder for `okwethu.wilmans.co.za`. This is an upgrade, not a fresh installation.

1. In phpMyAdmin, select the existing database and choose **Export → Quick → SQL**. Save the backup. Download a copy of the current website files, including the protected `_passport` folder and its private configuration and uploads. Keep the previous working release.
2. Schedule a short quiet period and stop anyone editing during the upgrade. In phpMyAdmin, confirm the selected database contains the existing `stories`, `ledger_entries` and `migrations` tables. Check `migrations`: `001_registry.sql` should be present and `002_community_tools.sql` should be absent.
3. Choose **Import** and select `okwethu-community-upgrade.sql`. Import it **once**. Confirm success and that `migrations` now contains `002_community_tools.sql`. This adds tables and columns and assigns existing stories to **Existing story collection**. It does not delete stories, reset consent, change ledger amounts or replace approval snapshots. MySQL schema changes are not one atomic transaction: if an import reports an error, stop and retain the error before retrying. Do not import it repeatedly over a partially completed migration.
4. Upload and extract `okwethu-community-update.zip` **inside the same subdomain web folder**, allowing replacement of application files. The ZIP contains `index.php`, `assets/`, and `_passport/` code and production libraries. It excludes configuration, uploads, sessions, database SQL and `.htaccess`; your existing private data and Apache rules stay in place. Delete the uploaded ZIP after extraction.
5. Open **https://okwethu.wilmans.co.za/login**. Sign in with the existing steward credentials. The first successful existing steward login creates the site-owner account once. Legacy sessions must sign in again. After that, accounts use database password hashes; the old configuration password is never a fallback.
6. Open **My account** and change the owner's password. Sign in again with the new password. Under **Steward desk → Community team**, add collections and individual team accounts. Share temporary passwords privately and ask each person to change theirs. The owner can move an existing story into another collection from **Manage story → Community collection**.
7. Check one existing passport, a restricted passport, a QR-marked download, its PDF, the permission inbox and ledger. Record a test request using your own address, counter it, follow its private link, accept and approve it. All amounts are demo records; no real money moves. Pause sharing and confirm the public content and downloads disappear. Confirm earlier ledger entries remain.

Do **not** run seed, reset, or import the original `001_registry.sql` during this upgrade. Do not replace `_passport/config/local.php`, `_passport/storage`, or your `.htaccess` files. Do not upload the repository, backups or SQL into publicly accessible folders.

### Email setup

The configured sender is **steward@okwethu.wilmans.co.za**. Email is disabled by default; private tracking links work immediately. Create or confirm the mailbox in Xneelo's mail management first. Use the exact outgoing server and credentials shown for that mailbox.

Edit the existing protected `_passport/config/local.php`. Add this **array entry inside its existing `return [...]`**, before the final `];`, retaining every existing database, URL and storage setting:

```php
'mail' => [
    'transport' => 'disabled', // change to 'smtp' only after entering settings
    'from' => 'steward@okwethu.wilmans.co.za',
    'host' => '', // exact SMTP hostname supplied by Xneelo
    'port' => 465,
    'encryption' => 'smtps',
    'username' => 'steward@okwethu.wilmans.co.za',
    'password' => '', // enter the mailbox password here privately
],
```

Ensure the preceding configuration entry ends with a comma. Xneelo documents [SMTP with SSL/TLS on port 465](https://xneelo.co.za/help-centre/email/ssl/); use its server hostname if your mail-domain certificate/DNS does not match. If your account specifically uses STARTTLS, set port `587` and encryption `starttls` instead. Do not disable certificate checks. PHP OpenSSL and outbound SMTP access must be available.

When the settings are complete, change `transport` to `smtp`, save, and send a test request from the app using an address you control with **Email me updates** selected. Check its inbox and spam folder, then **Steward desk → Email updates**. A message marked SENT means the server accepted it, not guaranteed inbox arrival. No live SMTP messages were sent during development.

Each opted-in request queues an initial update and subsequent decisions/replies. The app attempts a bounded delivery after saving; a failure never reverses the saved request or approval. Pending and failed updates can be sent or retried from Email updates, up to five attempts per message. Updates older than 29 days cannot be sent because their private links are expiring. There is no background cron requirement. An ambiguous SMTP failure may produce a duplicate message on retry. Sent message bodies are cleared from the outbox.

Private links are bearer credentials: anyone holding one can read and respond to that request until its 30-day expiry. Links are hashed in the token table; queued unsent email bodies necessarily contain the link. Responses send no referrer and are never publicly cached. Treat mailboxes, server access logs, private configuration and database backups as private. There is no public request directory or viewer analytics.

## Operating the features

- **Corrections:** Manage story edits title, language, coarse place, context, text, declared terms, percentage shares and preferred attribution. Every change requires a reason and the current version. A stale edit is rejected; reload and review before trying again. Contributor identities and original media are retained by this editor. Ask the operator for corrections to those records.
- **History:** Private, read-only saved versions include terms, consent and shares. Legacy stories retain their current state before the first post-upgrade edit; the app does not reconstruct past edits. Changes reset review badges; manually record a new declared review only when appropriate.
- **Sharing:** ACTIVE permits new requests, subject to visibility. PAUSED and WITHDRAWN hide content and stop new requests and approvals. RESTRICTED hides content but permits a conversation through a request. Every contributor needs publication consent for public content; missing or refused consent hides it immediately, even if visibility says OPEN. Regranting consent does not override an explicit pause. Existing downloads cannot be recalled.
- **Requests:** Visitors save the private tracking link shown after submission. They can see the decision and accept or decline a counteroffer. Acceptance alone creates no ledger entries: a steward must approve after checking current consent and confirming the same final fee. A changed fee needs a new counteroffer. Stewards can generate a fresh link for older requests or lost links and share it only with that requester through their recorded contact.
- **Accounts:** The site owner manages all collections, accounts, disabling and password recovery. Community admins maintain records and create/manage team members within their own collections. Stewards maintain records and decide requests. Reviewers can read private records and history but cannot write. Direct API routes, private pages, claims, requests, ledger entries and notifications are scoped as well as the navigation. Disabling an account or changing a password invalidates existing sessions. Membership changes apply on the next operation. There is no public signup or self-service email password reset.
- **Search:** Public search uses only the public projection. Hidden content cannot match title, language, media or collection filters; its code and public steward identity can still match. The private desk searches only assigned collections. Pages show at most 24 records. This demo reads its small catalog before filtering; a larger production catalog would benefit from indexed database search.
- **Attribution:** Open passports provide a copyable preferred credit line, credit-text download and passport URL. The fallback names the title, creator and contributors. PDFs include the credit and version; audio ZIPs include `ATTRIBUTION.txt`. Restricted or withdrawn records do not expose private credits. Attribution is not permission, verification or copy protection.

Past approval snapshots and ledger entries are preserved when terms, consent or sharing change. Corrections to amounts still append signed ledger adjustments; different currencies remain separate. Registration and roles record claims and delegated access, never verified ownership or cultural authority.

## Recovery and rollback

If the SQL succeeds but upload fails, the previous application can still use its old columns. If the new application reports an upgrade is required, verify that the SQL and migration marker were imported into the same database named in the private configuration.

Once the first database account has been created, prefer repairing or restoring this release over reverting to the old fixed-login application. Old application code cannot enforce new account roles or consent visibility rules. If rollback is necessary, take the site offline, preserve an additional export of the current data, restore the matching pre-upgrade database and release together, then reopen. Restoring a backup loses subsequent changes unless reconciled separately. Never restore just an old public application over the upgraded, actively used database.

## Verification and ownership note

Implementation spans registry, consent/ledger, frontend, demo and QA ownership areas under the user's explicit request for features 1, 3, 4, 5, 6 and 7. Individual accounts supersede the original demo-only-login restriction. No payment provider, analytics, blockchain or ownership verification was added.

Verified locally: full browser journey at 360px and desktop width; corrected stories, preferred credit, private counteroffer acceptance, final approval, demo allocation and sharing pause; no horizontal overflow or browser exceptions in checked pages. Automated checks cover community boundaries, read-only roles, session invalidation, stale and simultaneous edits, consent withdrawal, approval idempotence, expired links, changed counteroffers, mail opt-in and a local spool, public-search privacy, and preservation of existing records during migration. Production SMTP, hosted Apache rules and the phone QR scan require the hosting account and remain deployment checks.
