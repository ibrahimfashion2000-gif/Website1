# Installation & Setup Guide

## Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- HTTPS certificate (for production)

---

## 1. Clone/Setup Repository

```bash
cd /var/www/html
git clone https://github.com/ibrahimfashion2000-gif/Website1.git
cd Website1
```

---

## 2. Configure Environment Variables

### Create .env file from template
```bash
cp .env.example .env
```

### Edit .env with your configuration
```bash
nano .env
```

### Required settings
```env
DB_HOST=localhost
DB_NAME=automagic_erp
DB_USER=root
DB_PASS=your_secure_password

SESSION_TIMEOUT_MINUTES=30
MAX_LOGIN_ATTEMPTS=5
LOGIN_ATTEMPT_WINDOW_MINUTES=15

SECURE_COOKIES=true
BRAND_NAME=YourBrand
APP_DEBUG=false
```

---

## 3. Set File Permissions

```bash
# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Make certain files executable (if needed)
chmod +x install.sh (if exists)

# Ensure .env is secure
chmod 600 .env
```

---

## 4. Create Database

### Option A: Using MySQL Command Line
```sql
CREATE DATABASE automagic_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE automagic_erp;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR(255) NOT NULL,
    delivery_status VARCHAR(50),
    total_amount DECIMAL(10, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (delivery_status),
    INDEX idx_created (created_at)
);

-- Products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    stock_quantity INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category)
);

-- Order Items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_order (order_id)
);

-- Expenses table
CREATE TABLE expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
);

-- Income table
CREATE TABLE income (
    id INT PRIMARY KEY AUTO_INCREMENT,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
);
```

### Option B: Using MySQL GUI (phpMyAdmin, MySQL Workbench)
1. Create database `automagic_erp`
2. Run the SQL from above

---

## 5. Create Admin User

```sql
USE automagic_erp;

-- Replace 'admin_password' with your actual password
-- Password must be at least 8 characters
INSERT INTO users (username, password, active) VALUES (
    'admin',
    '$2y$10$your_hashed_password_here',
    1
);
```

### To generate password hash in PHP:
```php
<?php
$password = 'YourSecurePassword123';
$hashed = password_hash($password, PASSWORD_BCRYPT);
echo $hashed;
// Use this output in the SQL INSERT above
?>
```

---

## 6. Test Login

### Start your web server
```bash
# If using built-in PHP server (development only)
php -S localhost:8000

# Or use Apache/Nginx
sudo systemctl start apache2
```

### Access login page
```
http://localhost:8000/login.php
```

### Login credentials
- Username: `admin`
- Password: `YourSecurePassword123`

---

## 7. Verify Installation

### Check after login
- ✅ Dashboard loads without errors
- ✅ Charts display correctly
- ✅ Statistics show accurate counts
- ✅ No sensitive data in error messages

### Check error logs
```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/php-errors.log
```

---

## 8. Production Deployment

### Before going live

1. **Security**
   ```bash
   # Ensure .env is git-ignored
   grep ".env" .gitignore
   
   # Make .env readable only by web server
   chown www-data:www-data .env
   chmod 600 .env
   ```

2. **HTTPS/SSL**
   ```bash
   # Install SSL certificate (Let's Encrypt recommended)
   sudo certbot certonly --apache -d yourdomain.com
   ```

3. **Database Backup**
   ```bash
   mysqldump -u root -p automagic_erp > backup_$(date +%Y%m%d).sql
   ```

4. **Log Rotation**
   ```bash
   # Setup log rotation in /etc/logrotate.d/
   ```

5. **Environment Settings**
   ```env
   # Update .env for production
   SECURE_COOKIES=true
   APP_DEBUG=false
   ```

6. **Disable Directory Listing**
   Add to `.htaccess`:
   ```
   Options -Indexes
   ```

7. **Test HTTPS**
   ```
   https://yourdomain.com/login.php
   ```

---

## 9. Regular Maintenance

### Daily
- Monitor error logs
- Check failed login attempts
- Backup database (automated recommended)

### Weekly
- Review security logs
- Test backup restoration
- Check disk space

### Monthly
- Update PHP packages
- Review user access
- Audit database

### Quarterly
- Update SSL certificate
- Rotate credentials
- Security audit

---

## 10. Troubleshooting

### "Database connection failed"
- Check `.env` DB credentials
- Verify MySQL is running
- Ensure database exists

### "Session error"
- Check `/tmp` directory permissions
- Verify PHP session.save_path in php.ini
- Ensure write permissions on session directory

### "Login fails"
- Verify user exists in database
- Check password hash is correct
- Review error logs

### "Charts don't display"
- Check Chart.js CDN is accessible
- Verify data queries work
- Check browser console for errors

---

## File Structure After Installation

```
Website1/
├── .env                 (← Create this, add to .gitignore)
├── .env.example        
├── .gitignore          
├── db.php              
├── security.php        
├── config.php          
├── login.php           
├── logout.php          
├── index.php           
├── dashboard.php       
├── SECURITY.md         
├── INSTALLATION.md     
└── includes/
    └── (other files)
```

---

## Support

For issues:
1. Check error logs
2. Review SECURITY.md
3. Verify .env configuration
4. Test database connection

