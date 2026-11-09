# Fix repeated form expiry over HTTP

On 9 November 2026, read-only checks reproduced the problem on the live site: HTTP served a session token but changed it on the next request, while HTTPS retained the same token and the login form token matched the session. The production session cookie is deliberately Secure and cannot be sent over HTTP.

Immediate solution: open **https://okwethu.wilmans.co.za/login**.

## Prevent recurrence

Back up the subdomain's current `.htaccess` and `_passport/src/Web.php`. Upload `https-login-fix.zip` to the same subdomain folder as `index.php`, then extract it and allow these **two files** to be replaced:

- `.htaccess`: preserves private-folder protection, adds a permanent 308 redirect to the configured HTTPS domain.
- `_passport/src/Web.php`: redirects production HTTP requests before starting a session, and sends a host-only HSTS policy on HTTPS. Secure session cookies and CSRF validation remain enabled.

Delete the uploaded ZIP afterward. This fix contains no database settings, credentials, SQL import or uploaded content. Do not re-import the database or replace your configuration.

Test: open `http://okwethu.wilmans.co.za/login`; the address should change to HTTPS. Sign in using the existing login. Check `https://okwethu.wilmans.co.za/_passport/access-check.txt` still returns 403.

The updated source passed 15 focused tests (2,535 assertions), PHPStan and syntax checks. The Apache redirect needs verification after uploading; it has not been deployed by the assistant. If the host terminates HTTPS at a proxy and a redirect loop occurs, restore the two backed-up files and ask Xneelo about the correct trusted HTTPS indicator.

Official Xneelo guidance: https://xneelo.co.za/help-centre/control-panel/force-https-using-a-htaccess-file/
