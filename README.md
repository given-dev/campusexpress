# CampusExpress

A campus food delivery marketplace connecting vendors to students in Kampala, Uganda.

## Features

- **Student Marketplace** — Browse food items from campus vendors
- **WhatsApp Ordering** — Order directly via WhatsApp
- **Customer Sign In** — Email/password login with email verification and password recovery using SendGrid
- **Google Sign In** — Optional Google OAuth login; configure using [customer authentication setup](deploy/CUSTOMER_AUTH.md)
- **Vendor Dashboard** — Manage menu items and track sales
- **Admin Panel** — Review vendor applications and manage listings
- **Mobile Responsive** — Optimized for smartphone users

## Tech Stack

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP 8.0+
- **Database:** MySQL
- **Email:** SendGrid API
- **Container:** Docker

## Credentials

### Admin Accounts (existing in database)

| Username | Email | Password |
|----------|-------|----------|
| Admin | admin@campusexpress.com | *see below* |
| Owner | owner@example.com | *see below* |

> Passwords are hashed in the database. Use the reset script to set a new password.

### Reset Admin Password

```bash
php scripts/reset_admin_password.php
```

Then enter:
- Admin email
- New password (min 12 characters)

### Create New Admin

```bash
ADMIN_EMAIL=newadmin@example.com ADMIN_PASSWORD=your-secure-password php scripts/create_admin.php
```

### Vendor Accounts

Vendors register at `register.php` and are approved by admin. Login at `login.php`.

### Student Accounts

Students sign in with email and password at `user_login.php`. Registration and password recovery verify a 6-digit email code before saving the password. Google sign-in is optional and requires OAuth credentials. Run `php scripts/migrate_customer_auth.php` for existing databases; see [customer authentication setup](deploy/CUSTOMER_AUTH.md).

---

## Installation

### Local Development (XAMPP)

1. Clone the repository:
```bash
git clone https://github.com/given-dev/campusexpress.git
```

2. Copy to XAMPP htdocs:
```bash
cp -r campusexpress C:/xampp/htdocs/
```

3. Start Apache and MySQL from XAMPP Control Panel

4. Create database:
```bash
mysql -u root -p < setup.sql
```

5. Create admin account:
```bash
ADMIN_EMAIL=admin@campusexpress.com ADMIN_PASSWORD=your-secure-password php scripts/create_admin.php
```

6. Configure environment:
```bash
cp .env.example .env
# Edit .env with your settings
```

7. Access the site:
```
http://localhost/campusexpress/
```

### Docker

```bash
docker build -t campusexpress .
docker run -p 8080:8080 campusexpress
```

## Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `APP_ENV` | `production` or `development` | Yes |
| `APP_KEY` | Secret key (32+ chars) | Yes |
| `DB_HOST` | MySQL host | Yes |
| `DB_NAME` | Database name | Yes |
| `DB_USER` | MySQL username | Yes |
| `DB_PASS` | MySQL password | Yes |
| `SENDGRID_API_KEY` | SendGrid API key | For email OTP |
| `MAIL_FROM` | Verified sender email | For email OTP |

## Project Structure

```
campusexpress/
├── index.php              # Main marketplace page
├── user_login.php         # Student email OTP login
├── verify_otp.php         # OTP verification
├── login.php              # Vendor login
├── register.php           # Vendor registration
├── dashboard.php          # Vendor dashboard (sales analytics)
├── admin_login.php        # Admin login
├── admin_dashboard.php    # Admin panel (approve vendors)
├── track_order.php        # Order tracking API
├── save_item.php          # Add menu item
├── edit_item.php          # Edit menu item
├── update_item.php        # Update menu item
├── delete_item.php        # Delete menu item
├── db.php                 # Database & helpers
├── mailer.php             # SendGrid email sender
├── style.css              # Global styles
├── setup.sql              # Database schema
├── .env.example           # Environment template
├── scripts/
│   ├── create_admin.php   # Create admin account
│   └── reset_admin_password.php  # Reset admin password
└── uploads/               # Food images
```

## Admin Access

Admin login is hidden from the public. Access directly:
```
http://localhost/campusexpress/admin_login.php
```

## Security Features

- Password hashing with bcrypt
- CSRF protection on all forms
- Rate limiting on login attempts (5 per 15 min)
- Session regeneration on login
- Image upload validation (MIME, dimensions, EXIF stripping)
- OTP hashing before database storage
- SQL injection prevention (prepared statements)

## License

Private — CampusExpress 2026
