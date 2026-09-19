# CampusExpress Deployment Guide — cPanel

## Step 1: Create Database in cPanel

1. Log into cPanel at `https://das131.truehost.cloud:2083`
2. Go to **MySQL Databases**
3. Create a new database: `afriboar_campusexpress`
4. Create a new user: `afriboar_campus` with a strong password
5. **Write down the password** — you'll need it for `.env`
6. Add the user to the database with **ALL PRIVILEGES**

## Step 2: Import Database

1. Go to **phpMyAdmin** in cPanel
2. Select the `afriboar_campusexpress` database
3. Click **Import** tab
4. Upload `deploy.sql` (from this folder)
5. Click **Go** to run the import

## Step 3: Upload Files

1. Go to **File Manager** in cPanel
2. Navigate to `public_html/` (or a subfolder if using one)
3. Upload ALL files from the `campusexpress` folder:
   - All `.php` files
   - `style.css`
   - `setup.sql`, `.htaccess`
   - `favicon-32.png`, `logo-128.png`
   - `migrations/` folder
   - `scripts/` folder
   - `uploads/` folder (create it if missing)
4. **Do NOT upload** `.env`, `.env.production`, `.git/`, `.gitignore`

## Step 4: Configure .env

1. In File Manager, create a new file called `.env`
2. Paste this content (update the password):

```
APP_ENV=production
APP_KEY=46131b23ccf0d8f46f3513853d18536fe72e94c8130878e087308efcbbf0ef9b
APP_SHOW_OTP=0
DB_HOST=localhost
DB_NAME=afriboar_campusexpress
DB_USER=afriboar_campus
DB_PASS=YOUR_DATABASE_PASSWORD_HERE
SENDGRID_API_KEY=
MAIL_FROM=noreply@ce.afriboards.com
```

Replace `YOUR_DATABASE_PASSWORD_HERE` with the password you created in Step 1.

## Step 5: Create Admin Account

1. In cPanel, go to **Terminal** (or **SSH Access** if available)
2. Run:

```bash
cd /home/afriboar/public_html
ADMIN_EMAIL=admin@ce.afriboards.com ADMIN_PASSWORD='YourSecurePassword123!' ADMIN_NAME='Admin' php scripts/create_admin.php
```

If Terminal is not available, create a temporary PHP script:

1. Create file `create_admin_temp.php` in File Manager:
```php
<?php
require 'db.php';
$email = 'admin@ce.afriboards.com';
$password = 'YourSecurePassword123!';
$name = 'Admin';
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO admins (username, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $hash);
$stmt->execute();
echo "Admin created! Delete this file now!";
?>
```
3. Visit `https://ce.afriboards.com/create_admin_temp.php`
4. **DELETE the file immediately after**
5. Delete `create_admin_temp.php`

## Step 6: Set Permissions

In File Manager:
- `uploads/` folder → permissions **755**
- All other folders → permissions **755**
- `.env` file → permissions **644** (or **400** if available)

## Step 7: Test

1. Visit `https://ce.afriboards.com/` — marketplace should load
2. Visit `https://ce.afriboards.com/admin_login.php` — admin login
3. Login with the admin credentials you created

## Admin Login
- **URL**: `https://ce.afriboards.com/admin_login.php`
- **Email**: `admin@ce.afriboards.com`
- **Password**: Whatever you set in Step 5

## Troubleshooting

**500 Error?**
- Check `.env` file exists and has correct database credentials
- Make sure `uploads/` folder exists with 755 permissions

**Database connection failed?**
- Verify database name, user, and password in `.env`
- Make sure the user was assigned to the database with ALL PRIVILEGES

**Images not uploading?**
- Check `uploads/` folder exists and is writable (755)
- Check PHP version is 7.4+ (cPanel → Select PHP Version)
