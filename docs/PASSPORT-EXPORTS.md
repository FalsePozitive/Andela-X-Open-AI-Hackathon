# QR-marked media, percentage shares and passport PDFs

This user-requested update spans registry validation, media delivery, passport exports, interface copy, About/FAQ, logo spacing and tests. It keeps the original consent and ledger rules.

## What changed

- Share controls accept 0–100% with up to two decimal places and require a 100% total. The sample calculator accepts a normal ZAR amount. Review screens, About/FAQ and PDFs use percentages. Internal integer basis points and money minor units remain unchanged for exact allocation; no database migration is required.
- Registration accepts each contributor's `percentage` as a decimal string, e.g. `"70"` or `"33.33"`. Legacy `bps` inputs still work. If both are present they must agree. Invalid precision, out-of-range shares and totals other than 100% are rejected.
- The margin above the header's “Ours. Together.” tagline is removed.
- `GET /media/:id` serves a QR-marked PNG derivative for open images, including existing uploads. `GET /api/stories/:id/download` downloads the same marked PNG. Originals and their registration hash remain unchanged in private storage. Small images receive a canvas border to leave room for a readable mark.
- Video export runs in the visitor's browser: the QR is drawn permanently into the output frames and audio is retained. It creates WebM, or MP4 where supported, at up to 1280×720 fit resolution, with padding if needed for a legible QR. Keep the tab active; export takes real time. Limits: 10 minutes and 50 MB output. There is cancellation and explicit unsupported-browser/error handling. No server video encoder, microphone or camera access is needed.
- Original open video playback files remain accessible for streaming. Only the dedicated export button creates a permanently marked video. This is attribution support, not copy prevention or DRM.
- Audio download produces a ZIP containing the original audio, a branded PNG cover with a bottom-right QR, and a text passport link. The sound bytes remain unchanged; the QR is on the cover, not in the audio waveform. Keep them together when sharing. PHP ZIP support is needed for this endpoint; other features remain usable without it.
- `GET /api/stories/:id/cover` provides a QR cover for open media. All media/cover/download paths check sensitivity before reading originals or caches. Restricted media remains unavailable publicly.
- `GET /api/stories/:id/pdf` downloads an A4 Okwethu passport snapshot with logo, goldenrod palette, QR, metadata, declared terms, contributor consent and percentage shares. Open images use marked thumbnails. Long story text is explicitly labelled as an excerpt; full text remains on the live passport. Audio/video are linked, not embedded.
- Restricted PDFs use the same minimal public projection as restricted passports, excluding private title, content, context, location and contributor data. PDFs are snapshots, not proof of ownership or permission.

## Install on the existing Xneelo site

Back up the current site files. Upload `okwethu-passport-update.zip` to the subdomain folder containing `index.php`, extract it there and allow its files to be replaced. Delete the uploaded ZIP. This update includes **production PHP libraries under `_passport/vendor/`**: upload those too, not just the templates. Do not re-import the database or overwrite `_passport/config/local.php` or `_passport/storage/`.

Use PHP 8.3+ with the existing GD/WebP, mbstring, DOM/XML and PDO support. Enable the PHP ZIP extension for audio packages if necessary. The existing private storage directory must be writable by PHP: the app creates `derivatives/` and `pdf-cache/` there. Those folders must stay behind the `_passport` access-deny rules. Do not use world-writable permissions or move them into public assets.

## Checks

Automated checks cover exact percentage parsing and allocation, real QR decoding from saved image/video files, original-image preservation, cache variation by URL, audio ZIP contents, PDF output and restricted PDF text, existing consent/ledger logic, API privacy and registration. A generated short video fixture checks retained sound and safe cancellation. PDF pages are rendered and visually inspected before handover.

After uploading, test an existing image, a new percentage registration, an audio ZIP, video export in Chrome/Edge, and both open/restricted PDFs. Check the QR opens the live HTTPS passport. Cropping/removing a mark or downloading an original playback file is still possible; the app never describes marks as guaranteed protection.
