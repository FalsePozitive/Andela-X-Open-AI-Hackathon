# Deploy on Xneelo without SSH

Target: **https://okwethu.wilmans.co.za**. This package fits entirely inside the subdomain's existing web folder. You do not need access to a parent directory or a terminal.

Use the files from the same `xneelo-shared-*` release folder:

- `upload-to-subdomain.zip`: the only ZIP to upload to the subdomain's web folder.
- `import-into-empty-database.sql`: import from your computer through phpMyAdmin; do not put it in the website folder.
- `STEWARD-LOGIN-KEEP-ON-YOUR-COMPUTER.txt`: your generated sign-in details. Keep this on your computer; do not upload it.

## 1. Upload the website

Back up any files already in the subdomain folder. If there is an old index.html, rename or remove it after backing it up so it does not replace the app's homepage. Do not change the parent domain's files or .htaccess.

Open the folder that serves `okwethu.wilmans.co.za`, upload `upload-to-subdomain.zip`, and extract its **contents directly into that folder**. Do not create an extra `public/` or release-name folder. You should see:

```text
subdomain's existing web folder/
  .htaccess
  index.php
  assets/
  _passport/
    .htaccess
    access-check.txt
    config/local.php
    src/
    templates/
    vendor/
    resources/
    storage/uploads/
```

Enable “show hidden files” if needed so both `.htaccess` files are uploaded. Delete the uploaded ZIP from the server after extraction. If the control panel cannot extract ZIP files, extract it on your computer and upload all contents using FTP, including the hidden files.

## 2. Confirm the private folder is protected

Before adding database credentials, open:

https://okwethu.wilmans.co.za/_passport/access-check.txt

You must get **403 Forbidden**, not the text of the file. This verifies that Apache is blocking the private directory. If you see the text, or cannot confirm a 403 response, stop and ask Xneelo to check that `.htaccess` overrides and Apache rewrite/access rules are enabled. Do not remove the protection to make the app work.

The `_passport` directory is inside the web folder but denied to visitors by two Apache rules. PHP can still read it internally. Database settings, uploaded files, templates and libraries stay there. This layout depends on Apache enforcing the included rules; its protection must be checked on the actual host.

## 3. Create and import the database

In the Xneelo Control Panel, open **Web Hosting → your domain → Hosting tools → Manage MySQL**. Create a new, empty database and its database user. Save the database name, username, password and the database host shown by Xneelo. Use those exact values; do not assume the host is localhost.

Open the database's **⋯ menu → phpMyAdmin**. Select the new database on the left, then **Import → Choose file**. Choose `import-into-empty-database.sql` from your computer, leave the format as SQL, and select **Go**.

The file creates the tables and, when generated with a demo export, inserts three clearly labeled placeholder stories and MOCK ledger data. Import it once into an empty database. Do not import it into an existing site database or use it for upgrades.

Official instructions: https://xneelo.co.za/help-centre/control-panel/import-your-database-via-phpmyadmin/

## 4. Edit the database settings

Using File Manager's editor (or edit locally and re-upload), open `_passport/config/local.php`. Replace these four placeholders, preserving quotes and punctuation:

```php
'dsn' => 'mysql:host=REPLACE_DB_HOST;dbname=REPLACE_DB_NAME;charset=utf8mb4',
'db_user' => 'REPLACE_DB_USER',
'db_password' => 'REPLACE_DB_PASSWORD',
```

The four values are database host, database name, database username and database password. A single quote or backslash inside a password must be escaped in a PHP single-quoted string (`\'` or `\\`). Do not paste database credentials into public files.

The site URL, private upload path and generated steward password hash are already set for this package. Leave them as provided. `allow_demo_reset` stays false. Prefer file permissions 600 for the config and 700 for private uploads if your account's PHP runs as your account user. If PHP cannot write there, ask Xneelo for the correct account-specific permissions; do not use 777.

## 5. Open the site and sign in

Visit https://okwethu.wilmans.co.za, then https://okwethu.wilmans.co.za/login. Use the username and password in the **local** steward-login text file generated alongside the ZIP. The application does not send email; use the steward inbox to review permission requests.

In phpMyAdmin, replace the seed's public `steward@example.com` contact before inviting real requests. Select the `licenses` table and edit its `contact_email` values to the steward address you intend to publish. All demo identities and other email addresses are fictional placeholders.

Check one open passport, its QR download, the restricted passport, a blocked commercial approval, consent editing, approval and the MOCK ledger. Scan a QR on your phone; it must open this subdomain. Confirm HTTPS works and that the private-folder check still returns 403 after setup.

## If something goes wrong

- “Story Passport is being prepared”: check the four database values and the PHP error log in your hosting panel. The app deliberately hides sensitive error details from visitors.
- 500 error: check PHP 8.3+ and Apache `.htaccess` support with Xneelo. Do not delete the private-folder deny rules.
- Homepage works but passport pages fail: check that the root `.htaccess` was extracted and rewrite rules are allowed.
- Image uploads/QR fail: enable GD with WebP, PDO MySQL, fileinfo and mbstring via your host. Upload limit must allow 10 MB files (`upload_max_filesize=10M`, `post_max_size=12M`, `memory_limit=256M`).
- Upload ZIP will not extract: extract locally and upload the contents by FTP, including `.htaccess` files.

## Updating later

This is an **initial-install bundle** with a generated login and sample database. Do not overwrite `_passport/config/local.php` or `_passport/storage/` when updating. Do not re-import the starter SQL into an active database. Export a database backup with phpMyAdmin and download the private uploads through File Manager/FTP first. Keep backups on your computer, never in the public website directory.

On-host Apache protection, PHP extensions, database compatibility, phone QR access, browser accessibility, persistence and backup restoration still need to be verified on Xneelo; a locally built ZIP does not certify them.
