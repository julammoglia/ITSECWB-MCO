# Milestone 1: ITSECWB-MCO (PluggedIn) 

PluggedIn is an online electronics and accessories store that specializes in high-quality, budget-friendly digital products such as headphones, earphones, keyboards, mics, monitors, speakers, and mice. It is designed to provide a seamless shopping experience, offering multiple currencies for international users (PHP, USD, KRW). The platform supports real-time inventory tracking, transaction logging, and role-based user access, with features for both customers and admins.

Note: This project builds upon an older ITDBADM project that we were permitted to reuse. We refactored and enhanced it to meet the current course requirements (e.g., secure password hashing, generic auth errors, role-based access, and other security-oriented improvements) as instructed by the professor.

Technologies Used:
PHP: Server-side scripting to handle business logic and user interactions.
HTML: For structuring the web pages.
JavaScript: Client-side functionality, such as interactivity and form handling.
SQL: For database management (MySQL via phpMyAdmin)
MySQL: Database for managing users, products, transactions, and more.

## Prerequisites
- Install XAMPP
  - Launch the app and start Apache and MySQL
  - If your MySQL runs on another port (e.g., 3306), you must configure MySQL on the XAMPP interface to port 3307. 

## Database Setup
1) Create database
2) Import pluggedin_itdbadm.sql
3) Verify files

## Application Configuration
- DB connection is set in includes/db.php:
  - Host: 127.0.0.1
  - User: root
  - Password: "" (empty by default on XAMPP)
  - DB name: pluggedin_itdbadm
  - Port: 3307

If your MySQL runs on a different port (e.g., 3306), change the last parameter accordingly:
```php
$conn = new mysqli('127.0.0.1', 'root', '', 'pluggedin_itdbadm', 3306);
```

## Running Locally (XAMPP)
1) Place the project folder at:
- C:xampp/htdocs/ITSECWB-MCO

2) Start XAMPP services
- Start Apache and MySQL 

3) Open in browser
- Public catalog: http://localhost/ITSECWB-MCO/Index.php
- Authentication: http://localhost/ITSECWB-MCO/Login.php
- User dashboard: http://localhost/ITSECWB-MCO/User.php (requires login)
- Admin dashboard: http://localhost/ITSECWB-MCO/Admin.php (requires Admin role)

## Authentication & Passwords
- Registration, login verification, and password reset use password_hash/password_verify with bcrypt.
- Admin-created staff/admin users (Admin.php) now use password_hash (bcrypt) to prevent plaintext storage.
- Login failure message is generic ("Invalid email and/or password") to avoid leaking which field was incorrect.

## Troubleshooting
- Session keeps redirecting to Login:
  - Clear browser cookies; ensure PHP sessions are enabled and writable.

- MySQL port mismatch:
  - Update includes/db.php port to match your MySQL port (e.g., 3306).

## License
- This project was developed solely for academic requirements. 

Team Members:
Nicole Ashley L. Corpuz
Princess Loraine R. Escobar
Julianna Charlize Y. Lammoglia
Tristan Neo M. Mercado