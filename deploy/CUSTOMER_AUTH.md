# Customer sign-in setup

Email/password login uses PHP password hashing and the existing session and rate-limit helpers. Registration and password recovery send an email code before saving the password. Existing email-code users can choose **Forgot password or signing in for the first time?** to establish a password. Passwords must contain at least 12 characters and no more than 72 bytes.

## Existing database

Before deploying the new PHP files, back up the database, then run:

```sh
php scripts/migrate_customer_auth.php
```

This CLI-only command adds missing authentication columns and can be rerun. Alternatively, apply `migrations/003_customer_password_google.sql` once. Fresh installations already include the columns in `setup.sql` and `deploy/deploy.sql`.

Configure `SENDGRID_API_KEY` and `MAIL_FROM` for verification emails. In production use `APP_ENV=production`, a random `APP_KEY`, `APP_SHOW_OTP=0`, and HTTPS. Development code display must never be enabled in production.

## Enable Continue with Google

1. Open Google Cloud Console, create/select a project, and configure the Google Auth Platform branding, audience, and consent screen. While the app is in testing, add your intended test accounts.
2. Create an OAuth client of type **Web application**.
3. Add the exact callback URL as an authorized redirect URI. Examples:
   - Current preview: `http://127.0.0.1:8081/google_callback.php`
   - XAMPP: `http://localhost/campusexpress/google_callback.php`
   - Production: `https://YOUR-DOMAIN/google_callback.php`
4. Set these values in the deployment's `.env` (never commit the client secret):

```dotenv
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://127.0.0.1:8081/google_callback.php
```

Use the callback for the host you actually browse; localhost and 127.0.0.1 are different hosts. Restart your PHP service if environment variables are injected by the server. PHP cURL with a working CA trust store is required; TLS verification remains enabled.

The button activates when the three configuration values are valid. It uses a server-side authorization-code exchange with state, a ten-minute flow limit, and PKCE, then obtains the verified identity from Google's UserInfo endpoint. It requests only openid, email, and profile. Access tokens are not stored.

Google accounts are identified by Google's stable `sub` value. An existing email/password account is never automatically linked merely because the email matches: use the established password/recovery flow for that account. Linking Google to an existing account requires a future explicit account-linking feature.

Official references: https://developers.google.com/identity/protocols/oauth2/web-server and https://developers.google.com/identity/openid-connect/openid-connect

## Validation

Run PHP syntax checks and the local integration check against a running PHP development server:

```sh
php tests/customer_auth_integration.php http://127.0.0.1:8081
```

The integration check only permits loopback URLs and refuses production mode. It creates a uniquely named example.invalid test account, uses development OTP display, and deletes only that account and its OTP records afterward. It checks CSRF rejection, verification, hashing, correct/incorrect passwords, password recovery, and invalid Google callbacks. It does not send real Google requests or real email. Google end-to-end login must be tested after configuring an OAuth client.
