# Security Implementation Guide

## Overview
This document outlines all security measures implemented in this application.

---

## 🔐 Authentication & Session Management

### Session Security
- **Secure Cookies**: All sessions use httpOnly flag (JavaScript cannot access)
- **HTTPS Only**: Cookies require HTTPS in production
- **SameSite Strict**: CSRF protection via SameSite=Strict cookie attribute
- **Session Timeout**: Sessions expire after 30 minutes of inactivity (configurable)
- **Session Regeneration**: New session ID generated on successful login (prevents session fixation)

### Password Security
- **Hashing**: All passwords stored with PHP's `password_hash()` (bcrypt)
- **Verification**: Login uses `password_verify()` for constant-time comparison
- **Requirements**: Minimum 8 characters, maximum 255 characters

### Rate Limiting
- **Login Attempts**: Maximum 5 failed attempts per IP per 15-minute window
- **Auto-Unlock**: Counter resets after 15 minutes

---

## 🛡️ CSRF Protection

### Implementation
Every form includes a CSRF token via `get_csrf_token()`:
```html
<input type="hidden" name="csrf_token" value="<?php echo safe_output($csrf_token); ?>">
```

### Verification
Server-side validation uses `verify_csrf_token()` with `hash_equals()` for constant-time comparison.

---

## 🔒 Data Protection

### Output Escaping
- **HTML**: All user input escaped with `safe_output()` using `htmlspecialchars()`
- **JSON**: All JSON data escaped with `safe_json()` for JavaScript context
- **Consistent**: Applied throughout all PHP files

### Database Security
- **Parameterized Queries**: All SQL uses prepared statements with placeholders
- **PDO Prepared Statements**: Prevent SQL injection attacks
- **Error Handling**: Database errors logged securely, never exposed to users

### Credentials Management
- **Environment Variables**: All credentials loaded from `.env` file
- **Never Hardcoded**: Database passwords, API keys, tokens stored in `.env`
- **Git Ignored**: `.env` file added to `.gitignore` (never committed)

---

## 📋 Audit Logging

### Events Logged
- ✅ Successful login
- ❌ Failed login attempts
- ⏱️ Rate limit exceeded
- 🔓 Logout events
- ⚠️ CSRF token validation failures
- 📊 All errors and exceptions

### Log Location
Logs written to system error_log (configurable in php.ini)

### Log Format
```
[TIMESTAMP] Event: EVENT_TYPE | User ID: user_id | IP: ip_address | Data: {...}
```

---

## 🔄 File Structure

### Core Security Files
- **`security.php`**: All security utility functions
- **`db.php`**: Secure database connection with environment variables
- **`.env`**: Environment configuration (NEVER commit)
- **`.env.example`**: Template for `.env` file

### Protected Pages
- **`login.php`**: Public (no auth required)
- **`logout.php`**: Protected (auth required)
- **`index.php`**: Protected (auth required)

### Auth Check
```php
require_auth();  // Redirects to login if not authenticated
```

---

## 🚀 Environment Configuration

### Required Variables (.env file)
```env
# Database
DB_HOST=localhost
DB_NAME=automagic_erp
DB_USER=root
DB_PASS=your_password

# Session
SESSION_TIMEOUT_MINUTES=30
MAX_LOGIN_ATTEMPTS=5
LOGIN_ATTEMPT_WINDOW_MINUTES=15

# Security
SECURE_COOKIES=true
BRAND_NAME=YourBrand
APP_DEBUG=false
```

---

## ✅ Security Checklist

### For Deployment
- [ ] Create `.env` file with production credentials
- [ ] Add `.env` to `.gitignore`
- [ ] Set `SECURE_COOKIES=true` for HTTPS
- [ ] Configure error logging location
- [ ] Use strong database password
- [ ] Enable HTTPS/SSL certificate
- [ ] Set appropriate file permissions (644 for PHP, 755 for directories)
- [ ] Remove debug mode (`APP_DEBUG=false`)

### Ongoing
- [ ] Review error logs regularly
- [ ] Monitor failed login attempts
- [ ] Update dependencies regularly
- [ ] Review session timeout settings
- [ ] Audit user activities
- [ ] Rotate database credentials periodically

---

## 🐛 Vulnerabilities Fixed

| Issue | Fix | File |
|-------|-----|------|
| Hardcoded credentials | Environment variables | db.php, .env |
| No CSRF protection | CSRF token validation | login.php, security.php |
| Brute force attacks | Rate limiting + attempt counter | security.php, login.php |
| Session fixation | Session ID regeneration | security.php |
| XSS attacks | Output escaping with htmlspecialchars() | security.php, all PHP files |
| SQL injection | Parameterized queries | All database queries |
| Weak session cookies | Secure flags (httpOnly, secure, samesite) | security.php |
| No session timeout | Activity tracking + timeout check | security.php, login.php |
| No logout | Logout handler with session destruction | logout.php |
| No audit trail | Security event logging | security.php |
| Missing user ID | Store admin_id in session | login.php |
| Exposed errors | Generic error messages + logging | All files |
| Duplicate code | Consolidated dashboard.php into index.php | index.php, dashboard.php |

---

## 📚 Function Reference

### In security.php

#### Session Management
- `init_secure_session()` - Initialize secure session
- `is_session_valid()` - Check if session expired
- `require_auth()` - Require authentication or redirect
- `is_user_authenticated()` - Check if user is logged in
- `get_current_user()` - Get current user data

#### CSRF Protection
- `get_csrf_token()` - Generate CSRF token
- `verify_csrf_token($token)` - Verify CSRF token

#### Rate Limiting
- `check_login_rate_limit()` - Check if login allowed
- `increment_login_attempts()` - Increment attempt counter
- `clear_login_attempts()` - Reset attempt counter

#### Output Escaping
- `safe_output($value, $flags)` - Escape for HTML
- `safe_json($value)` - Escape for JSON

#### Logging
- `log_security_event($event, $data)` - Log security event

---

## 🤝 Contributing

When adding new features:
1. Use parameterized queries for all database operations
2. Escape all user-facing output with `safe_output()`
3. Add CSRF tokens to all forms
4. Log important security events
5. Never hardcode sensitive data
6. Review security.php for utility functions

---

## ⚠️ Important Notes

- **Do NOT commit `.env` file** - Contains production secrets
- **Backup logs regularly** - Important for auditing
- **Test in staging first** - Before deploying to production
- **Monitor failed logins** - Indicates potential attack attempts
- **Keep PHP updated** - Security patches are critical

---

## 📞 Support

For security issues or questions:
1. Review this documentation
2. Check `security.php` for available functions
3. Review error logs for diagnostic information
4. Follow the checklist for deployment

