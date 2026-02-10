# ITDBADM-MP
PluggedIn - Online Electronics and Accessories Store
Description

PluggedIn is an online electronics and accessories store that specializes in high-quality, budget-friendly digital products such as headphones, earphones, keyboards, mics, monitors, speakers, and mice. It is designed to provide a seamless shopping experience, offering multiple currencies for international users (PHP, USD, KRW). The platform supports real-time inventory tracking, transaction logging, and role-based user access, with features for both customers and admins.

- ------------------------------------------------------------------------------------------------------
Technologies Used:

PHP: Server-side scripting to handle business logic and user interactions.
HTML: For structuring the web pages.
JavaScript: Client-side functionality, such as interactivity and form handling.
SQL: For database management (MySQL via phpMyAdmin)
MySQL: Database for managing users, products, transactions, and more.
- ------------------------------------------------------------------------------------------------------

Setup Instructions:
1. Install XAMPP/WAMP (If not installed already)

Launch XAMPP/WAMP and start Apache and MySQL services.

- ------------------------------------------------------------------------------------------------------
2. Set up the Database in phpMyAdmin

Access phpMyAdmin:

Open your browser and navigate to http://localhost/phpmyadmin.

Create a New Database:
- In phpMyAdmin, click on the "Databases" tab.
- Create a new database called pluggedin_itdbadm (you can choose a different name if needed).
- Click "Create".

Import the SQL File:
- After the database is created, go to the Import tab in phpMyAdmin.
- Choose the pluggedin_itdbadm.sql file (provided in the project).
- Click Go to import the database.

- ------------------------------------------------------------------------------------------------------

3. Add the Project Files
Copy the project files into the htdocs folder (for XAMPP) or www folder (for WAMP).

For example, if you're using XAMPP, your files should be placed in C:\xampp\htdocs\PluggedIn.

- ------------------------------------------------------------------------------------------------------

4. Configure Stored Procedures and Triggers
The pluggedin_itdbadm.sql file contains the stored procedures and triggers that are required for the application. After importing the database, these will be automatically added to the MySQL server. Here’s a brief overview of what they do:

Stored Procedures: These are used for managing business logic, such as inserting and updating transactions or managing user roles and data.

Triggers: These will ensure that certain actions (such as updates or deletes) trigger automatically in the system (for example, updating inventory levels or logging transactions).

Make sure these are created successfully by checking the Procedures and Triggers tabs in phpMyAdmin.

- ------------------------------------------------------------------------------------------------------

5. Access the Project in Your Browser
Navigate to the project directory in your browser by typing the following URL:

http://localhost/PluggedIn

This will load the home page of the store where you can start interacting with the platform.

- ------------------------------------------------------------------------------------------------------

Team Member	Role
Lianne Maxene Balbastro	FrontEnd DB Admin
Julianna Charlize Lammoglia	FrontEnd BackEnd
Maria Alyssa Mansueto	DB Admin BackEnd
Edriel Santamaria	DB Admin BackEnd