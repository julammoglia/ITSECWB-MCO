# PluggedIn (ITSECWB-MCO)

PluggedIn is a PHP + MySQL electronics e-commerce web application with customer, staff, and admin workflows. This README is written mainly as a setup and deployment guide for local XAMPP use and Render + Aiven deployment.

## Project Summary

### Customer features
- Register and sign in
- Edit profile and change password
- Browse products
- Add and remove favorites
- Add to cart and checkout
- View order history
- Delete account

### Admin features
- Add and delete products
- Update stock
- Add staff accounts
- Delete staff accounts
- Delete customer accounts
- Update order status
- Export audit logs

### Security features
- Prepared statements
- CSRF protection
- Server-side validation
- Stored XSS protections
- Session timeout handling
- Audit logging
- Debug and generic error modes

## Tech Stack
- PHP 8.2
- MySQL
- HTML, CSS, JavaScript
- Docker
- Render
- Aiven MySQL

## Important Notes
- The project is SQL-based.
- Stored procedures are no longer used.
- SQL triggers are no longer used.
- Their previous behavior was moved into PHP.
- The schema is compatible with stricter managed MySQL environments.
- Linux hosting is case-sensitive, so links must use the real filenames such as `Index.php`.

Schema file:
- [pluggedin_itdbadm.sql](./assets/pluggedin_itdbadm.sql)

## Environment Variables

The app uses environment variables for database and deployment configuration.

See:
- [`.env.example`](./.env.example)

Main variables:
- `APP_ENV`
- `APP_DEBUG`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `DB_CHARSET`
- `DB_CONNECT_TIMEOUT`
- `TURNSTILE_SITE_KEY`
- `TURNSTILE_SECRET_KEY`

Optional SSL variables for external MySQL:
- `DB_SSL_CA`
- `DB_SSL_CA_BASE64`
- `DB_SSL_CERT`
- `DB_SSL_CERT_BASE64`
- `DB_SSL_KEY`
- `DB_SSL_KEY_BASE64`
- `DB_SSL_CIPHER`
- `DB_SSL_VERIFY_SERVER_CERT`

## Local Setup (XAMPP)

1. Place the project in:
   - `C:\xampp\htdocs\ITSECWB-MCO`
2. Start Apache and MySQL in XAMPP.
3. Create a local MySQL database.
4. Import [pluggedin_itdbadm.sql](./assets/pluggedin_itdbadm.sql).
5. Copy `.env.example` to `.env`.
6. Configure local values, for example:

```env
APP_ENV=production
APP_DEBUG=false
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=pluggedin_itdbadm
DB_USER=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_CONNECT_TIMEOUT=10
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
```

7. Open:
   - `http://localhost/ITSECWB-MCO/Index.php`

## Deployment on Render

This repository is ready for Docker deployment on Render.

Included deployment files:
- [Dockerfile](./Dockerfile)
- [apache-vhost.conf](./docker/apache-vhost.conf)
- [start-apache.sh](./docker/start-apache.sh)

### Render setup
1. Push the latest project code to GitHub.
2. Create a new Render Web Service.
3. Choose `Docker` as the environment.
4. Leave `Root Directory` blank if deploying from the repo root.
5. Add the environment variables listed below.
6. Deploy the service.

## Database Deployment with Aiven

### Aiven setup
1. Create an Aiven MySQL service.
2. Use the provided database, host, port, username, and password.
3. Import [pluggedin_itdbadm.sql](./assets/pluggedin_itdbadm.sql) into `defaultdb` or your selected database.
4. Copy the CA certificate from Aiven if SSL is required.

### Required Render environment variables
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `TURNSTILE_SITE_KEY`
- `TURNSTILE_SECRET_KEY`

### Recommended variables
- `APP_DEBUG=false`
- `DB_CHARSET=utf8mb4`
- `DB_CONNECT_TIMEOUT=10`
- `DB_SSL_VERIFY_SERVER_CERT=true`

### Aiven SSL notes
Use one of:
- `DB_SSL_CA`
- `DB_SSL_CA_BASE64`

The app supports:
- full raw PEM certificate text pasted into `DB_SSL_CA_BASE64`
- or base64-encoded certificate contents

Optional client SSL variables:
- `DB_SSL_CERT`
- `DB_SSL_CERT_BASE64`
- `DB_SSL_KEY`
- `DB_SSL_KEY_BASE64`
- `DB_SSL_CIPHER`

## Turnstile / CAPTCHA

If login or registration CAPTCHA does not load on the deployed site:
- verify `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY`
- make sure your Render hostname is allowed in Cloudflare Turnstile
- include local hostnames too if needed:
  - `localhost`
  - `127.0.0.1`

## Logs and Debugging

Audit log:
- [security.log](./logs/security.log)

Error log:
- [error.log](./logs/error.log)

Audit logging includes:
- authentication events
- transaction events
- administrative actions

The admin dashboard can export audit logs as CSV.

Debug mode can be controlled by:
- `config/debug_mode.json`
- `APP_DEBUG=true` or `APP_DEBUG=false`

Recommended deployment value:
- `APP_DEBUG=false`

## Deployment Troubleshooting

### Generic error page on Render
- temporarily set `APP_DEBUG=true`
- reload the page
- inspect the detailed debug output
- set `APP_DEBUG=false` again after fixing the issue

### MySQL SSL connection problems
- verify the Aiven host, port, DB name, username, and password
- verify the CA certificate value
- confirm `DB_SSL_VERIFY_SERVER_CERT=true`

### 404 on Render but not on XAMPP
- check filename case
- `Index.php` and `index.php` are different on Linux

### First request is slow
- Render free instances may sleep during inactivity
- the first request may take longer while the service wakes up

## Demo / Production Notes
- The app is accessible via the internet when deployed on Render.
- Render provides HTTPS for the deployed app.
- Uploaded files and log files are still container filesystem-based.
- Checkout is application-simulated and not connected to a real payment gateway.

## Team
- Nicole Ashley L. Corpuz
- Princess Loraine R. Escobar
- Julianna Charlize Y. Lammoglia
- Tristan Neo M. Mercado
