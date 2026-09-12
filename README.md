# Summer Fair CRM — Live Server Version

This package is prepared for a PHP/MySQL live server such as cPanel shared hosting.

## Server requirements

- PHP 8.1 or newer
- MySQL 5.7+ or MariaDB 10.4+
- PDO MySQL extension
- Apache with `.htaccess` support, or equivalent security rules on Nginx
- SSL/HTTPS certificate

## cPanel deployment

1. In cPanel, open **MySQL Databases**.
2. Create a database, for example `youruser_summerfair`.
3. Create a database user with a strong password.
4. Add the database user to the database and grant **ALL PRIVILEGES**.
5. Upload and extract this project into `public_html/summer-fair/` or your chosen folder.
6. Open `https://yourdomain.com/summer-fair/install.php`.
7. Enter the cPanel database details and the exact application URL.
8. After installation succeeds, delete `install.php` from the server.
9. Confirm that `install.lock` remains in place.
10. Open `login.php` and sign in.

## Accounts created by installer

- Primary admin: `evan` / `Qwaszx92837465@`
- Admin: `shaif` / `shaif123`
- Admin: `fahad` / `fahad123`
- Viewer: `marcus` / `marcus321`
- Viewer: `james` / `james321`

Change the shorter default passwords immediately before real use. They are hashed in MySQL, but `shaif123`, `fahad123`, `marcus321`, and `james321` are weak passwords.

## Manual configuration option

Instead of using the installer:

1. Import `database_live.sql` into your existing database.
2. Copy `app_config.example.php` to `app_config.php`.
3. Edit the database values and application URL.
4. Temporarily upload/run a trusted user-seeding script or use the installer once.

## Important production security

- Use HTTPS only.
- Delete `install.php` after installation.
- Do not expose `app_config.php` publicly.
- Set `app_config.php` permission to `600` or `640` where supported.
- Keep regular database backups.
- Use a dedicated database user, never the hosting root account.
- Change all default account passwords.
- Restrict access by IP or add two-factor authentication if payment data is sensitive.

## Main entry point

`https://yourdomain.com/summer-fair/login.php`
