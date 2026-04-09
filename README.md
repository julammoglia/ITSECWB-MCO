# Milestone 1: ITSECWB-MCO (PluggedIn) 

PluggedIn is an online electronics and accessories store that specializes in high-quality, budget-friendly digital products such as headphones, earphones, keyboards, mics, monitors, speakers, and mice. It is designed to provide a seamless shopping experience, offering multiple currencies for international users (PHP, USD, KRW). The platform supports real-time inventory tracking, transaction logging, and role-based user access, with features for both customers and admins.

Note: This project builds upon an older ITDBADM project that we were permitted to reuse. We refactored and enhanced it to meet the current course requirements (e.g., secure password hashing, generic auth errors, role-based access, and other security-oriented improvements) as instructed by the professor.

Technologies Used:
PHP: Server-side scripting to handle business logic and user interactions.
HTML: For structuring the web pages.
JavaScript: Client-side functionality, such as interactivity and form handling.
SQL: For database management (MySQL via phpMyAdmin)
MySQL: Database for managing users, products, transactions, and more.
Docker: Containerized deployment for Render and other hosting platforms.

## Prerequisites
- Install XAMPP
  - Launch the app and start Apache and MySQL
  - If your MySQL runs on another port (e.g., 3306), you must configure MySQL on the XAMPP interface to port 3307. 

## Database Setup
1) Create database
2) Import pluggedin_itdbadm.sql
3) Verify files

Note:
- The schema no longer relies on stored procedures or triggers.
- Product, order, assignment, and audit side effects are now handled in PHP.

If you already have an older local database imported, run the one-time migration:
```bash
/Applications/XAMPP/xamppfiles/bin/php scripts/migrate_legacy_users.php
```

This migration safely:
- adds the missing `phone` column to `users` if needed
- adds a unique index on `users.email` if possible
- converts legacy plaintext passwords in `users.password` to bcrypt hashes

## Application Configuration
Database configuration now comes from environment variables. Local `.env` loading is supported through [`.env.example`](./.env.example), while Render should use dashboard environment variables.

Supported database variables:
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `DB_CHARSET`
- `DB_CONNECT_TIMEOUT`

Optional SSL variables for external MySQL providers such as Aiven:
- `DB_SSL_CA` or `DB_SSL_CA_BASE64`
- `DB_SSL_CERT` or `DB_SSL_CERT_BASE64`
- `DB_SSL_KEY` or `DB_SSL_KEY_BASE64`
- `DB_SSL_CIPHER`
- `DB_SSL_VERIFY_SERVER_CERT`

Turnstile variables:
- `TURNSTILE_SITE_KEY`
- `TURNSTILE_SECRET_KEY`

## Running Locally (XAMPP)
1) Place the project folder at:
- C:xampp/htdocs/ITSECWB-MCO

2) Start XAMPP services
- Start Apache and MySQL 

3) Configure local environment
- Copy `.env.example` to `.env`
- Set local database values, for example:
  - `DB_HOST=127.0.0.1`
  - `DB_PORT=3307`
  - `DB_NAME=pluggedin_itdbadm`
  - `DB_USER=root`
  - `DB_PASSWORD=`

4) Open in browser
- Public catalog: http://localhost/ITSECWB-MCO/Index.php
- Authentication: http://localhost/ITSECWB-MCO/Login.php
- User dashboard: http://localhost/ITSECWB-MCO/User.php (requires login)
- Admin dashboard: http://localhost/ITSECWB-MCO/Admin.php (requires Admin role)

## Deployment on Render with Docker
This repository now includes a [Dockerfile](./Dockerfile) for Render deployment.

Recommended setup:
1. Create an external MySQL database such as Aiven.
2. Import [pluggedin_itdbadm.sql](./assets/pluggedin_itdbadm.sql) into that database.
3. Create a new Render Web Service from this repo.
4. Render will detect the Dockerfile and build the container.
5. Add these environment variables in Render:
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASSWORD`
   - `TURNSTILE_SITE_KEY`
   - `TURNSTILE_SECRET_KEY`
6. If your external DB requires SSL, also add:
   - `DB_SSL_CA_BASE64` with the base64-encoded CA certificate
   - optionally `DB_SSL_VERIFY_SERVER_CERT=true`

Notes:
- The container binds Apache to Render's `PORT` automatically.
- Uploaded profile pictures, logs, and debug config remain filesystem-based, so use a persistent disk or external object storage if you need durable file storage in production.

## Authentication & Passwords
- Registration, login verification, and password reset use password_hash/password_verify with bcrypt.
- Admin-created staff/admin users (Admin.php) now use password_hash (bcrypt) to prevent plaintext storage.
- Login failure message is generic ("Invalid email and/or password") to avoid leaking which field was incorrect.

## Troubleshooting
- Session keeps redirecting to Login:
  - Clear browser cookies; ensure PHP sessions are enabled and writable.

- Database connection failure:
  - Verify `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`.
  - Check `logs/error.log` for the latest connection error.

- External MySQL SSL issues:
  - Confirm the CA certificate is supplied using `DB_SSL_CA` or `DB_SSL_CA_BASE64`.
  - Make sure the DB host and port match your provider settings.

## License
- This project was developed solely for academic requirements. 

Team Members:
Nicole Ashley L. Corpuz
Princess Loraine R. Escobar
Julianna Charlize Y. Lammoglia
Tristan Neo M. Mercado
