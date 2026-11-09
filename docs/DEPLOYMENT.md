# Xneelo deployment and rollback

**No SSH or access above public_html?** Use [the shared-hosting instructions](DEPLOYMENT-SHARED.md) and the xneelo-shared bundle. It fits inside the subdomain directory and uses phpMyAdmin import. The layout below is the alternative for accounts with access outside the web root.

## Hosting layout

Target: **https://okwethu.wilmans.co.za** (supplied by the user). Its document root must contain **only the contents of `public/`**, including `.htaccess`. Keep the rest of the release outside that root:

```text
account/
  passport-app/             src/, templates/, vendor/, config/, database/, bin/
  passport-private/         uploads/ (writable by PHP; not web-accessible)
  public_html/              index.php, .htaccess, assets/ (subdomain document root)
```

For this split layout, change the single bootstrap path in public `index.php` from `dirname(__DIR__) . '/src/bootstrap.php'` to the absolute `passport-app/src/bootstrap.php` path. Alternatively deploy the entire release outside public_html and set the subdomain document root to its `public/` directory. Do not upload the whole repository into public_html. The repository-root deny rule is a defense in depth, not a substitute for the correct document root.

## Prepare

1. Select PHP 8.3 or later. Verify PDO MySQL, GD with WebP, fileinfo, mbstring and session support.
2. Create a dedicated MySQL/MariaDB database. Use InnoDB with utf8mb4. Set the application connection to UTC.
3. Set PHP `upload_max_filesize=10M`, `post_max_size=12M`, and `memory_limit=256M`. The app independently caps files at 10 MB and requests at 11 MB. Apache must allow `.htaccess` rewrites. HTTPS must be active.
4. Set up `config/local.php` outside the public root, from the example. Use the actual HTTPS subdomain as `base_url`, or QR links will point to the wrong site. Set the absolute private storage path, a strong password hash, and `allow_demo_reset=false`.
5. Keep secrets readable only by the hosting account/PHP process. Do not include local or test configuration in a release.

## Build and package

Run `composer install`, `npm ci`, all checks, and `npm run build` locally. Then run `php bin/package.php`. This creates a ZIP under ignored `dist/`, using an allowlist of source/config-example/public assets, migrations and production Composer dependencies. It excludes development tools, tests, runtime files, local configuration, and passwords. Node and Composer are not needed on the hosting server after uploading.

Do not replace the server's config or private storage when deploying a new release. Keep the prior release and a database export outside the web root.

## Initial deploy

1. Upload and extract the release outside the document root, then point the subdomain to `public/` or install the split layout above.
2. Run `php bin/console.php preflight`, then `php bin/console.php migrate` using SSH. Xneelo shared-hosting SSH may need to be enabled through support. If SSH is unavailable, import the versioned migration SQL through the database administration panel and record the migration version. Do not create a public setup/reset endpoint.
3. For a dedicated demo database only, run `php bin/console.php seed`. With no SSH, generate a seed SQL export locally and import it into the empty demo database; copy only the generated placeholder media to private uploads.
4. Replace the seed steward contact `steward@example.com` with an address you intend to publish before inviting real requests. Seed identities and requester emails remain explicitly fictional. The app does not send email.
5. Test direct and nested routes, login/logout, CSRF rejection, QR PNG download, open media, protected media denial, claim reporting and the full consent → approval → ledger journey.

Use a separate migration account for schema changes. Runtime database grants should allow SELECT/INSERT on `ledger_entries` and `consents`, with no UPDATE/DELETE; grant the other tables only the SELECT/INSERT/UPDATE access the app needs. Do not grant schema-management or database-wide destructive privileges to the web user.

## Persistence, backup and rollback

- Record a story and upload media. Redeploy the same package; verify the same passport, consent history and media still work. The deploy must never run seed/reset automatically.
- Back up database plus private uploads together. Store both outside the public root; restrict access. Restore into a separate database first and check consistency.
- Roll back code by restoring the previous release and preserving configuration/storage. For a schema incompatibility, restore the matched database backup during maintenance; do not run destructive reverse migrations against active data.
- Check hosting PHP error logs after release. Errors are generic to visitors. No viewer analytics or application viewer records are created; hosting-level access logs are controlled by the provider.

## Release gate requiring account access

Real HTTPS/subdomain routing, provider PHP extensions, server filesystem permissions, account database grants, external-phone QR scan, redeployment persistence and backup restoration must be checked on Xneelo. Local tests cannot certify these. Do not describe the app as live until these pass.

