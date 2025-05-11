# How to Run ArtLoop Application

This guide provides step-by-step instructions for setting up and running the ArtLoop digital art gallery web application.

## Prerequisites

1. **Web Server**: Apache or Nginx
2. **PHP**: Version 7.4 or higher
3. **MySQL**: Version 5.7 or higher
4. **PhpMyAdmin**: For database management (optional)

## Setup Instructions

### 1. Clone or Download the Repository

If you're using Git:
```
git clone https://github.com/yourusername/artloop.git
cd artloop
```

If you downloaded a ZIP file, extract it to your web server's document root directory.

### 2. Database Setup

1. Create a new MySQL database named `artloop`
2. Import the database schema from `database/schema.sql` using PhpMyAdmin:
   - Open PhpMyAdmin in your browser
   - Select the `artloop` database
   - Click on the "Import" tab
   - Choose the file `database/schema.sql`
   - Click "Go" to import the schema

   Alternatively, use the MySQL command line:
   ```
   mysql -h 127.0.0.1 -u username -p artloop < database/schema.sql
   ```

### 3. Configure Database Connection

Open `includes/db_connect.php` and update the database credentials with your MySQL username and password:

```php
$host = '127.0.0.1';
$dbname = 'artloop';
$username = 'your_mysql_username';
$password = 'your_mysql_password';
```

### 4. Set Up File Permissions

Make sure the `uploads` directory is writable by the web server:

```
chmod 755 uploads
```

### 5. Start the Web Server

#### Using XAMPP/MAMP/WAMP

1. Place the project in the `htdocs` (XAMPP/MAMP) or `www` (WAMP) directory
2. Start Apache and MySQL services from the control panel
3. Open your web browser and navigate to `http://localhost/artloop`

#### Using Built-in PHP Server (for development only)

Run the following command in the project root directory:

```
php -S localhost:8000
```

Then open your web browser and navigate to `http://localhost:8000`

### 6. Access the Application

Open your web browser and navigate to:
- XAMPP/MAMP/WAMP: `http://localhost/artloop`
- Built-in PHP server: `http://localhost:8000`

### 7. Default Admin Account

Use these credentials to access the admin panel:
- Username: `admin`
- Password: `admin123`

## Troubleshooting

### Database Connection Issues

- Verify your MySQL service is running
  - On Linux: `sudo systemctl status mysql` or `sudo service mysql status`
  - On macOS: Check System Preferences > MySQL
  - On Windows: Check Services (services.msc) for MySQL service status
- Check that the database credentials in `includes/db_connect.php` are correct
- Ensure the `artloop` database exists
- If you encounter socket connection errors, try using '127.0.0.1' instead of 'localhost' for the host parameter to force a TCP/IP connection

#### Error: Can't connect to MySQL server on '127.0.0.1:3306'

If you see this error, it typically means:
1. MySQL server is not running - start it using your system's service manager
2. MySQL server is not configured to accept TCP/IP connections - check your MySQL configuration file (my.cnf or my.ini) and ensure `skip-networking` is not enabled
3. A firewall is blocking the connection - check your firewall settings and allow connections to port 3306
4. MySQL is running on a different port - check the port in your MySQL configuration and update the connection settings accordingly

You can test the connection using:
```
mysql -h 127.0.0.1 -u username -p -P 3306
```

### Upload Problems

- Check that the `uploads` directory has proper write permissions
- Verify PHP settings allow file uploads (`file_uploads = On` in php.ini)
- Check the upload file size limit in php.ini (`upload_max_filesize` and `post_max_size`)

### Blank Pages or PHP Errors

- Enable error reporting in PHP for debugging:
  ```php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  ```
- Check your web server error logs

## User Roles

The application has three user roles:

1. **Visitor**: Can browse artworks, view details, and search
2. **Artist**: Can upload artworks, manage their profile, and track statistics
3. **Admin**: Has full access to manage users, artworks, and site settings

To register as an artist, select the "Artist" option during registration.
