# Okwethu — Ours

The user requested the Okwethu name, a goldenrod-led palette, a community-empowering visual identity and removal of the word MOCK from the template. This change spans the frontend, copy, logo assets and display formatting. It does not change consent decisions, ledger arithmetic or stored records.

## Identity

- Main name: Okwethu. Wordmark styling: lowercase `okwethu`.
- Meaning supplied by the user: Ours.
- Voice: Our stories. Our say. Our future.
- Original logo: three people held in an O-shaped embrace, with an open story at the centre. The graphic uses simple geometry, without claiming or imitating a specific community's cultural motifs.
- Reusable assets: `public/assets/okwethu-mark.svg`, `okwethu-logo.svg` and `okwethu-logo.png`. SVGs remain crisp at small and large sizes.

## Palette

Sampled directly from the supplied image:

| Role | Colour |
|---|---|
| Goldenrod, primary | #DAA520 |
| Ochre | #B3841A |
| Amber | #EF9521 |
| Apricot | #FFB248 |
| Burnt orange | #C97816 |
| Honey | #FFD27F |
| Bronze | #8A5E10 |
| Pale gold | #FFEAAB |

Supporting colours: dark brown #332513 for body text; warm paper #FFFBF1; darker bronze #79500E for accessible small text. Goldenrod buttons use dark text. No external fonts or tracking resources are loaded.

## Interface changes

New community-led home page, illustrated hero, logo/wordmark, favicon, story cards, goldenrod controls, warm form surfaces, clear focus styles, reduced-motion support and a consistent mobile layout across login, passports and stewardship screens.

MOCK is replaced by Demo in visible template copy. Every simulated financial amount remains clearly labelled Demo, with “no real money moves” shown on the ledger and footer. Stored MOCK_PAYMENT/MOCK_ADJUSTMENT values, API records and historical notes are retained; display helpers provide readable labels. The app still does not move real money or verify ownership.

## Update your existing Xneelo site

1. Back up the existing site files.
2. Upload `okwethu-design-update.zip` into the same subdomain folder as `index.php`.
3. Extract it there and allow its included files to be replaced.
4. Delete the uploaded ZIP. Refresh the site; CSS/JavaScript URLs are versioned to avoid the old design staying cached.

The update contains public assets/index plus private templates and display classes. It does **not** contain `.htaccess`, configuration, credentials, SQL, migrations, session data or uploaded stories. Do not re-import the database. Your existing steward login and uploaded content stay in place. The PHP HTTPS safeguard from the previous fix is retained.

## Verification

Fixture renders check that restricted stories do not leak content and that generated interface pages no longer show the legacy MOCK label. Browser screenshots and layout measurements cover home, login, ledger, open/restricted passports and registration at 1440px and 360px. JavaScript errors, image loading and horizontal overflow are checked. Existing wizard, consent, allocation, upload and transport tests remain part of validation.

Screenshots use clearly labelled fictional fixtures. They are local previews, not proof that the update has been uploaded to Xneelo.
