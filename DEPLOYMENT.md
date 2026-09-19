# Private staging deployment

The application is packaged as a Dockerized PHP 8.3 Apache service. It needs a MySQL database and persistent storage for `uploads/`.

## External accounts required

- A Docker-capable PHP host such as Railway, Render, Fly.io, or a VPS
- A managed MySQL database
- A Resend account with a verified sending domain
- A temporary staging domain protected by the host's access controls or HTTP basic authentication

## Environment variables

Copy every key from `.env.example` into the host's secret/environment settings. Never upload a real `.env` file or commit secrets.

Generate `APP_KEY` with:

```powershell
C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Set `APP_ENV=production` and `APP_SHOW_OTP=0` on staging so the staging environment exercises the real security path.

## Database

1. Create the managed MySQL database.
2. Import `setup.sql` into an empty database.
3. For an existing database, run `migrations/001_production_auth.sql` once.
4. Set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` on the host.

## Administrator

After deployment, open a one-off shell/command session on the host and run:

```sh
ADMIN_EMAIL=owner@example.com ADMIN_PASSWORD='a-long-unique-password' ADMIN_NAME='Owner' php scripts/create_admin.php
```

Do not use the local demonstration administrator password on staging or production.

## Real OTP email

1. Add and verify your sending domain in SendGrid.
2. Create a restricted SendGrid API key.
3. Set `SENDGRID_API_KEY` in the host's secret settings.
4. Set `MAIL_FROM` to a sender on the verified domain, for example `login@example.com`.
5. Keep `APP_SHOW_OTP=0`.

The application sends `POST https://api.sendgrid.com/v3/mail/send` with bearer authentication. Failed delivery invalidates the newly generated OTP.

## Persistent uploads

Mount a persistent volume at:

```text
/var/www/html/uploads
```

Without persistent storage, product images may disappear after a restart or deployment. An object-storage migration is recommended before a larger public launch.

## Health check

Configure the host to check:

```text
/health.php
```

A healthy service returns HTTP 200 with `{"status":"ok"}`.

## Private access

Until acceptance testing is complete, restrict staging through the hosting platform's access controls, VPN, or HTTP basic authentication. Do not rely on an unlisted URL.

## Acceptance test

- HTTPS redirects and remains active on every page.
- `health.php` returns HTTP 200.
- A real OTP email arrives and the code works only once.
- Five bad vendor or administrator passwords trigger a 15-minute limit.
- Vendor registration requires administrator approval.
- Uploaded images remain after a service restart.
- Direct access to `.env`, `.sql`, `.md`, and PHP files inside `uploads/` is denied.
- The default local administrator credentials do not work.